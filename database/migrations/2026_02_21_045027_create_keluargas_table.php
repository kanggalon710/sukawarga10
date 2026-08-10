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
        Schema::create('keluargas', function (Blueprint $table) {
            $table->id();
            $table->string('keluarga_id')->unique(); // ID unik dari JS
            $table->string('nama');
            $table->string('noKK')->nullable();
            $table->string('nik')->nullable();
            $table->string('alamat');
            $table->string('rt');
            $table->string('rw')->default('10');
            $table->string('kelurahan')->default('Sukakarya');
            $table->string('kecamatan')->default('Tarogong Kidul');
            $table->string('noHP')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('penghasilan')->nullable();
            $table->string('statusRumah')->nullable();
            $table->integer('jumlahAnggota')->default(1);
            $table->string('status')->default('aktif');
            $table->json('tags')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('ikutSampah')->default(true);
            $table->boolean('ikutPadaringan')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keluargas');
    }
};
