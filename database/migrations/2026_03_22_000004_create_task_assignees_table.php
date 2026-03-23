<?php
// database/migrations/2026_03_22_000001_create_task_assignees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        // Migrate data lama: salin assigned_to → task_assignees
        // agar task yang sudah ada tetap tampil assignee-nya
        DB::table('tasks')
            ->whereNotNull('assigned_to')
            ->get()
            ->each(function ($task) {
                DB::table('task_assignees')->insertOrIgnore([
                    'task_id'    => $task->id,
                    'user_id'    => $task->assigned_to,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
