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
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TicketCategoryController;
use App\Http\Controllers\Api\MasterLocationController;
use App\Http\Controllers\Api\MasterAssetCategoryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectReportController;


// ─── Public routes ─────────────────────────
Route::post('login',          [AuthController::class, 'login']);
Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// ─── Authenticated routes ──────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('me',       [AuthController::class, 'me']);
    Route::put('me',       [AuthController::class, 'updateProfile']);
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::put('password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('dashboard',       [DashboardController::class, 'index']);
    Route::get('dashboard/chart', [DashboardController::class, 'chart']);

    // Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/assign',  [TicketController::class, 'assign']);
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve']);
    Route::post('tickets/{ticket}/close',   [TicketController::class, 'close']);
    Route::post('tickets/{ticket}/reopen',  [TicketController::class, 'reopen']);
    Route::post('tickets/{ticket}/rate',    [TicketController::class, 'rate']);

    // Comments
    Route::get('tickets/{ticket}/comments',              [TicketCommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments',             [TicketCommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);

    // Attachments
    Route::post('tickets/{ticket}/attachments', [TicketController::class, 'uploadAttachment']);

    // Assets
    Route::apiResource('assets', AssetController::class);
    Route::post('assets/{asset}/assign',    [AssetController::class, 'assign']);
    Route::post('assets/{asset}/unassign',  [AssetController::class, 'unassign']);
    Route::post('assets/{asset}/maintain',  [AssetController::class, 'setMaintenance']);
    Route::post('/assets/{asset}/pm',       [AssetController::class, 'storePM']);
    Route::patch('/assets/{asset}/pm/{pm}', [AssetController::class, 'completePM']);

    // Knowledge Base
    Route::apiResource('knowledge', KnowledgeBaseController::class);
    Route::post('knowledge/{article}/rate', [KnowledgeBaseController::class, 'rate']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/toggle-active',  [UserController::class, 'toggleActive']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

    // Reports
    Route::get('reports/summary',     [ReportController::class, 'summary']);
    Route::get('reports/tickets',     [ReportController::class, 'tickets']);
    Route::get('reports/sla',         [ReportController::class, 'sla']);
    Route::get('reports/technicians', [ReportController::class, 'technicians']);
    Route::get('reports/assets',      [ReportController::class, 'assets']);
    Route::get('reports/export',      [ReportController::class, 'export']);

    // Project Reports
    Route::prefix('project-reports')->group(function () {
        Route::get('/summary', [ReportController::class, 'summaryproject']);
        Route::get('/projects', [ReportController::class, 'projects']);
        Route::get('/tasks', [ReportController::class, 'tasks']);
        Route::get('/team-performance', [ReportController::class, 'teamPerformance']);
        Route::get('/timeline', [ReportController::class, 'timeline']);
    });



    // Server Monitoring
    Route::get('monitoring',                [ServerMonitorController::class, 'index']);
    Route::get('monitoring/{server}',       [ServerMonitorController::class, 'show']);
    Route::post('monitoring',               [ServerMonitorController::class, 'store']);
    Route::post('monitoring/{server}/ping', [ServerMonitorController::class, 'ping']);
    Route::delete('monitoring/{server}',    [ServerMonitorController::class, 'destroy']);

    // Permissions
    Route::get('me/permissions', [RoleController::class, 'myPermissions']);
    Route::get('permissions',    [RoleController::class, 'permissions']);

    // Kategori tiket
    Route::get('ticket-categories/active',              [TicketCategoryController::class, 'active']);
    Route::get('ticket-categories',                     [TicketCategoryController::class, 'index']);
    Route::post('ticket-categories',                    [TicketCategoryController::class, 'store']);
    Route::put('ticket-categories/reorder',             [TicketCategoryController::class, 'reorder']);
    Route::put('ticket-categories/{ticketCategory}',    [TicketCategoryController::class, 'update']);
    Route::delete('ticket-categories/{ticketCategory}', [TicketCategoryController::class, 'destroy']);

    // Task Tracking & Komentar
    Route::get('projects/{project}/tasks/{task}/tracking',              [ProjectController::class, 'taskTracking']);
    Route::post('projects/{project}/tasks/{task}/comments',             [ProjectController::class, 'storeComment']);
    Route::delete('projects/{project}/tasks/{task}/comments/{comment}', [ProjectController::class, 'destroyComment']);

    // ✅ Pastikan 2 baris ini ada di sini:
    Route::get('projects/{project}/tasks/{task}/column-assignees',  [ProjectController::class, 'getColumnAssignees']);
    Route::post('projects/{project}/tasks/{task}/column-assignees', [ProjectController::class, 'saveColumnAssignees']);

    // ── Master Locations
    Route::apiResource('master/locations', MasterLocationController::class)
         ->parameters(['locations' => 'location']);

    // ── Master Asset Categories
    Route::apiResource('master/asset-categories', MasterAssetCategoryController::class)
         ->parameters(['asset-categories' => 'assetCategory']);

    // Notifications
    Route::get('notifications', function () {
        return response()->json(['data' => [], 'total' => 0]);
    });

    // Projects — semua member bisa lihat
    Route::get('projects',           [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);

    // Projects — hanya manager_it & super_admin yang bisa kelola
    Route::middleware('role:super_admin,manager_it')->group(function () {
        Route::post('projects',                  [ProjectController::class, 'store']);
        Route::put('projects/{project}',         [ProjectController::class, 'update']);
        Route::delete('projects/{project}',      [ProjectController::class, 'destroy']);
        Route::put('projects/{project}/members', [ProjectController::class, 'syncMembers']);
    });

    Route::put('profile/password', [ProfileController::class, 'changePassword']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

    // Tasks
    Route::post('projects/{project}/tasks',          [ProjectController::class, 'storeTask']);
    Route::put('projects/{project}/tasks/reorder',   [ProjectController::class, 'reorderTasks']);
    Route::put('projects/{project}/tasks/{task}',    [ProjectController::class, 'updateTask']);
    Route::delete('projects/{project}/tasks/{task}', [ProjectController::class, 'destroyTask']);

    // Project attachments
    Route::post('projects/{project}/attachments',                        [ProjectController::class, 'uploadProjectAttachment']);
    Route::delete('projects/{project}/attachments/{attachment}',         [ProjectController::class, 'deleteProjectAttachment']);
    Route::post('projects/{project}/tasks/{task}/attachments',           [ProjectController::class, 'uploadAttachment']);
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [ProjectController::class, 'deleteAttachment']);

    // Roles — hanya super_admin
    Route::middleware('role:super_admin')->group(function () {
        Route::get('roles',                    [RoleController::class, 'index']);
        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    });

    Route::middleware('auth:sanctum')->group(function () {
    // ... existing routes ...

    


    
});

});