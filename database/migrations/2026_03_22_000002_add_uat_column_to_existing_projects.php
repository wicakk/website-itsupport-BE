<?php
// database/migrations/2026_03_22_000002_add_uat_column_to_existing_projects.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ambil semua project yang punya kolom Prod tapi belum punya UAT
        $projects = DB::table('projects')->whereNull('deleted_at')->get();

        foreach ($projects as $project) {
            $hasUAT = DB::table('task_columns')
                ->where('project_id', $project->id)
                ->where('name', 'UAT')
                ->exists();

            $prodCol = DB::table('task_columns')
                ->where('project_id', $project->id)
                ->where('name', 'Prod')
                ->first();

            if (!$hasUAT && $prodCol) {
                // Geser Prod ke position 5
                DB::table('task_columns')
                    ->where('id', $prodCol->id)
                    ->update(['position' => 5, 'updated_at' => now()]);

                // Sisipkan UAT di position 4 (sebelum Prod)
                DB::table('task_columns')->insert([
                    'project_id' => $project->id,
                    'name'       => 'UAT',
                    'color'      => '#06B6D4',
                    'position'   => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Hapus semua kolom UAT yang ditambahkan
        DB::table('task_columns')->where('name', 'UAT')->delete();

        // Kembalikan Prod ke position 4
        DB::table('task_columns')
            ->where('name', 'Prod')
            ->update(['position' => 4, 'updated_at' => now()]);
    }
};
