<?php

namespace App\Models\Concerns;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope tenant (Phase E2): setiap query model ini otomatis dibatasi
 * ke organisasi RW tenant request, termasuk findOrFail - baris milik tenant
 * lain berujung 404, bukan bocor (§21: ketidaktebakan ID bukan otorisasi).
 *
 * Opt-in per model, BUKAN ditempel ke MilikOrganisasi, supaya tiap area
 * diaktifkan bersama tes isolasinya sendiri dan blast radius-nya terkendali.
 * Model ber-scope wajib punya kolom organization_id yang ter-backfill penuh:
 * baris ber-organization_id NULL tidak akan pernah tampil di request.
 *
 * Di konsol (importer, seeder, tinker) context tidak ditetapkan sehingga
 * scope tidak menyaring apa-apa - importer memang bekerja lintas isi tabel.
 *
 * PERHATIAN: scope Eloquent TIDAK berlaku untuk DB::table(). Query resource
 * tenant wajib lewat model (aturan di AGENTS.md).
 */
trait ScopedKeOrganisasi
{
    protected static function bootScopedKeOrganisasi(): void
    {
        static::addGlobalScope('organisasi', function (Builder $builder) {
            $context = app(TenantContext::class);
            if (! $context->sudahDitetapkan()) {
                return;
            }

            $rwId = $context->rw()?->id;
            if ($rwId === null) {
                // Host platform/desa (tanpa RW di rantainya) kini NYATA:
                // halaman mereka tidak menyentuh data tenant, jadi query
                // ber-scope harus FAIL-CLOSED - nol baris, bukan semua baris.
                // Agregat lintas tenant yang sah memakai withoutGlobalScope
                // eksplisit dengan komentar alasan (aturan AGENTS #9).
                $builder->whereRaw('1 = 0');

                return;
            }

            // Nama tabel dikualifikasi supaya tidak ambigu saat query di-join.
            $builder->where($builder->getModel()->getTable().'.organization_id', $rwId);
        });
    }
}
