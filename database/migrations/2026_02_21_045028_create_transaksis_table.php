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
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('transaksi_id')->unique(); // ID asli
            $table->date('tanggal');
            $table->string('jenis'); // masuk/keluar
            $table->string('kas'); // sampah, padaringan, umum
            $table->string('kategori')->nullable();
            $table->string('keterangan')->nullable();
            $table->integer('jumlah')->default(0);
            $table->string('refKeluargaId')->nullable();
            $table->string('refNo')->nullable();
            $table->string('kwitansiNo')->nullable();
            $table->string('operator')->nullable();
            $table->boolean('voided')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
