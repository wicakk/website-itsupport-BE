<?php
// app/Http/Controllers/Api/TicketCategoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCategoryController extends Controller
{
    /**
     * GET /api/ticket-categories
     * List semua kategori (termasuk jumlah tiket per kategori)
     */
    public function index(): JsonResponse
    {
        $categories = TicketCategory::orderBy('order')->orderBy('name')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'tickets_count' => Ticket::where('category', $c->name)->count(),
            ]));

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * GET /api/ticket-categories/active
     * Hanya yang aktif — dipakai dropdown form tiket
     */
    public function active(): JsonResponse
    {
        $categories = TicketCategory::active()->get(['id', 'name', 'color']);
        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * POST /api/ticket-categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:ticket_categories,name',
            'color'       => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'order'       => 'nullable|integer|min:0',
        ]);

        $category = TicketCategory::create([
            'name'        => $validated['name'],
            'color'       => $validated['color'] ?? '#6366f1',
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
            'order'       => $validated['order'] ?? TicketCategory::max('order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan.',
            'data'    => $category,
        ], 201);
    }

    /**
     * PUT /api/ticket-categories/{category}
     */
    public function update(Request $request, TicketCategory $ticketCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:ticket_categories,name,' . $ticketCategory->id,
            'color'       => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'order'       => 'nullable|integer|min:0',
        ]);

        $oldName = $ticketCategory->name;
        $ticketCategory->update($validated);

        // Update nama kategori di semua tiket yang pakai nama lama
        if (isset($validated['name']) && $validated['name'] !== $oldName) {
            Ticket::where('category', $oldName)->update(['category' => $validated['name']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diupdate.',
            'data'    => $ticketCategory,
        ]);
    }

    /**
     * DELETE /api/ticket-categories/{category}
     */
    public function destroy(TicketCategory $ticketCategory): JsonResponse
    {
        $ticketsCount = Ticket::where('category', $ticketCategory->name)->count();

        if ($ticketsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak bisa hapus — kategori ini dipakai oleh {$ticketsCount} tiket. Nonaktifkan saja.",
            ], 422);
        }

        $ticketCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    /**
     * PUT /api/ticket-categories/reorder
     * Ubah urutan kategori via drag & drop
     * Body: { "orders": [{"id": 1, "order": 0}, {"id": 2, "order": 1}, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders'          => 'required|array',
            'orders.*.id'     => 'required|exists:ticket_categories,id',
            'orders.*.order'  => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            TicketCategory::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }
}
