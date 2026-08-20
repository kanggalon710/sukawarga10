<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pendaftarans.no_kk` dibuat NOT NULL, padahal WebAuthController::registerWarga
 * memvalidasinya sebagai `nullable` dan formulir pendaftaran memang tidak
 * mewajibkannya.
 *
 * Akibatnya warga yang mendaftar tanpa mengisi No. KK - yang justru lazim, karena
 * kartu keluarga tidak selalu ada di tangan saat mendaftar - mendapat halaman
 * error 500, bukan pesan yang bisa dimengerti. Ketahuan 2026-08-20 saat menulis
 * tes pendaftaran, bukan dari laporan pengguna.
 *
 * Kolomnya yang mengalah, bukan validasinya: mewajibkan No. KK berarti menutup
 * pendaftaran bagi warga yang belum memegang kartunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftarans', function (Blueprint $table) {
            $table->string('no_kk', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris lama yang no_kk-nya null harus diisi dulu, kalau tidak ALTER gagal.
        \Illuminate\Support\Facades\DB::table('pendaftarans')->whereNull('no_kk')->update(['no_kk' => '']);

        Schema::table('pendaftarans', function (Blueprint $table) {
            $table->string('no_kk', 16)->nullable(false)->change();
        });
    }
};
