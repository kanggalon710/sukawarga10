<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengikat akun warga ke KK-nya lewat kunci eksplisit.
 *
 * Sebelumnya ProfilWargaController mencocokkan `keluargas.nama` dengan
 * `users.namaLengkap`, sehingga dua warga bernama sama bisa saling membuka dan
 * menghapus anggota keluarga orang lain. Nama bukan identitas.
 *
 * Backfill hanya menautkan yang TIDAK ambigu (tepat satu kandidat). Akun yang
 * ambigu sengaja dibiarkan kosong: halaman profil akan menolak dengan pesan
 * "hubungi admin RT", dan itu lebih baik daripada menebak KK yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keluarga_id')->nullable()->after('rt');
            $table->index('keluarga_id');
        });

        $tertaut = 0;
        $ambigu = 0;

        foreach (DB::table('users')->where('level', 'warga')->get() as $u) {
            $kandidat = collect();

            // 1. Cocokkan lewat nomor WA ter-normalisasi (paling kuat)
            if (! empty($u->wa) && function_exists('normalizeWa')) {
                $wa = normalizeWa($u->wa);
                if ($wa) {
                    $kandidat = collect(DB::table('keluargas')->where('noHP', $wa)->pluck('keluarga_id'));
                }
            }

            // 2. Kalau belum ketemu, baru pakai nama persis
            if ($kandidat->isEmpty() && ! empty($u->namaLengkap)) {
                $kandidat = collect(DB::table('keluargas')->where('nama', $u->namaLengkap)->pluck('keluarga_id'));
            }

            if ($kandidat->unique()->count() === 1) {
                DB::table('users')->where('id', $u->id)->update(['keluarga_id' => $kandidat->first()]);
                $tertaut++;
            } elseif ($kandidat->isNotEmpty()) {
                $ambigu++;
            }
        }

        if ($tertaut || $ambigu) {
            echo "  Akun warga tertaut ke KK: {$tertaut}. Ambigu/dilewati: {$ambigu}.\n";
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['keluarga_id']);
            $table->dropColumn('keluarga_id');
        });
    }
};
