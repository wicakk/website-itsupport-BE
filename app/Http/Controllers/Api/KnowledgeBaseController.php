<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeBase::with('author:id,name,initials')
            ->published()
            ->orderByDesc('views');

        if ($request->filled('category')) $query->where('category', $request->category);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('title',  'like', "%$s%")
                  ->orWhere('content','like', "%$s%")
            );
        }

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'category'     => 'required|string|max:100',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'is_published' => 'sometimes|boolean',
        ]);

        $article = KnowledgeBase::create([
            ...$data,
            'author_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Artikel berhasil dibuat.', 'article' => $article], 201);
    }

    public function show(KnowledgeBase $knowledge): JsonResponse
    {
        $knowledge->incrementViews();
        return response()->json($knowledge->load('author:id,name,initials'));
    }

    public function update(Request $request, KnowledgeBase $knowledge): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'content'      => 'sometimes|string',
            'category'     => 'sometimes|string|max:100',
            'tags'         => 'sometimes|nullable|array',
            'is_published' => 'sometimes|boolean',
        ]);

        $knowledge->update($data);

        return response()->json(['message' => 'Artikel diperbarui.', 'article' => $knowledge->fresh()]);
    }

    public function destroy(KnowledgeBase $knowledge): JsonResponse
    {
        $knowledge->delete();
        return response()->json(['message' => 'Artikel dihapus.']);
    }

    public function rate(Request $request, KnowledgeBase $knowledge): JsonResponse
    {
        $request->validate(['score' => 'required|integer|min:1|max:5']);
        $knowledge->addRating($request->score);
        return response()->json(['message' => 'Rating disimpan.', 'rating' => $knowledge->fresh()->rating]);
    }
}
