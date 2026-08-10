<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Anggota;

/**
 * Migrasi DATA: isi statusPekerjaan anggota lama dari teks bebas `pekerjaan`
 * (heuristik statusKerjaDariPekerjaan). Idempotent — hanya baris yang masih null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Anggota::whereNull('statusPekerjaan')->chunkById(200, function ($rows) {
            foreach ($rows as $a) {
                $a->statusPekerjaan = statusKerjaDariPekerjaan($a->pekerjaan);
                $a->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Migrasi data — tidak dibalik.
    }
};
