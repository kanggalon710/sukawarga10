<?php

namespace App\Models;

use App\Models\Concerns\ScopedKeOrganisasiViaKeluarga;
use Illuminate\Database\Eloquent\Model;

class IuranSampah extends Model
{
    // Scope tenant turunan lewat keluarga_id (Phase E2) - model ini tidak punya
    // kolom organization_id sendiri. Dijaga tests/Feature/IsolasiTenantTest.php.
    use ScopedKeOrganisasiViaKeluarga;

    /**
     * Kolom keluarga_id tabel ini berisi id NUMERIK keluargas.id, bukan ID
     * bisnis string - alur bayar menyimpan parameter rute apa adanya
     * (TransaksiController). Inkonsistensi warisan, tercatat di TODO.
     */
    protected static function kolomRujukanKeluarga(): string
    {
        return 'id';
    }

    protected $guarded = [];
    protected $casts = ['weeks' => 'array', 'weekDates' => 'array'];

    //
}
