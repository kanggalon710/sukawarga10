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
        Schema::create('iuran_sampahs', function (Blueprint $table) {
            $table->id();
            $table->string('keluarga_id');
            $table->integer('tahun');
            $table->json('weeks')->nullable(); // M1-M5
            $table->json('weekDates')->nullable(); // Tanggal bayar
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iuran_sampahs');
    }
};
