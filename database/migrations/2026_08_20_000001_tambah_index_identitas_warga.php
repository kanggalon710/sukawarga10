<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk kolom identitas warga (NIK & No. KK).
 *
 * Sampai sekarang tidak ada satu pun index di sana, padahal pemeriksa NIK
 * ganda menyentuh ketiganya di SETIAP penyimpanan data warga - termasuk
 * pencarian lintas tenant yang membaca seluruh `keluargas` semua desa
 * sekaligus. Tanpa index itu full scan, dan biayanya tumbuh tiap desa baru.
 *
 * Sengaja BUKAN unique: data warisan memuat NIK kosong, duplikat, dan sisa
 * notasi ilmiah dari Google Sheets, jadi unique tidak akan pernah bisa
 * dipasang tanpa membersihkan data 3 desa lebih dulu. Keunikan ditegakkan di
 * lapisan aplikasi lewat PemeriksaNikWarga; batasannya dicatat di DECISIONS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->index('nik', 'keluargas_nik_idx');
            $table->index('noKK', 'keluargas_nokk_idx');
        });

        Schema::table('anggotas', function (Blueprint $table) {
            $table->index('nik', 'anggotas_nik_idx');
        });
    }

    public function down(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->dropIndex('keluargas_nik_idx');
            $table->dropIndex('keluargas_nokk_idx');
        });

        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropIndex('anggotas_nik_idx');
        });
    }
};
