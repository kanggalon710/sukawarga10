<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Pindahkan dokumen warga warisan dari disk publik ke disk privat (P0 2026-09-30).
 *
 * Sengaja lewat migrasi, bukan langkah manual: pembaru satu-klik (Pembaruan
 * Sistem) menjalankan `migrate --force` tapi tidak perintah lain, dan scan
 * KK warisan tetap bisa diunduh publik dari /storage/dokumen sampai berkasnya
 * dipindah. Tidak ada kolom yang berubah (path relatif sama). Berkas yatim
 * TIDAK dihapus di sini; itu keputusan operator lewat dokumen:amankan
 * --hapus-yatim. Bentrok isi dilaporkan dan tidak menggagalkan migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('dokumen:amankan', ['--jalankan' => true]);
        echo Artisan::output();
    }

    public function down(): void
    {
        // Rollback kode lama menautkan /storage/<path>: berkas dikembalikan ke
        // disk publik supaya tautan lama tetap bekerja.
        Artisan::call('dokumen:amankan', ['--jalankan' => true, '--kembalikan' => true]);
        echo Artisan::output();
    }
};
