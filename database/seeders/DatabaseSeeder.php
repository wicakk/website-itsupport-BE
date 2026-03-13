<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ServerMonitorSeeder::class,
            AssetSeeder::class,
            KnowledgeBaseSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
