<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Ticket Comments ──────────────────────────────────────────────────
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // catatan internal IT
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Ticket Attachments ───────────────────────────────────────────────
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('ticket_comments')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->string('path');
            $table->timestamps();
        });

        // ─── Assets ───────────────────────────────────────────────────────────
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->unique(); // AST-001
            $table->string('name');
            $table->enum('category', ['Laptop','Desktop','Printer','Network','Server','Phone','Monitor','Others']);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->unique()->nullable();
            $table->enum('status', ['Active','Maintenance','Inactive','Disposed'])->default('Active');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->json('specs')->nullable(); // RAM, CPU, storage, dsb
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Knowledge Base ───────────────────────────────────────────────────
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->string('category');
            $table->json('tags')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->decimal('rating', 3, 2)->default(0); // avg
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Server Monitors ──────────────────────────────────────────────────
        Schema::create('server_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('ip_address', 45);
            $table->string('hostname')->nullable();
            $table->string('os')->nullable();
            $table->enum('status', ['Online','Warning','Down','Maintenance'])->default('Online');
            $table->string('uptime')->nullable(); // "99.9%"
            $table->unsignedTinyInteger('cpu_usage')->default(0);
            $table->unsignedTinyInteger('ram_usage')->default(0);
            $table->unsignedTinyInteger('disk_usage')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_monitored')->default(true);
            $table->integer('port')->default(9090);
            $table->timestamps();
        });

        // ─── Ticket Categories ─────────────────────────────────────────────────
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#6366f1');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_monitors');
        Schema::dropIfExists('knowledge_base');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_categories');
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropForeign(['comment_id']);
            $table->dropColumn('comment_id');
        });
    }
};
