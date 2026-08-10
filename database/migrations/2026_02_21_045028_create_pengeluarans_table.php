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
        Schema::create('pengeluarans', function (Blueprint $table) {
            $table->id();
            $table->string('pengeluaran_id')->unique();
            $table->date('tanggal');
            $table->string('kategori'); // kas mana
            $table->string('jenis');
            $table->string('keterangan')->nullable();
            $table->integer('jumlah')->default(0);
            $table->string('penerima')->nullable();
            $table->boolean('verifikasi')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengeluarans');
    }
};
