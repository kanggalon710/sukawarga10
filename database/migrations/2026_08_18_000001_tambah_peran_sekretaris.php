<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran Sekretaris RW (matriks kapabilitas).
 *
 * Sebelumnya `sekretaris` cuma "hantu": dirujuk satu baris mati di
 * SuratController tapi tidak ada di katalog peran maupun hierarki, sehingga
 * tahap cap surat terpaksa dikerjakan superadmin.
 *
 * Katalog peran memang di-seed oleh migrasi (lihat 2026_08_15_000007), bukan
 * database/seeders - pola itu diikuti supaya katalog ikut `migrate` di
 * produksi. `legacy_level` 'sekretaris' unik, jadi tidak menambah ambiguitas
 * seperti 'ketua_rw' yang dipegang desa_admin dan rw_admin sekaligus.
 */
return new class extends Migration
{
    private const SLUG = 'rw_secretary';

    public function up(): void
    {
        // insertOrIgnore: `roles.slug` unique, jadi menjalankan ulang migrasi
        // di portal yang sudah punya barisnya tidak meledak.
        DB::table('roles')->insertOrIgnore([
            'slug' => self::SLUG,
            'name' => 'Sekretaris RW',
            'scope_type' => 'rw',
            'legacy_level' => 'sekretaris',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', self::SLUG)->value('id');
        if ($roleId === null) {
            return;
        }

        // Tidak ada foreign key cascade di user_role_assignments (lihat migrasi
        // 2026_08_15_000007), jadi assignment-nya dibersihkan manual supaya
        // tidak meninggalkan baris yatim yang menunjuk peran hilang.
        DB::table('user_role_assignments')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
