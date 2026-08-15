<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C multi-tenant: kolom kepemilikan organisasi pada tabel tenant
 * (lihat .ai/AUDIT-MULTITENANT.md bagian 6 & 10).
 *
 * Additive: kolom nullable + index, lalu backfill seluruh baris existing ke
 * RW 10 - pasti benar karena deployment ini single-tenant sejak awal.
 * `anggotas` dan `iuran_*` SENGAJA tidak diberi kolom: scope-nya diturunkan
 * lewat `keluarga_id` (aturan §20: jangan FK redundan).
 *
 * Tanpa FK constraint di level database: SQLite (lingkungan tes) tidak bisa
 * menambahkan FK ke tabel yang sudah ada, dan constraint yang hanya hidup di
 * MySQL berarti perilaku dev dan produksi berbeda. Integritas dijaga trait
 * MilikOrganisasi + tes, mengikuti preseden `users.keluarga_id`.
 */
return new class extends Migration
{
    private const TABEL = [
        'keluargas', 'users', 'transaksis', 'surats', 'aduans', 'umkms',
        'kegiatans', 'pengeluarans', 'sumbangans', 'setor_sampahs',
        'pendaftarans', 'audit_logs',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->unsignedBigInteger('organization_id')->nullable();
                // Nama index eksplisit dan pendek, mengikuti preseden migrasi
                // ..._add_indexes_to_hot_columns (batas 64 karakter MySQL).
                $table->index('organization_id', "{$tabel}_org_idx");
            });
        }

        $rwId = DB::table('organizations')->where('slug', 'rw-10-sukakarya')->value('id');
        if (! $rwId) {
            echo "  PERINGATAN: organisasi RW 10 tidak ditemukan, backfill dilewati.\n";

            return;
        }

        foreach (self::TABEL as $tabel) {
            $n = DB::table($tabel)->whereNull('organization_id')
                ->update(['organization_id' => $rwId]);
            echo "  {$tabel}: {$n} baris ditautkan ke RW 10.\n";
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->dropIndex("{$tabel}_org_idx");
                $table->dropColumn('organization_id');
            });
        }
    }
};
