<?php

namespace App\Events;

use App\Models\ServerMonitor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ServerMetricsUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public ServerMonitor $server) {}

    public function broadcastOn(): Channel
    {
        return new Channel('monitoring');
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->server->id,
            'name'       => $this->server->name,
            'cpu_usage'  => $this->server->cpu_usage,
            'ram_usage'  => $this->server->ram_usage,
            'disk_usage' => $this->server->disk_usage,
            'status'     => $this->server->status,
            'updated_at' => $this->server->updated_at,
        ];
    }
}
