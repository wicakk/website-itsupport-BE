<?php

namespace Database\Seeders;

use App\Models\ServerMonitor;
use Illuminate\Database\Seeder;

class ServerMonitorSeeder extends Seeder
{
    public function run(): void
    {
        $servers = [
            ['name' => 'WEB-SERVER-01',  'ip_address' => '192.168.1.10', 'os' => 'Ubuntu 22.04',        'status' => 'Online',  'uptime' => '99.9%', 'cpu_usage' => 45, 'ram_usage' => 67, 'disk_usage' => 55],
            ['name' => 'DB-SERVER-01',   'ip_address' => '192.168.1.11', 'os' => 'CentOS 8',            'status' => 'Warning', 'uptime' => '99.7%', 'cpu_usage' => 78, 'ram_usage' => 82, 'disk_usage' => 73],
            ['name' => 'FILE-SERVER-01', 'ip_address' => '192.168.1.12', 'os' => 'Windows Server 2022', 'status' => 'Warning', 'uptime' => '98.2%', 'cpu_usage' => 23, 'ram_usage' => 45, 'disk_usage' => 91],
            ['name' => 'MAIL-SERVER-01', 'ip_address' => '192.168.1.13', 'os' => 'Ubuntu 20.04',        'status' => 'Online',  'uptime' => '99.9%', 'cpu_usage' => 56, 'ram_usage' => 61, 'disk_usage' => 40],
            ['name' => 'BACKUP-SERVER',  'ip_address' => '192.168.1.14', 'os' => 'Debian 11',           'status' => 'Warning', 'uptime' => '97.5%', 'cpu_usage' => 12, 'ram_usage' => 34, 'disk_usage' => 88],
        ];

        foreach ($servers as $data) {
            ServerMonitor::firstOrCreate(['name' => $data['name']], [
                ...$data,
                'last_checked_at' => now(),
                'is_monitored'    => true,
            ]);
        }

        $this->command->info('✅ Server monitors seeded (' . count($servers) . ' servers)');
    }
}
