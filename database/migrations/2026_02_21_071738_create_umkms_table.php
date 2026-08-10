<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umkms', function (Blueprint $table) {
            $table->id();
            $table->string('umkm_id')->unique();
            $table->string('pemilik');
            $table->string('rt')->nullable();
            $table->string('namaUsaha');
            $table->string('jenis')->nullable(); // makanan, jasa, kerajinan, dll
            $table->text('deskripsi')->nullable();
            $table->string('noHP')->nullable();
            $table->string('status')->default('aktif'); // aktif, musiman, nonaktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umkms');
    }
};
