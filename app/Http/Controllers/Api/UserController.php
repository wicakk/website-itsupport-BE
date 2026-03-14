<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::latest();

        if ($request->filled('role'))   $query->where('role', $request->role);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('name',       'like', "%$s%")
                  ->orWhere('email',      'like', "%$s%")
                  ->orWhere('department', 'like', "%$s%")
            );
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|string|min:8|confirmed',
            'role'       => ['required', Rule::in(['super_admin','manager_it','it_support','user'])],
            'department' => 'nullable|string|max:100',
            'phone'      => 'nullable|string|max:20',
        ]);

        // Auto-generate initials
        $words    = explode(' ', $data['name']);
        $initials = strtoupper(
            count($words) >= 2
                ? $words[0][0] . $words[1][0]
                : substr($data['name'], 0, 2)
        );

        $colors   = ['#3B8BFF','#8B5CF6','#06B6D4','#10B981','#F59E0B','#F97316'];
        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'initials' => $initials,
            'color'    => $colors[array_rand($colors)],
        ]);

        return response()->json(['message' => 'User berhasil dibuat.', 'user' => $user], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->loadCount(['requestedTickets','assignedTickets']));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'department' => 'nullable|string|max:255',
            'role' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'User dihapus.']);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return response()->json(['message' => "User berhasil {$status}.", 'user' => $user->fresh()]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['message' => 'Password user berhasil direset.']);
    }
}
