<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServerMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServerMonitorController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = ServerMonitor::monitored()->get();

        // Summary counts
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

    /**
     * POST /api/monitoring/{server}/ping
     * Trigger manual metric refresh (simulasi — production: ganti dengan SNMP/Prometheus)
     */
    public function ping(Request $request, ServerMonitor $server): JsonResponse
    {
        // Simulate metric update (in production: fetch from real monitoring agent)
        $server->update([
            'cpu_usage'       => rand(5, 95),
            'ram_usage'       => rand(20, 90),
            'last_checked_at' => now(),
        ]);

        return response()->json([
            'message' => "Server {$server->name} berhasil di-ping.",
            'server'  => $server->fresh(),
        ]);
    }
}
