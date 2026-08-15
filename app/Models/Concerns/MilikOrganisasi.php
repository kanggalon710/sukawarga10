<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cap kepemilikan organisasi pada baris baru (Phase C multi-tenant).
 *
 * Aturan pengisian `organization_id` saat create:
 *
 * 1. Context request ada (HTTP lewat ResolveTenant) → SELALU dicap dari
 *    context, MENIMPA nilai kiriman apa pun. Sebagian besar model tenant
 *    memakai `$guarded = []`, jadi tanpa penimpaan ini organization_id bisa
 *    disuntik dari form (client tidak pernah dipercaya menentukan tenant).
 * 2. Tanpa context (konsol: importer, seeder, tinker) → nilai yang sudah
 *    di-set dipertahankan; kalau kosong dan tepat SATU organisasi RW aktif
 *    ada, pakai itu (deterministik di era single-tenant). Lebih dari satu RW
 *    → dibiarkan null; aturan ini sengaja berhenti menebak begitu platform
 *    benar-benar multi-tenant, dan pemanggil konsol wajib menyebut org
 *    secara eksplisit.
 *
 * Pembacaan ber-scope (where organization_id = ...) menyusul di Phase E2.
 */
trait MilikOrganisasi
{
    protected static function bootMilikOrganisasi(): void
    {
        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($context->sudahDitetapkan()) {
                $model->organization_id = $context->rw()?->id;

                return;
            }

            if ($model->organization_id !== null) {
                return;
            }

            $rwAktif = Organization::where('type', Organization::TYPE_RW)
                ->where('status', 'aktif')->limit(2)->pluck('id');
            if ($rwAktif->count() === 1) {
                $model->organization_id = $rwAktif->first();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
