<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_column_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('column_id')
                  ->constrained('task_columns')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['task_id', 'column_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_column_assignees');
    }
};