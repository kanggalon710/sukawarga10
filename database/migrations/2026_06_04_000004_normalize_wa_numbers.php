<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\Keluarga;
use App\Models\User;

/**
 * Migrasi DATA: normalisasi semua nomor WA lama ke format kanonik 62xxxx
 * (keluargas.noHP & users.wa) memakai helper normalizeWa(). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Keluarga::whereNotNull('noHP')->where('noHP', '!=', '')->chunkById(200, function ($rows) {
            foreach ($rows as $k) {
                $n = normalizeWa($k->noHP);
                if ($n && $n !== $k->noHP) { $k->noHP = $n; $k->saveQuietly(); }
            }
        });

        if (Schema::hasColumn('users', 'wa')) {
            User::whereNotNull('wa')->where('wa', '!=', '')->chunkById(200, function ($rows) {
                foreach ($rows as $u) {
                    $n = normalizeWa($u->wa);
                    if ($n && $n !== $u->wa) { $u->wa = $n; $u->saveQuietly(); }
                }
            });
        }
    }

    public function down(): void
    {
        // Tidak dibalik (data).
    }
};
