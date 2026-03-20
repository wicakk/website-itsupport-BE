<?php
// database/migrations/2026_03_20_200000_fix_resolution_time_minutes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: pakai EXTRACT(EPOCH FROM ...) / 60 untuk menghitung menit
        DB::statement("
            UPDATE tickets
            SET resolution_time_minutes = EXTRACT(EPOCH FROM (resolved_at - created_at))::INTEGER / 60
            WHERE status IN ('Resolved', 'Closed')
              AND resolved_at IS NOT NULL
              AND (resolution_time_minutes IS NULL OR resolution_time_minutes = 0)
        ");

        DB::statement("
            UPDATE tickets
            SET resolved_at = updated_at,
                resolution_time_minutes = EXTRACT(EPOCH FROM (updated_at - created_at))::INTEGER / 60
            WHERE status IN ('Resolved', 'Closed')
              AND resolved_at IS NULL
              AND (resolution_time_minutes IS NULL OR resolution_time_minutes = 0)
        ");
    }

    public function down(): void
    {
        // tidak perlu rollback data
    }
};
