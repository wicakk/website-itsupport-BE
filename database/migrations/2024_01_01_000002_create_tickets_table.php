<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // TKT-0001
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->enum('priority', ['Low','Medium','High','Critical']);
            $table->enum('status', [
                'Open','Assigned','In Progress','Waiting User','Resolved','Closed'
            ])->default('Open');

            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('department')->nullable();
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('resolution_time_minutes', 8, 2)->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->unsignedTinyInteger('satisfaction_rating')->nullable(); // 1-5

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index('sla_deadline');
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
