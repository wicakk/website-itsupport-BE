<?php
// database/seeders/TicketCategorySeeder.php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Hardware',  'color' => '#F59E0B', 'description' => 'Masalah perangkat keras komputer, printer, dll', 'order' => 0],
            ['name' => 'Software',  'color' => '#6366f1', 'description' => 'Masalah aplikasi, sistem operasi, lisensi', 'order' => 1],
            ['name' => 'Network',   'color' => '#10B981', 'description' => 'Masalah jaringan, internet, VPN, WiFi', 'order' => 2],
            ['name' => 'Email',     'color' => '#3B82F6', 'description' => 'Masalah email, outlook, akun email', 'order' => 3],
            ['name' => 'Printer',   'color' => '#8B5CF6', 'description' => 'Masalah printer dan perangkat cetak', 'order' => 4],
            ['name' => 'Server',    'color' => '#EF4444', 'description' => 'Masalah server, hosting, database server', 'order' => 5],
            ['name' => 'Security',  'color' => '#EC4899', 'description' => 'Masalah keamanan, akses, password, virus', 'order' => 6],
            ['name' => 'Others',    'color' => '#94A3B8', 'description' => 'Kategori lainnya yang tidak termasuk di atas', 'order' => 7],
        ];

        foreach ($categories as $cat) {
            TicketCategory::firstOrCreate(
                ['name' => $cat['name']],
                array_merge($cat, ['is_active' => true])
            );
        }

        $this->command->info('✅ Ticket categories seeded!');
    }
}
