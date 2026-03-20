<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * GET /api/tickets
     * Query params: status, priority, category, assigned_to, search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['requester:id,name,initials,color', 'assignee:id,name,initials,color'])
            ->forUser($request->user())
            ->latest();

        // Filters
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('priority'))    $query->where('priority', $request->priority);
        if ($request->filled('category'))    $query->where('category', $request->category);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('overdue'))     $query->overdue();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('title',         'like', "%$s%")
                  ->orWhere('ticket_number','like', "%$s%")
                  ->orWhere('description',  'like', "%$s%")
            );
        }

        $tickets = $query->paginate($request->per_page ?? 15);

        return response()->json($tickets);
    }

    /**
     * POST /api/tickets
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'category'        => ['required', Rule::in(['Hardware','Software','Network','Email','Printer','Server','Security','Others'])],
            'priority'        => ['required', Rule::in(['Low','Medium','High','Critical'])],
            'department'      => 'nullable|string|max:100',
            'attachments'     => 'nullable|array|max:5',
            'attachments.*'   => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,zip',
        ]);

        $ticket = Ticket::create([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'category'     => $data['category'],
            'priority'     => $data['priority'],
            'department'   => $data['department'] ?? null,
            'requester_id' => $request->user()->id,
            'status'       => 'Open',
        ]);

        // Handle attachments jika ada
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path     = $file->storeAs("tickets/{$ticket->id}", $filename, 'public');

                $ticket->attachments()->create([
                    'user_id'       => $request->user()->id,
                    'filename'      => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'file_size'     => $file->getSize(),
                    'path'          => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Tiket berhasil dibuat.',
            'ticket'  => $ticket->load(['requester:id,name,initials,color', 'attachments']),
        ], 201);
    }

    /**
     * GET /api/tickets/{ticket}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json(
            $ticket->load([
                'requester:id,name,initials,color,department',
                'assignee:id,name,initials,color',
                'comments.user:id,name,initials,color,role',
                'attachments',
            ])
        );
    }

    /**
     * PUT /api/tickets/{ticket}
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category'    => ['sometimes', Rule::in(['Hardware','Software','Network','Email','Printer','Server','Security','Others'])],
            'priority'    => ['sometimes', Rule::in(['Low','Medium','High','Critical'])],
            'status'      => ['sometimes', Rule::in(['Open','Assigned','In Progress','Waiting User','Resolved','Closed'])],
            'department'  => 'sometimes|nullable|string|max:100',
        ]);

        $ticket->update($data);

        return response()->json(['message' => 'Tiket diperbarui.', 'ticket' => $ticket->fresh()]);
    }

    /**
     * DELETE /api/tickets/{ticket}
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();
        return response()->json(['message' => 'Tiket dihapus.']);
    }

    /**
     * POST /api/tickets/{ticket}/assign
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $assignee = User::find($request->assigned_to);

        $ticket->update([
            'assigned_to' => $request->assigned_to,
            'status'      => 'Assigned',
        ]);

        // Add system comment
        $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => "Tiket di-assign ke {$assignee->name}.",
            'is_internal' => true,
        ]);

        return response()->json(['message' => "Tiket di-assign ke {$assignee->name}.", 'ticket' => $ticket->fresh()]);
    }

    /**
     * POST /api/tickets/{ticket}/resolve
     */
    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'resolution_notes' => 'required|string|min:10',
        ]);

        $ticket->update([
            'status'           => 'Resolved',
            'resolution_notes' => $request->resolution_notes,
        ]);

        $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => "Tiket diselesaikan. Catatan: {$request->resolution_notes}",
            'is_internal' => false,
        ]);

        return response()->json(['message' => 'Tiket berhasil diselesaikan.', 'ticket' => $ticket->fresh()]);
    }

    /**
     * POST /api/tickets/{ticket}/close
     */
    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'Closed']);

        return response()->json(['message' => 'Tiket ditutup.', 'ticket' => $ticket->fresh()]);
    }

    /**
     * POST /api/tickets/{ticket}/reopen
     */
    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'Open', 'resolved_at' => null, 'closed_at' => null]);

        $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => 'Tiket dibuka kembali.',
            'is_internal' => true,
        ]);

        return response()->json(['message' => 'Tiket dibuka kembali.', 'ticket' => $ticket->fresh()]);
    }

    /**
     * POST /api/tickets/{ticket}/rate
     */
    public function rate(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate(['rating' => 'required|integer|min:1|max:5']);

        $ticket->update(['satisfaction_rating' => $request->rating]);

        return response()->json(['message' => 'Rating berhasil disimpan.']);
    }

    /**
     * POST /api/tickets/{ticket}/attachments
     */
    public function uploadAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,zip',
        ]);

        $file      = $request->file('file');
        $filename  = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path      = $file->storeAs("tickets/{$ticket->id}", $filename, 'public');

        $attachment = $ticket->attachments()->create([
            'user_id'       => $request->user()->id,
            'filename'      => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'path'          => $path,
        ]);

        return response()->json(['message' => 'File berhasil diupload.', 'attachment' => $attachment], 201);
    }
}
