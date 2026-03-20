<?php
// app/Http/Controllers/Api/ProjectController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskHistory;
use App\Models\TaskComment;
use App\Models\ProjectAttachment;
use App\Models\TaskColumn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    // ── Kolom Kanban default (baru) ───────────────────────────────
    private array $defaultColumns = [
        ['name' => 'Mulai Project',    'color' => '#94A3B8', 'position' => 0],
        ['name' => 'Analisa',          'color' => '#6366f1', 'position' => 1],
        ['name' => 'Develop Local',    'color' => '#F59E0B', 'position' => 2],
        ['name' => 'Develop Staging',  'color' => '#8B5CF6', 'position' => 3],
        ['name' => 'Prod',             'color' => '#10B981', 'position' => 4],
    ];

    /** GET /api/projects */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::with(['creator:id,name,initials,color', 'members:id,name,initials,color', 'attachments'])
            ->withCount('tasks')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', fn($m) => $m->where('user_id', $user->id));
            })
            ->latest()
            ->get()
            ->map(function ($project) {
                $totalTasks = $project->tasks()->count();

                // Hitung task di kolom "Prod" (kolom terakhir = selesai)
                $prodColumn = $project->columns()->where('name', 'Prod')->first();
                $completedTasks = $prodColumn
                    ? $project->tasks()->where('column_id', $prodColumn->id)->count()
                    : 0;

                $project->task_stats = [
                    'total'     => $totalTasks,
                    'completed' => $completedTasks,
                    'progress'  => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
                ];

                return $project;
            });

        return response()->json(['success' => true, 'data' => $projects]);
    }

    /** POST /api/projects */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'color'       => 'nullable|string|max:7',
            'status'      => 'nullable|in:active,on_hold,completed,cancelled',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
            'member_ids'  => 'nullable|array',
            'member_ids.*'=> 'exists:users,id',
        ]);

        $project = Project::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'color'      => $validated['color'] ?? '#6366f1',
            'status'     => $validated['status'] ?? 'active',
        ]);

        // Buat kolom Kanban default baru
        foreach ($this->defaultColumns as $col) {
            TaskColumn::create(['project_id' => $project->id, ...$col]);
        }

        // Creator sebagai owner
        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        // Tambah member lain
        if (!empty($validated['member_ids'])) {
            foreach ($validated['member_ids'] as $uid) {
                if ($uid != $request->user()->id) {
                    $project->members()->attach($uid, ['role' => 'member']);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Project berhasil dibuat.',
            'data'    => $project->load(['creator:id,name,initials,color', 'members:id,name,initials,color']),
        ], 201);
    }

    /** GET /api/projects/{project} */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        // Auto-create kolom baru jika project lama (kolom kosong)
        if ($project->columns()->count() === 0) {
            foreach ($this->defaultColumns as $col) {
                TaskColumn::create(['project_id' => $project->id, ...$col]);
            }
        }

        $project->load([
            'creator:id,name,initials,color',
            'members:id,name,initials,color',
            'columns.tasks.assignee:id,name,initials,color',
            'columns.tasks.creator:id,name,initials,color',
            'columns.tasks.attachments.uploader:id,name',
            'attachments.uploader:id,name',
        ]);

        return response()->json(['success' => true, 'data' => $project]);
    }

    /** PUT /api/projects/{project} */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project, 'owner');

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'color'       => 'nullable|string|max:7',
            'status'      => 'nullable|in:active,on_hold,completed,cancelled',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $project->load(['creator:id,name,initials,color', 'members:id,name,initials,color']),
        ]);
    }

    /** DELETE /api/projects/{project} */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project, 'owner');
        $project->delete();
        return response()->json(['success' => true, 'message' => 'Project dihapus.']);
    }

    /** PUT /api/projects/{project}/members */
    public function syncMembers(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project, 'owner');

        $validated = $request->validate([
            'member_ids'   => 'required|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        $ownerIds = $project->members()->wherePivot('role', 'owner')->pluck('users.id')->toArray();
        $syncData = [];
        foreach ($ownerIds as $id)              $syncData[$id] = ['role' => 'owner'];
        foreach ($validated['member_ids'] as $id) {
            if (!isset($syncData[$id]))         $syncData[$id] = ['role' => 'member'];
        }
        $project->members()->sync($syncData);

        return response()->json([
            'success' => true,
            'data'    => $project->load('members:id,name,initials,color')->members,
        ]);
    }

    // ── Tasks ─────────────────────────────────────────────────────

    public function storeTask(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'column_id'   => 'required|exists:task_columns,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $maxPos = Task::where('column_id', $validated['column_id'])->max('position') ?? -1;

        $task = Task::create([
            ...$validated,
            'project_id' => $project->id,
            'created_by' => $request->user()->id,
            'priority'   => $validated['priority'] ?? 'medium',
            'position'   => $maxPos + 1,
        ]);

        TaskHistory::create([
            'task_id'     => $task->id,
            'user_id'     => $request->user()->id,
            'type'        => 'created',
            'description' => 'Task dibuat di kolom ' . ($task->column->name ?? '-'),
            'to_value'    => $task->column->name ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $task->load(['assignee:id,name,initials,color', 'creator:id,name,initials,color', 'attachments']),
        ], 201);
    }

    public function updateTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'column_id'   => 'sometimes|exists:task_columns,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'position'    => 'nullable|integer',
        ]);

        $userId = $request->user()->id;

        // Catat history: pindah kolom
        if (isset($validated['column_id']) && (int)$validated['column_id'] !== (int)$task->column_id) {
            $oldCol = TaskColumn::find($task->column_id)?->name ?? '-';
            $newCol = TaskColumn::find($validated['column_id'])?->name ?? '-';
            TaskHistory::create([
                'task_id'     => $task->id,
                'user_id'     => $userId,
                'type'        => 'column_changed',
                'description' => "Dipindahkan dari \"{$oldCol}\" ke \"{$newCol}\"",
                'from_value'  => $oldCol,
                'to_value'    => $newCol,
            ]);
        }

        // Catat history: ganti assignee
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] != $task->assigned_to) {
            $oldUser = $task->assigned_to ? (\App\Models\User::find($task->assigned_to)?->name ?? '-') : 'Tidak ada';
            $newUser = $validated['assigned_to'] ? (\App\Models\User::find($validated['assigned_to'])?->name ?? '-') : 'Tidak ada';
            TaskHistory::create([
                'task_id'     => $task->id,
                'user_id'     => $userId,
                'type'        => 'assignee_changed',
                'description' => "Assignee diubah: {$oldUser} → {$newUser}",
                'from_value'  => $oldUser,
                'to_value'    => $newUser,
            ]);
        }

        // Catat history: ganti prioritas
        if (isset($validated['priority']) && $validated['priority'] !== $task->priority) {
            $labels = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
            $oldP = $labels[$task->priority] ?? $task->priority;
            $newP = $labels[$validated['priority']] ?? $validated['priority'];
            TaskHistory::create([
                'task_id'     => $task->id,
                'user_id'     => $userId,
                'type'        => 'priority_changed',
                'description' => "Prioritas diubah: {$oldP} → {$newP}",
                'from_value'  => $oldP,
                'to_value'    => $newP,
            ]);
        }

        $task->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $task->load(['assignee:id,name,initials,color', 'creator:id,name,initials,color', 'attachments']),
        ]);
    }

    /** DELETE /api/projects/{project}/tasks/{task} */
    public function destroyTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        // Hapus file attachment dari storage
        foreach ($task->attachments as $att) {
            Storage::delete($att->path);
        }

        $task->delete();
        return response()->json(['success' => true]);
    }

    /** PUT /api/projects/{project}/tasks/reorder */
    public function reorderTasks(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'tasks'             => 'required|array',
            'tasks.*.id'        => 'required|exists:tasks,id',
            'tasks.*.column_id' => 'required|exists:task_columns,id',
            'tasks.*.position'  => 'required|integer',
        ]);

        // Cache nama kolom agar tidak query berulang
        $columnNames = TaskColumn::whereIn('id', collect($validated['tasks'])->pluck('column_id'))
            ->pluck('name', 'id');

        foreach ($validated['tasks'] as $t) {
            $task = Task::find($t['id']);
            if (!$task) continue;

            // Catat history jika kolom berubah
            if ((int)$task->column_id !== (int)$t['column_id']) {
                $oldCol = $columnNames[$task->column_id] ?? TaskColumn::find($task->column_id)?->name ?? '-';
                $newCol = $columnNames[$t['column_id']] ?? '-';

                TaskHistory::create([
                    'task_id'     => $task->id,
                    'user_id'     => $request->user()->id,
                    'type'        => 'column_changed',
                    'description' => "Dipindahkan dari \"{$oldCol}\" ke \"{$newCol}\"",
                    'from_value'  => $oldCol,
                    'to_value'    => $newCol,
                ]);
            }

            $task->update([
                'column_id' => $t['column_id'],
                'position'  => $t['position'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ── Attachments ───────────────────────────────────────────────

    public function uploadAttachment(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $request->validate([
            'file' => 'required|file|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store("task-attachments/{$task->id}", 'public');

        $attachment = TaskAttachment::create([
            'task_id'     => $task->id,
            'uploaded_by' => $request->user()->id,
            'filename'    => $file->getClientOriginalName(),
            'path'        => $path,
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $attachment->load('uploader:id,name'),
        ], 201);
    }

    /**
     * DELETE /api/projects/{project}/tasks/{task}/attachments/{attachment}
     */
    public function deleteAttachment(Request $request, Project $project, Task $task, TaskAttachment $attachment): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['success' => true]);
    }

    // ── Project Attachments ───────────────────────────────────────

    /** POST /api/projects/{project}/attachments */
    public function uploadProjectAttachment(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $request->validate(['file' => 'required|file|max:10240']);

        $file = $request->file('file');
        $path = $file->store("project-attachments/{$project->id}", 'public');

        $att = ProjectAttachment::create([
            'project_id'  => $project->id,
            'uploaded_by' => $request->user()->id,
            'filename'    => $file->getClientOriginalName(),
            'path'        => $path,
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
        ]);

        return response()->json(['success' => true, 'data' => $att->load('uploader:id,name')], 201);
    }

    /** DELETE /api/projects/{project}/attachments/{attachment} */
    public function deleteProjectAttachment(Request $request, Project $project, ProjectAttachment $attachment): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(['success' => true]);
    }

    // ── Helper ────────────────────────────────────────────────────
    private function authorizeProject($user, Project $project, string $minRole = 'member'): void
    {
        $isMember = $project->members()->where('user_id', $user->id)->exists()
            || $project->created_by === $user->id;

        if (!$isMember && !in_array($user->role, ['super_admin', 'manager_it'])) {
            abort(403, 'Akses ditolak.');
        }

        if ($minRole === 'owner') {
            $isOwner = $project->created_by === $user->id
                || $project->members()->where('user_id', $user->id)->wherePivot('role', 'owner')->exists()
                || in_array($user->role, ['super_admin', 'manager_it']);

            if (!$isOwner) abort(403, 'Hanya owner yang bisa melakukan ini.');
        }
    }

    // ── Task Tracking ─────────────────────────────────────────────

    /** GET /api/projects/{project}/tasks/{task}/tracking */
    public function taskTracking(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        return response()->json([
            'success'     => true,
            'task'        => $task->load(['assignee:id,name,initials,color','creator:id,name,initials,color','column:id,name']),
            'histories'   => TaskHistory::where('task_id', $task->id)->with('user:id,name,initials,color')->latest()->get(),
            'comments'    => TaskComment::where('task_id', $task->id)->with('user:id,name,initials,color')->latest()->get(),
            'attachments' => $task->attachments()->with('uploader:id,name,initials,color')->latest()->get(),
        ]);
    }

    /** POST /api/projects/{project}/tasks/{task}/comments */
    public function storeComment(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        TaskHistory::create([
            'task_id'     => $task->id,
            'user_id'     => $request->user()->id,
            'type'        => 'comment_added',
            'description' => 'Menambahkan komentar',
        ]);

        return response()->json(['success' => true, 'data' => $comment->load('user:id,name,initials,color')], 201);
    }

    /** DELETE /api/projects/{project}/tasks/{task}/comments/{comment} */
    public function destroyComment(Request $request, Project $project, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);
        if ($comment->user_id !== $request->user()->id) abort(403, 'Hanya pembuat komentar yang bisa menghapus.');
        $comment->delete();
        return response()->json(['success' => true]);
    }
}
