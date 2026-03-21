<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Nama lokasi, cth: Ruang IT Lt. 2
            $table->string('code')->nullable()->unique();  // Kode singkat, cth: RIT-2
            $table->string('building')->nullable();        // Gedung / Lantai
            $table->text('description')->nullable();       // Keterangan tambahan
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_locations');
    }
};
