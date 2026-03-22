<?php
// database/migrations/2026_03_22_000003_add_revisi_column_to_existing_projects.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projects = DB::table('projects')->whereNull('deleted_at')->get();

        foreach ($projects as $project) {
            $hasRevisi = DB::table('task_columns')
                ->where('project_id', $project->id)
                ->where('name', 'Revisi')
                ->exists();

            $prodCol = DB::table('task_columns')
                ->where('project_id', $project->id)
                ->where('name', 'Prod')
                ->first();

            if (!$hasRevisi && $prodCol) {
                // Geser Prod ke position 6
                DB::table('task_columns')
                    ->where('id', $prodCol->id)
                    ->update(['position' => 6, 'updated_at' => now()]);

                // Sisipkan Revisi di position 5 (antara UAT dan Prod)
                DB::table('task_columns')->insert([
                    'project_id' => $project->id,
                    'name'       => 'Revisi',
                    'color'      => '#F97316', // orange
                    'position'   => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('task_columns')->where('name', 'Revisi')->delete();

        // Kembalikan Prod ke position 5
        DB::table('task_columns')
            ->where('name', 'Prod')
            ->update(['position' => 5, 'updated_at' => now()]);
    }
};
