<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use App\Models\Concerns\ScopedKeOrganisasi;
use Illuminate\Database\Eloquent\Model;

class Surat extends Model
{
    // ScopedKeOrganisasi: pembacaan otomatis dibatasi tenant request (Phase E2),
    // dijaga tests/Feature/IsolasiTenantTest.php.
    use MilikOrganisasi, ScopedKeOrganisasi;

    /**
     * Sumber tunggal daftar kode surat. Daftar yang sama dipakai select form
     * (layanan/surat.blade.php) dan $judulMap halaman cetak.
     */
    public const KODE_VALID = [
        'SKD', 'SKTM', 'SKP', 'SKU', 'SKCK', 'SKK', 'SKL',
        'SKN', 'SKBB', 'SKI', 'SKKB', 'SPB', 'LAIN',
    ];

    protected $guarded = [];
}
