<?php
// app/Http/Controllers/Api/RoleController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     * List semua roles + permissions-nya.
     * Dipakai frontend untuk load data awal RolesPage.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name,display_name,group')
            ->withCount('users')
            ->get();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    /**
     * GET /api/permissions
     * Semua permissions dikelompokkan per group.
     * Dipakai untuk render checkbox matrix di RolesPage.
     */
    public function permissions(): JsonResponse
    {
        $grouped = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn($items, $group) => [
                'group' => $group,
                'key'   => $group,
                'items' => $items->map(fn($p) => [
                    'id'    => $p->id,
                    'key'   => $p->name,   // "tickets.view" dll
                    'label' => $p->display_name,
                    'group' => $p->group,
                ])->values(),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * PUT /api/roles/{role}/permissions
     * Sync permissions ke role tertentu.
     * Body: { "permission_ids": [1, 2, 3] }
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids'   => 'present|array',   // present = wajib ada tapi boleh kosong
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json([
            'success' => true,
            'message' => "Permissions untuk role '{$role->display_name}' berhasil diperbarui.",
            'data'    => $role->load('permissions:id,name,display_name,group'),
        ]);
    }

    /**
     * GET /api/me/permissions
     * Permissions milik user yang sedang login.
     * Dipakai PermissionContext frontend saat app load & setelah login.
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cari role berdasarkan enum user.role
        $role = Role::where('name', $user->role)
            ->with('permissions:id,name')
            ->first();

        $permissions = $role
            ? $role->permissions->pluck('name')->toArray()
            : [];

        return response()->json([
            'success'     => true,
            'role'        => $user->role,
            'permissions' => $permissions,
        ]);
    }
}
