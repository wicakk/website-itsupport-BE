<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMonitor extends Model
{
    protected $fillable = [
        'name', 'ip_address', 'port', 'hostname', 'os', 'status',
        'uptime', 'cpu_usage', 'ram_usage', 'disk_usage',
        'last_checked_at', 'is_monitored',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'is_monitored'    => 'boolean',
    ];

    public function scopeMonitored($q) { return $q->where('is_monitored', true); }

    public function getStatusColorAttribute(): string {
        return match($this->status) {
            'Online'      => '#10B981',
            'Warning'     => '#F59E0B',
            'Down'        => '#EF4444',
            'Maintenance' => '#6B7FA3',
            default       => '#6B7FA3',
        };
    }
}
