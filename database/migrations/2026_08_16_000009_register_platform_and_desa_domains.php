<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domain per tingkat hirarki (halaman platform/desa/RW).
 *
 * `desa.jabnet.id` didaftarkan sebagai milik org PLATFORM - dulu ia baris
 * domain milik org RW 10, sehingga menghapus RW itu lewat Manajemen Desa
 * ikut menghapus domain root dan seluruh portal jadi 404 (insiden produksi
 * 2026-08-16). Domain platform tidak pernah ikut terhapus bersama tenant.
 *
 * Sekalian backfill `{slug}.desa.jabnet.id` untuk desa yang sudah ada;
 * desa baru mendapatkannya dari PembukaTenant.
 */
return new class extends Migration
{
    private const BASIS = 'desa.jabnet.id';

    public function up(): void
    {
        $now = now();

        $platformId = DB::table('organizations')->where('type', 'platform')->value('id');
        if ($platformId !== null) {
            DB::table('domains')->insertOrIgnore([
                'organization_id' => $platformId, 'hostname' => self::BASIS,
                'is_primary' => true, 'status' => 'aktif',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            echo '  Domain platform '.self::BASIS." terdaftar.\n";
        }

        $daftarDesa = DB::table('organizations')->where('type', 'desa')->get(['id', 'slug']);
        foreach ($daftarDesa as $desa) {
            DB::table('domains')->insertOrIgnore([
                'organization_id' => $desa->id, 'hostname' => "{$desa->slug}.".self::BASIS,
                'is_primary' => true, 'status' => 'aktif',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        echo '  '.$daftarDesa->count()." domain desa di-backfill.\n";
    }

    public function down(): void
    {
        DB::table('domains')->where('hostname', self::BASIS)->delete();
        DB::table('domains')
            ->where('hostname', 'like', '%.'.self::BASIS)
            ->whereIn('organization_id', DB::table('organizations')->where('type', 'desa')->pluck('id'))
            ->delete();
    }
};
