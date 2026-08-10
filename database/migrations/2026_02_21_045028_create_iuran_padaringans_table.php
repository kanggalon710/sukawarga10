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
        Schema::create('iuran_padaringans', function (Blueprint $table) {
            $table->id();
            $table->string('keluarga_id');
            $table->integer('tahun');
            $table->json('months')->nullable(); // JAN-DES
            $table->json('monthDates')->nullable(); // Tanggal bayar
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iuran_padaringans');
    }
};
