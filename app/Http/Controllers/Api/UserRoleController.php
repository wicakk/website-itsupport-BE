<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * GET /api/users
     * List semua users beserta roles-nya.
     */
    public function index(): JsonResponse
    {
        $users = User::with('roles')->get()->map(fn($user) => [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'roles'      => $user->roles,
            'created_at' => $user->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * GET /api/users/{id}/roles
     * Roles yang dimiliki satu user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'roles' => $user->roles,
            ],
        ]);
    }

    /**
     * PUT /api/users/{id}/roles
     * Sync roles user (replace semua).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->syncRoles($validated['role_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Role user berhasil diupdate.',
            'data'    => [
                'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'roles' => $user->roles()->get(),
            ],
        ]);
    }
}
