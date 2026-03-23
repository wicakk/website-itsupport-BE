<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Case 1: resolved_at sudah ada
        DB::statement("
            UPDATE tickets
            SET resolution_time_minutes = TIMESTAMPDIFF(MINUTE, created_at, resolved_at)
            WHERE status IN ('Resolved', 'Closed')
              AND resolved_at IS NOT NULL
              AND (resolution_time_minutes IS NULL OR resolution_time_minutes = 0)
        ");

        // Case 2: resolved_at NULL → pakai updated_at
        DB::statement("
            UPDATE tickets
            SET resolved_at = updated_at,
                resolution_time_minutes = TIMESTAMPDIFF(MINUTE, created_at, updated_at)
            WHERE status IN ('Resolved', 'Closed')
              AND resolved_at IS NULL
              AND (resolution_time_minutes IS NULL OR resolution_time_minutes = 0)
        ");
    }

    public function down(): void
    {
        // optional: kosongin lagi kalau mau rollback
        DB::statement("
            UPDATE tickets
            SET resolution_time_minutes = NULL
        ");
    }
};