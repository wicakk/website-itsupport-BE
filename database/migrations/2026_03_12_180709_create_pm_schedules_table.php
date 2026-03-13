<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('interval', ['Mingguan','Bulanan','3 Bulan','6 Bulan','Tahunan']);
            $table->date('next_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['Pending','Selesai'])->default('Pending');
            $table->date('last_done')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_schedules');
    }
};
