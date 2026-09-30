<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Rahasia MPWA keluar dari jalur setting yang bisa dibaca tenant (P0 2026-09-30).
 *
 * 1. `mpwa_api_key` dienkripsi di tempat (APP_KEY). AppSetting::semuaEfektif()
 *    tidak lagi memuatnya, jadi nilainya tidak pernah sampai ke view mana pun;
 *    hanya AppSetting::rahasia() di server yang membacanya.
 * 2. Baris `mpwa_api_url` dihapus: host gateway kini dari config
 *    (services.mpwa.url) dan wajib ada di allow-list, bukan pilihan tenant.
 *    Nilai lamanya tidak disimpan ulang karena justru itulah jalur SSRF-nya.
 *
 * Query langsung by-key di sini disengaja (migrasi data lintas tenant).
 * PENTING: APP_KEY yang diganti setelah ini membuat kunci tak terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $n = 0;
        foreach (DB::table('app_settings')->where('key', 'mpwa_api_key')->get(['id', 'value']) as $baris) {
            if ($baris->value === null || $baris->value === '' || $this->sudahTerenkripsi($baris->value)) {
                continue;
            }
            DB::table('app_settings')->where('id', $baris->id)
                ->update(['value' => Crypt::encryptString($baris->value)]);
            $n++;
        }
        $url = DB::table('app_settings')->where('key', 'mpwa_api_url')->delete();

        echo "  mpwa_api_key dienkripsi: {$n} baris; mpwa_api_url dihapus: {$url} baris.\n";
    }

    public function down(): void
    {
        foreach (DB::table('app_settings')->where('key', 'mpwa_api_key')->get(['id', 'value']) as $baris) {
            if ($baris->value && $this->sudahTerenkripsi($baris->value)) {
                DB::table('app_settings')->where('id', $baris->id)
                    ->update(['value' => Crypt::decryptString($baris->value)]);
            }
        }
        // mpwa_api_url tidak dipulihkan: nilai lama sengaja tidak disimpan.
        // Kode lama jatuh ke bawaan https://mpwa.jabnet.id bila kosong.
    }

    private function sudahTerenkripsi(string $nilai): bool
    {
        try {
            Crypt::decryptString($nilai);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
