<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use App\Models\Concerns\ScopedKeOrganisasi;
use Illuminate\Database\Eloquent\Model;

class Keluarga extends Model
{
    // ScopedKeOrganisasi: seluruh pembacaan otomatis dibatasi tenant request
    // (Phase E2, jalur uang) - dijaga tests/Feature/IsolasiTenantTest.php.
    use MilikOrganisasi, ScopedKeOrganisasi;

    protected $guarded = [];
    protected $casts = [
        'tags' => 'array',
        'aset' => 'array',
        'bansos' => 'array',
        'kelompokRentan' => 'array',
        'kesehatan' => 'array',
        'ikutSampah' => 'boolean',
        'ikutPadaringan' => 'boolean',
        'punyaTabungan' => 'boolean',
        'punyaHutangUsaha' => 'boolean',
        'tanggalLahirKK' => 'date',
    ];

    /**
     * Anggota keluarga di bawah KK ini.
     */
    public function anggota()
    {
        return $this->hasMany(Anggota::class, 'keluarga_id', 'keluarga_id');
    }

    /**
     * Total jiwa = 1 (kepala) + anggota
     */
    public function getTotalJiwaAttribute(): int
    {
        return 1 + $this->anggota()->count();
    }

    /**
     * Umur KK (otomatis dari tanggal lahir)
     */
    public function getUmurKKAttribute(): ?int
    {
        return $this->tanggalLahirKK ? $this->tanggalLahirKK->age : null;
    }
}

