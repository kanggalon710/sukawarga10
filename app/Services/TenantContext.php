<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Organization;

/**
 * Konteks tenant untuk satu request (Phase B2 multi-tenant).
 *
 * Diisi SEKALI oleh middleware ResolveTenant hasil pemetaan hostname di tabel
 * `domains`; setelah itu seluruh aplikasi bertanya ke sini, bukan mem-parsing
 * hostname sendiri-sendiri (aturan §13 dokumen arsitektur). Terdaftar scoped
 * di AppServiceProvider sehingga satu instance per request.
 *
 * Hostname HANYA untuk menemukan konteks. Otorisasi tetap diperiksa terhadap
 * data scope di database, bukan terhadap hostname.
 */
class TenantContext
{
    private ?Domain $domain = null;

    private ?Organization $organization = null;

    public function tetapkan(Domain $domain): void
    {
        $this->domain = $domain;
        $this->organization = $domain->organization;
    }

    public function sudahDitetapkan(): bool
    {
        return $this->organization !== null;
    }

    public function domain(): ?Domain
    {
        return $this->domain;
    }

    public function hostname(): ?string
    {
        return $this->domain?->hostname;
    }

    /** Organisasi yang dituju hostname ini (saat ini selalu level RW). */
    public function organisasi(): ?Organization
    {
        return $this->organization;
    }

    public function rw(): ?Organization
    {
        return $this->organization?->leluhur(Organization::TYPE_RW);
    }

    public function desa(): ?Organization
    {
        return $this->organization?->leluhur(Organization::TYPE_DESA);
    }

    public function platform(): ?Organization
    {
        return $this->organization?->leluhur(Organization::TYPE_PLATFORM);
    }
}
