<?php

namespace App\Jobs;

use App\Events\ServerMetricsUpdated;
use App\Models\ServerMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class PollServerMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $servers = ServerMonitor::where('is_monitored', true)->get();

        foreach ($servers as $server) {
            try {
                $res = Http::timeout(3)->get(
                    "http://{$server->ip_address}:{$server->port}/metrics"
                );

                if ($res->ok()) {
                    $d = $res->json();
                    $server->update([
                        'cpu_usage'       => round($d['cpu']['percent']     ?? $d['cpu']  ?? 0),
                        'ram_usage'       => round($d['memory']['percent']  ?? $d['ram']  ?? 0),
                        'disk_usage'      => round($d['storage']['percent'] ?? $d['disk'] ?? 0),
                        'uptime'          => $d['uptime'] ?? null,
                        'status'          => $this->calcStatus(
                            $d['cpu']['percent']     ?? 0,
                            $d['memory']['percent']  ?? 0,
                            $d['storage']['percent'] ?? 0
                        ),
                        'last_checked_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                $server->update(['last_checked_at' => now()]);
            }

            broadcast(new ServerMetricsUpdated($server->fresh()));
        }
    }

    private function calcStatus(float $cpu, float $ram, float $disk): string
    {
        if ($cpu > 90 || $ram > 90 || $disk > 95) return 'Down';
        if ($cpu > 70 || $ram > 75 || $disk > 85) return 'Warning';
        return 'Online';
    }
}