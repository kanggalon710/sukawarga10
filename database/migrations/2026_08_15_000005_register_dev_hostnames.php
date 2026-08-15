<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase B2 multi-tenant: daftarkan hostname pengembangan ke tabel `domains`.
 *
 * Resolver menolak hostname tak terdaftar dengan 404 TANPA fallback diam-diam
 * (aturan §14 dokumen arsitektur). Konsekuensinya `localhost` harus terdaftar
 * resmi, kalau tidak `php artisan serve` dan seluruh suite tes ikut tertolak.
 * Ini bukan celah: aplikasi di mesin mana pun memang milik tenant RW 10, dan
 * status `dev` mendokumentasikan niatnya.
 */
return new class extends Migration
{
    private const HOSTNAMES_DEV = ['localhost', '127.0.0.1'];

    public function up(): void
    {
        $rwId = DB::table('organizations')->where('slug', 'rw-10-sukakarya')->value('id');
        if (! $rwId) {
            return; // organisasi belum ada (mis. rollback parsial); tidak ada yang bisa didaftarkan
        }

        $now = now();
        foreach (self::HOSTNAMES_DEV as $hostname) {
            // insertOrIgnore: aman diulang dan tidak menimpa pemetaan yang
            // mungkin sudah diubah operator lewat tabelnya langsung.
            DB::table('domains')->insertOrIgnore([
                'organization_id' => $rwId, 'hostname' => $hostname,
                'is_primary' => false, 'status' => 'dev',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('domains')->whereIn('hostname', self::HOSTNAMES_DEV)->delete();
    }
};
