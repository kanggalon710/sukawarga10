<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan periode iuran yang dibayar sebagai data terstruktur.
 *
 * Sebelumnya pembatalan (void) menyimpulkan periode mana yang harus dibuka lagi
 * dengan mem-parsing kalimat bahasa Indonesia di kolom `keterangan`. Untuk
 * padaringan caranya cuma `str_contains` kode bulan, sehingga nama warga yang
 * memuat "MEI" atau "AGU" ikut cocok dan periode yang salah ikut dibatalkan.
 *
 * Kolom ini nullable: baris lama tetap memakai jalur parsing sebagai fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->json('periode')->nullable()->after('refKeluargaId');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn('periode');
        });
    }
};
