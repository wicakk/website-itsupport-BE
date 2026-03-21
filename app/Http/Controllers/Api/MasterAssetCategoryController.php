<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterAssetCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterAssetCategoryController extends Controller
{
    /**
     * GET /api/master/asset-categories
     * Query: search, active_only, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterAssetCategory::query()->orderBy('sort_order')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('per_page')) {
            return response()->json($query->paginate($request->per_page));
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/master/asset-categories
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $category = MasterAssetCategory::create($data);

        return response()->json(['message' => 'Kategori berhasil ditambahkan.', 'category' => $category], 201);
    }

    /**
     * GET /api/master/asset-categories/{category}
     */
    public function show(MasterAssetCategory $assetCategory): JsonResponse
    {
        return response()->json($assetCategory);
    }

    /**
     * PUT /api/master/asset-categories/{category}
     */
    public function update(Request $request, MasterAssetCategory $assetCategory): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $assetCategory->update($data);

        return response()->json(['message' => 'Kategori berhasil diperbarui.', 'category' => $assetCategory->fresh()]);
    }

    /**
     * DELETE /api/master/asset-categories/{category}
     */
    public function destroy(MasterAssetCategory $assetCategory): JsonResponse
    {
        $assetCategory->delete();
        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
