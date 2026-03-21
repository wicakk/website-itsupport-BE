<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_hardware_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();

            $table->string('nama_aset')->nullable();
            $table->string('kategori')->nullable();     // Laptop, Desktop, Printer, dll
            $table->string('status')->nullable();       // Active, Inactive, Under Repair, Disposed
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('pengguna')->nullable();
            $table->date('tgl_beli')->nullable();
            $table->unsignedBigInteger('harga_beli')->nullable();
            $table->date('garansi_sd')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_hardware_assets');
    }
};
