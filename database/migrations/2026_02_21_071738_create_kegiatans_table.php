<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatans', function (Blueprint $table) {
            $table->id();
            $table->string('kegiatan_id')->unique();
            $table->string('judul');
            $table->date('tanggal');
            $table->string('waktu')->nullable();
            $table->string('tempat')->nullable();
            $table->string('jenis')->nullable(); // rapat, kerja bakti, sosial, dll
            $table->string('pic')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status')->default('direncanakan'); // direncanakan, selesai, dibatalkan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatans');
    }
};
