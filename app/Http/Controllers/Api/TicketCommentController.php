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
            ->with('user:id,name,initials,color,role')
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'body'        => 'required|string|min:2',
            'is_internal' => 'sometimes|boolean',
        ]);

        // Hanya teknisi yang bisa buat catatan internal
        $isInternal = $request->boolean('is_internal')
            && $request->user()->isTechnician();

        $comment = $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->body,
            'is_internal' => $isInternal,
        ]);

        return response()->json([
            'message' => 'Komentar ditambahkan.',
            'comment' => $comment->load('user:id,name,initials,color,role'),
        ], 201);
    }

    public function destroy(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        // Hanya pemilik komentar atau admin yang bisa hapus
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Komentar dihapus.']);
    }
}
