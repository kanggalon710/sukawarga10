<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode pemulihan PIN sekali pakai (P0 2026-09-30).
 *
 * Menggantikan alur lama yang langsung menyimpan PIN baru lalu mengirimnya
 * lewat WA. Yang disimpan hanya sidik HMAC kode (terikat user_id), bukan
 * kodenya, dan tidak ada nomor WA di tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemulihan_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kode_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->timestamp('dipakai_pada')->nullable();
            $table->timestamps();

            // Pencarian verifikasi: kode aktif milik satu user.
            $table->index(['user_id', 'dipakai_pada', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemulihan_pins');
    }
};
