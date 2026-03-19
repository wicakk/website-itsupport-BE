<?php
// app/Http/Controllers/Api/ProjectController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskColumn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // ── Projects ──────────────────────────────────────────────────

    /** GET /api/projects */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::with(['creator:id,name,initials,color', 'members:id,name,initials,color'])
            ->withCount('tasks')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', fn($m) => $m->where('user_id', $user->id));
            })
            ->latest()
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), [
                'task_stats' => [
                    'total' => $p->tasks_count,
                ],
            ]));

        return response()->json(['success' => true, 'data' => $projects]);
    }

    /** POST /api/projects */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
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

        // Default Kanban columns
        $columns = ['To Do', 'In Progress', 'Review', 'Done'];
        $colors  = ['#94A3B8', '#6366f1', '#F59E0B', '#10B981'];
        foreach ($columns as $i => $name) {
            TaskColumn::create([
                'project_id' => $project->id,
                'name'       => $name,
                'color'      => $colors[$i],
                'position'   => $i,
            ]);
        }

        // Add creator as owner
        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        // Add other members
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

        // Auto-create default columns jika belum ada (untuk project lama)
        if ($project->columns()->count() === 0) {
            $defaults = [
                ['name' => 'To Do',       'color' => '#94A3B8', 'position' => 0],
                ['name' => 'In Progress', 'color' => '#6366f1', 'position' => 1],
                ['name' => 'Review',      'color' => '#F59E0B', 'position' => 2],
                ['name' => 'Done',        'color' => '#10B981', 'position' => 3],
            ];
            foreach ($defaults as $col) {
                TaskColumn::create(array_merge($col, ['project_id' => $project->id]));
            }
        }

        $project->load([
            'creator:id,name,initials,color',
            'members:id,name,initials,color',
            'columns.tasks.assignee:id,name,initials,color',
            'columns.tasks.creator:id,name,initials,color',
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
            'color'       => 'nullable|string|max:7',
            'status'      => 'nullable|in:active,on_hold,completed,cancelled',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project berhasil diupdate.',
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

    // ── Members ───────────────────────────────────────────────────

    /** PUT /api/projects/{project}/members */
    public function syncMembers(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project, 'owner');

        $validated = $request->validate([
            'member_ids'   => 'required|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        // Selalu pertahankan owner
        $ownerIds = $project->members()->wherePivot('role', 'owner')->pluck('users.id')->toArray();
        $syncData = [];
        foreach ($ownerIds as $id) {
            $syncData[$id] = ['role' => 'owner'];
        }
        foreach ($validated['member_ids'] as $id) {
            if (!isset($syncData[$id])) {
                $syncData[$id] = ['role' => 'member'];
            }
        }
        $project->members()->sync($syncData);

        return response()->json([
            'success' => true,
            'data'    => $project->load('members:id,name,initials,color')->members,
        ]);
    }

    // ── Tasks ─────────────────────────────────────────────────────

    /** POST /api/projects/{project}/tasks */
    public function storeTask(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'column_id'   => 'required|exists:task_columns,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $maxPos = Task::where('column_id', $validated['column_id'])->max('position') ?? -1;

        $task = Task::create([
            ...$validated,
            'project_id'  => $project->id,
            'created_by'  => $request->user()->id,
            'priority'    => $validated['priority'] ?? 'medium',
            'position'    => $maxPos + 1,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $task->load(['assignee:id,name,initials,color', 'creator:id,name,initials,color']),
        ], 201);
    }

    /** PUT /api/projects/{project}/tasks/{task} */
    public function updateTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'column_id'   => 'sometimes|exists:task_columns,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'position'    => 'nullable|integer',
        ]);

        $task->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $task->load(['assignee:id,name,initials,color', 'creator:id,name,initials,color']),
        ]);
    }

    /** DELETE /api/projects/{project}/tasks/{task} */
    public function destroyTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);
        $task->delete();
        return response()->json(['success' => true, 'message' => 'Task dihapus.']);
    }

    /** PUT /api/projects/{project}/tasks/reorder — drag & drop */
    public function reorderTasks(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'tasks'            => 'required|array',
            'tasks.*.id'       => 'required|exists:tasks,id',
            'tasks.*.column_id'=> 'required|exists:task_columns,id',
            'tasks.*.position' => 'required|integer',
        ]);

        foreach ($validated['tasks'] as $t) {
            Task::where('id', $t['id'])->update([
                'column_id' => $t['column_id'],
                'position'  => $t['position'],
            ]);
        }

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
                || $project->members()->where('user_id', $user->id)
                    ->wherePivot('role', 'owner')->exists()
                || in_array($user->role, ['super_admin', 'manager_it']);

            if (!$isOwner) abort(403, 'Hanya owner yang bisa melakukan ini.');
        }
    }
}
