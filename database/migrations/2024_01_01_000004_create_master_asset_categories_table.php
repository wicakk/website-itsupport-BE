<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // cth: Laptop, Desktop, Printer
            $table->string('icon')->nullable();  // opsional, nama icon
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_categories');
    }
};
