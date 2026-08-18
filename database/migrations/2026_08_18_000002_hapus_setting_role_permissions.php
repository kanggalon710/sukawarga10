<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Buang setting `role_permissions` yang sudah pensiun.
 *
 * Ia dulu matriks menu per level yang bisa diedit admin tenant, tapi tidak
 * pernah dibaca penjaga rute - murni kosmetik sidebar. Sejak matriks
 * kapabilitas, `userCan()` diturunkan dari MatriksKapabilitas dan baris ini
 * tidak dibaca siapa pun. Ditinggalkan, ia cuma jadi jebakan: orang berikutnya
 * bisa mengira mengubahnya berpengaruh pada hak akses.
 *
 * Query `where('key', ...)` polos di sini adalah PENGECUALIAN SADAR terhadap
 * aturan AGENTS.md (baca setting hanya lewat AppSetting::nilai/semuaEfektif).
 * Aturan itu ada supaya pembacaan menghormati pewarisan platform->desa->RW;
 * di sini justru sebaliknya - kita memang ingin menghapus baris milik SEMUA
 * organisasi sekaligus, jadi pewarisan tidak relevan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'role_permissions')->delete();
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan apa pun: nilainya sudah tidak punya
        // makna di sistem izin mana pun, dan menebak isinya lebih berbahaya
        // daripada membiarkannya kosong (bawaan kode yang berlaku).
    }
};
