<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk kolom yang dipakai menyaring, menggabung, dan mengurutkan.
 *
 * Sampai sekarang migrasi hanya memberi unique() pada kunci bisnis (*_id),
 * padahal jalur terpanas (Dashboard, Laporan, Billing) menyaring lewat
 * status/rt/tanggal/kas. Di ~101 KK memang belum terasa, tapi audit_logs dan
 * transaksis tumbuh terus.
 *
 * Nama index ditulis eksplisit dan pendek supaya aman di MySQL maupun SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->index(['status', 'rt'], 'keluargas_status_rt_idx');
            $table->index('ikutSampah', 'keluargas_ikut_sampah_idx');
            $table->index('ikutPadaringan', 'keluargas_ikut_padaringan_idx');
        });

        Schema::table('anggotas', function (Blueprint $table) {
            $table->index('keluarga_id', 'anggotas_keluarga_idx');
        });

        Schema::table('transaksis', function (Blueprint $table) {
            $table->index(['kas', 'jenis', 'voided'], 'transaksis_kas_jenis_void_idx');
            $table->index('tanggal', 'transaksis_tanggal_idx');
            $table->index('refKeluargaId', 'transaksis_ref_keluarga_idx');
        });

        Schema::table('iuran_sampahs', function (Blueprint $table) {
            $table->index(['keluarga_id', 'tahun'], 'iuran_sampahs_kk_tahun_idx');
        });

        Schema::table('iuran_padaringans', function (Blueprint $table) {
            $table->index(['keluarga_id', 'tahun'], 'iuran_padaringans_kk_tahun_idx');
        });

        Schema::table('surats', function (Blueprint $table) {
            $table->index('user_id', 'surats_user_idx');
            $table->index(['tahun', 'status'], 'surats_tahun_status_idx');
        });

        Schema::table('aduans', function (Blueprint $table) {
            $table->index('user_id', 'aduans_user_idx');
            $table->index('status', 'aduans_status_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('tanggal', 'audit_logs_tanggal_idx');
        });

        Schema::table('pendaftarans', function (Blueprint $table) {
            $table->index('status', 'pendaftarans_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->dropIndex('keluargas_status_rt_idx');
            $table->dropIndex('keluargas_ikut_sampah_idx');
            $table->dropIndex('keluargas_ikut_padaringan_idx');
        });
        Schema::table('anggotas', fn (Blueprint $t) => $t->dropIndex('anggotas_keluarga_idx'));
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropIndex('transaksis_kas_jenis_void_idx');
            $table->dropIndex('transaksis_tanggal_idx');
            $table->dropIndex('transaksis_ref_keluarga_idx');
        });
        Schema::table('iuran_sampahs', fn (Blueprint $t) => $t->dropIndex('iuran_sampahs_kk_tahun_idx'));
        Schema::table('iuran_padaringans', fn (Blueprint $t) => $t->dropIndex('iuran_padaringans_kk_tahun_idx'));
        Schema::table('surats', function (Blueprint $table) {
            $table->dropIndex('surats_user_idx');
            $table->dropIndex('surats_tahun_status_idx');
        });
        Schema::table('aduans', function (Blueprint $table) {
            $table->dropIndex('aduans_user_idx');
            $table->dropIndex('aduans_status_idx');
        });
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropIndex('audit_logs_tanggal_idx'));
        Schema::table('pendaftarans', fn (Blueprint $t) => $t->dropIndex('pendaftarans_status_idx'));
    }
};
