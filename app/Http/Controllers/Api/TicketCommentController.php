<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketCommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $comments = $ticket->comments()
            ->with(['user:id,name,initials,color,role', 'attachments'])
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'body'          => 'nullable|string|min:2',
            'is_internal'   => 'sometimes|boolean',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,zip',
        ]);

        if (!$request->filled('body') && !$request->hasFile('attachments')) {
            return response()->json(['message' => 'Komentar atau file lampiran diperlukan.'], 422);
        }

        $isInternal = $request->boolean('is_internal')
            && $request->user()->isTechnician();

        $comment = $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->body ?? '',
            'is_internal' => $isInternal,
        ]);

        // ✅ Simpan attachment dengan comment_id agar tampil di dalam bubble komentar
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path     = $file->storeAs("tickets/{$ticket->id}/comments/{$comment->id}", $filename, 'public');

                $comment->attachments()->create([
                    'ticket_id'     => $ticket->id,
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
            'message' => 'Komentar ditambahkan.',
            'comment' => $comment->load(['user:id,name,initials,color,role', 'attachments']),
        ], 201);
    }

    public function destroy(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        // Hapus file dari storage saat komentar dihapus
        foreach ($comment->attachments as $attachment) {
            \Storage::disk('public')->delete($attachment->path);
            $attachment->delete();
        }

        $comment->delete();

        return response()->json(['message' => 'Komentar dihapus.']);
    }
}