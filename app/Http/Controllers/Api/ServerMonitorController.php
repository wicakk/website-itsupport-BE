<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServerMonitor;
use App\Events\ServerMetricsUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServerMonitorController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = ServerMonitor::monitored()->get();

        $summary = [
            'online'      => $servers->where('status', 'Online')->count(),
            'warning'     => $servers->where('status', 'Warning')->count(),
            'down'        => $servers->where('status', 'Down')->count(),
            'maintenance' => $servers->where('status', 'Maintenance')->count(),
        ];

        return response()->json(['servers' => $servers, 'summary' => $summary]);
    }

    public function show(ServerMonitor $server): JsonResponse
    {
        return response()->json($server);
    }

    public function ping(Request $request, ServerMonitor $server): JsonResponse
    {
        // Update metrics
        $server->update([
            'cpu_usage'       => rand(5, 95),
            'ram_usage'       => rand(20, 90),
            'last_checked_at' => now(),
        ]);

        // Broadcast realtime ke semua client WebSocket
        broadcast(new ServerMetricsUpdated($server->fresh()));

        return response()->json([
            'message' => "Server {$server->name} berhasil di-ping.",
            'server'  => $server->fresh(),
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|unique:server_monitors,name',
            'ip_address' => 'required|ip',
            'port'       => 'required|integer|min:1|max:65535',
            'os'         => 'required|string',
        ]);

        $server = ServerMonitor::create([
            ...$validated,
            'status'       => 'Online',
            'is_monitored' => true,
        ]);

        return response()->json(['server' => $server], 201);
    }
    public function destroy(ServerMonitor $server): JsonResponse
    {
        $server->delete();
        return response()->json(['message' => "Server {$server->name} berhasil dihapus."]);
    }
}