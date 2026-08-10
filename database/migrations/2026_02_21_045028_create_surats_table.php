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
        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->string('surat_id')->unique();
            $table->string('kodeSurat')->nullable();
            $table->integer('tahun')->nullable();
            $table->integer('nomorUrut')->nullable();
            $table->string('nomorSurat')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('pemohon')->nullable();
            $table->string('keperluan')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surats');
    }
};
