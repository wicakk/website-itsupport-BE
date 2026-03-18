<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: CheckRole
 * 
 * Cara pakai di routes/api.php:
 *   Route::middleware('role:admin')->group(...)
 *   Route::middleware('role:admin,manager')->group(...) // salah satu
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Role tidak mencukupi.',
            ], 403);
        }

        return $next($request);
    }
}
