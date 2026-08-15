<?php

namespace App\Models;

use App\Models\Concerns\ScopedKeOrganisasiViaKeluarga;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    // Scope tenant turunan lewat keluarga_id (Phase E2) - model ini tidak punya
    // kolom organization_id sendiri. Dijaga tests/Feature/IsolasiTenantTest.php.
    use ScopedKeOrganisasiViaKeluarga;

    protected $guarded = [];

    protected $casts = [
        'tanggalLahir' => 'date',
    ];

    /**
     * Keluarga (KK) yang menaungi anggota ini.
     */
    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'keluarga_id', 'keluarga_id');
    }
}
