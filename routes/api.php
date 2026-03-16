<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ServerMonitorController;

// ─── Public routes ─────────────────────────

// Auth
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);


// ─── Authenticated routes ──────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('me', [AuthController::class, 'me']);
    Route::put('me', [AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::put('password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/chart', [DashboardController::class, 'chart']);

    // Tickets
    Route::apiResource('tickets', TicketController::class);

    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve']);
    Route::post('tickets/{ticket}/close', [TicketController::class, 'close']);
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen']);
    Route::post('tickets/{ticket}/rate', [TicketController::class, 'rate']);

    // Comments
    Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);

    // Attachments
    Route::post('tickets/{ticket}/attachments', [TicketController::class, 'uploadAttachment']);

    // Assets
    Route::apiResource('assets', AssetController::class);
    Route::post('assets/{asset}/assign', [AssetController::class, 'assign']);
    Route::post('assets/{asset}/unassign', [AssetController::class, 'unassign']);
    Route::post('assets/{asset}/maintain', [AssetController::class, 'setMaintenance']);

    Route::post('/assets/{asset}/pm', [AssetController::class, 'storePM']);
    Route::patch('/assets/{asset}/pm/{pm}', [AssetController::class, 'completePM']);

    // Knowledge Base
    Route::apiResource('knowledge', KnowledgeBaseController::class);
    Route::post('knowledge/{article}/rate', [KnowledgeBaseController::class, 'rate']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

    // Reports
    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('reports/tickets', [ReportController::class, 'tickets']);
    Route::get('reports/sla', [ReportController::class, 'sla']);
    Route::get('reports/technicians', [ReportController::class, 'technicians']);
    Route::get('reports/assets', [ReportController::class, 'assets']);
    Route::get('reports/export', [ReportController::class, 'export']);

    // Server Monitoring
    Route::get('monitoring',              [ServerMonitorController::class, 'index']);
    Route::get('monitoring/{server}',     [ServerMonitorController::class, 'show']);
    Route::post('monitoring',             [ServerMonitorController::class, 'store']); 
    Route::post('monitoring/{server}/ping', [ServerMonitorController::class, 'ping']);
    Route::delete('monitoring/{server}', [ServerMonitorController::class, 'destroy']);


    Route::get('notifications', function () {
        return response()->json([
            'data'  => [],
            'total' => 0,
        ]);
    });

});