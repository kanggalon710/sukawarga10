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

    /**
     * Tahap approval -> kapabilitas yang boleh MEMAJUKANNYA.
     *
     * Sumber tunggal untuk controller DAN blade: sebelumnya daftar level ini
     * ditulis dua kali (SuratController::approve dan $needsMyAction di
     * layanan/surat.blade.php) sehingga bisa lepas sinkron. Nama tahapnya
     * sengaja tidak berubah supaya tidak perlu migrasi data.
     *
     * Tahap `cap_sekretaris` yang sempat dirujuk blade TIDAK pernah ada di
     * database: `ttd_rw -> selesai` memang tahap cap sekretaris (kolom
     * sek_signed_by/at diisi di situ).
     */
    public const KAPABILITAS_TAHAP = [
        'diajukan' => 'surat.ttdRt',
        'ttd_rt' => 'surat.ttdRw',
        'ttd_rw' => 'surat.finalisasi',
    ];

    /** Tahap yang sudah tidak bisa dimajukan maupun ditolak lagi. */
    public const TAHAP_AKHIR = ['selesai', 'ditolak'];

    protected $guarded = [];
}
