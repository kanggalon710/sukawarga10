<?php

namespace App\Models\Concerns;

use App\Models\Keluarga;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope tenant TURUNAN (Phase E2): untuk model tanpa kolom organization_id
 * yang kepemilikannya lewat `keluarga_id` (anggota, iuran). Disaring dengan
 * subquery ke keluargas - dan karena Keluarga sendiri ber-ScopedKeOrganisasi,
 * subquery itu otomatis ikut tersaring ke tenant request. Satu query, bukan
 * whereHas per baris.
 *
 * Di konsol context kosong: tidak menyaring, sama seperti scope utamanya -
 * penting karena importer menghapus/mengisi anggota lintas isi tabel.
 */
trait ScopedKeOrganisasiViaKeluarga
{
    protected static function bootScopedKeOrganisasiViaKeluarga(): void
    {
        static::addGlobalScope('organisasi', function (Builder $builder) {
            $context = app(TenantContext::class);
            if (! $context->sudahDitetapkan() || $context->rw() === null) {
                return;
            }

            $builder->whereIn(
                $builder->getModel()->getTable().'.keluarga_id',
                Keluarga::query()->select(static::kolomRujukanKeluarga())
            );
        });
    }

    /**
     * Kolom di tabel keluargas yang dirujuk `keluarga_id` model ini.
     * Warisan yang harus disadari: `anggotas.keluarga_id` berisi ID bisnis
     * string (kk_...), sedangkan `iuran_*.keluarga_id` berisi id NUMERIK
     * keluargas.id (alur bayar menyimpan parameter rute apa adanya) -
     * model iuran meng-override ini ke 'id'.
     */
    protected static function kolomRujukanKeluarga(): string
    {
        return 'keluarga_id';
    }
}
