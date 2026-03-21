<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterLocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterLocationController extends Controller
{
    /**
     * GET /api/master/locations
     * Query: search, active_only, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterLocation::query()->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('name',     'like', "%$s%")
                  ->orWhere('code',   'like', "%$s%")
                  ->orWhere('building','like', "%$s%")
            );
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Jika tidak ada per_page, kembalikan semua (untuk dropdown)
        if ($request->filled('per_page')) {
            return response()->json($query->paginate($request->per_page));
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/master/locations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50|unique:master_locations,code',
            'building'    => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $location = MasterLocation::create($data);

        return response()->json([
            'message'  => 'Lokasi berhasil ditambahkan.',
            'location' => $location,
        ], 201);
    }

    /**
     * GET /api/master/locations/{location}
     */
    public function show(MasterLocation $location): JsonResponse
    {
        return response()->json($location);
    }

    /**
     * PUT /api/master/locations/{location}
     */
    public function update(Request $request, MasterLocation $location): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'code'        => 'nullable|string|max:50|unique:master_locations,code,' . $location->id,
            'building'    => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $location->update($data);

        return response()->json([
            'message'  => 'Lokasi berhasil diperbarui.',
            'location' => $location->fresh(),
        ]);
    }

    /**
     * DELETE /api/master/locations/{location}
     */
    public function destroy(MasterLocation $location): JsonResponse
    {
        $location->delete();

        return response()->json(['message' => 'Lokasi berhasil dihapus.']);
    }
}
