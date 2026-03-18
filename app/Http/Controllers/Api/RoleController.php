<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     * List semua roles beserta permissions-nya.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->withCount('users')->get();

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ]);
    }

    /**
     * POST /api/roles
     * Buat role baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100|unique:roles,name|alpha_dash',
            'display_name'   => 'nullable|string|max:150',
            'description'    => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name'         => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'description'  => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permission_ids'])) {
            $role->syncPermissions($validated['permission_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dibuat.',
            'data'    => $role->load('permissions'),
        ], 201);
    }

    /**
     * GET /api/roles/{id}
     * Detail satu role.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $role->load('permissions'),
        ]);
    }

    /**
     * PUT /api/roles/{id}
     * Update role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'name')->ignore($role->id)],
            'display_name'   => 'nullable|string|max:150',
            'description'    => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->update(array_filter([
            'name'         => $validated['name'] ?? $role->name,
            'display_name' => $validated['display_name'] ?? $role->display_name,
            'description'  => $validated['description'] ?? $role->description,
        ]));

        if (array_key_exists('permission_ids', $validated)) {
            $role->syncPermissions($validated['permission_ids'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diupdate.',
            'data'    => $role->load('permissions'),
        ]);
    }

    /**
     * DELETE /api/roles/{id}
     * Hapus role.
     */
    public function destroy(Role $role): JsonResponse
    {
        // Cegah hapus role yang masih dipakai user
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Role tidak bisa dihapus karena masih digunakan oleh ' . $role->users()->count() . ' user.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus.',
        ]);
    }

    /**
     * GET /api/permissions
     * List semua permissions (dipakai saat form role).
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn($items, $group) => [
                'group' => $group,
                'items' => $items,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $permissions,
        ]);
    }
}
