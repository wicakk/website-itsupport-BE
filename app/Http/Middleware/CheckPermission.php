<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: CheckPermission
 *
 * Cara pakai di routes/api.php:
 *   Route::middleware('permission:roles.create')->group(...)
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Permission '{$permission}' diperlukan.",
            ], 403);
        }

        return $next($request);
    }
}
