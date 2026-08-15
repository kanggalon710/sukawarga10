<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F multi-tenant: app_settings ber-scope organisasi (§16-§17).
 *
 * organization_id NULL = default level platform yang diwarisi semua tenant;
 * baris ber-organisasi menimpanya (inheritance dieksekusi di
 * AppSetting::semuaEfektif, bukan di SQL).
 *
 * Backfill: SEMUA baris existing menjadi milik RW 10, karena memang diisi
 * pengurus RW 10 (tarif, identitas, kredensial MPWA). Kalau dibiarkan NULL,
 * tenant kedua akan MEWARISI kunci API WhatsApp dan identitas RW 10 - bocor.
 *
 * Catatan MySQL: unique komposit dengan kolom nullable mengizinkan beberapa
 * baris NULL untuk key yang sama. Keunikan default platform dijaga jalur
 * tulis (AppSetting::simpan memakai updateOrCreate), bukan constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable();
        });

        $rwId = DB::table('organizations')->where('slug', 'rw-10-sukakarya')->value('id');
        if ($rwId) {
            $n = DB::table('app_settings')->whereNull('organization_id')
                ->update(['organization_id' => $rwId]);
            echo "  app_settings: {$n} baris ditautkan ke RW 10.\n";
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['organization_id', 'key'], 'app_settings_org_key_unik');
        });
    }

    public function down(): void
    {
        // Baris duplikat antar organisasi harus dilenyapkan sebelum unique(key)
        // bisa kembali; pertahankan baris tenant pertama (id terkecil) per key.
        $duplikat = DB::table('app_settings as a')
            ->join('app_settings as b', function ($join) {
                $join->on('a.key', '=', 'b.key')->whereColumn('a.id', '>', 'b.id');
            })->pluck('a.id');
        if ($duplikat->isNotEmpty()) {
            DB::table('app_settings')->whereIn('id', $duplikat)->delete();
            echo '  app_settings: '.$duplikat->count()." baris duplikat lintas organisasi dihapus.\n";
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique('app_settings_org_key_unik');
            $table->unique('key');
            $table->dropColumn('organization_id');
        });
    }
};
