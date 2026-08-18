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

    /** @var array<int|string, array<int, string>> memo peran efektif per user, umur satu request */
    private array $peranEfektif = [];

    /** @var array<string, mixed> memo umum ber-umur satu request (settings efektif, dll) */
    private array $memo = [];

    /** Memo generik seumur request; kunci yang sama dihitung sekali. */
    public function ingat(string $kunci, \Closure $hitung): mixed
    {
        return $this->memo[$kunci] ??= $hitung();
    }

    public function lupakan(string $kunci): void
    {
        unset($this->memo[$kunci]);
    }

    /**
     * ID organisasi tenant + seluruh leluhurnya, urut dari yang terdekat
     * (RW dulu, platform terakhir). Satu query, di-memo per request.
     */
    public function rantaiLeluhurIds(): array
    {
        if (! $this->sudahDitetapkan()) {
            return [];
        }

        return $this->ingat('rantai.leluhur', function () {
            $petaInduk = Organization::pluck('parent_id', 'id');
            $rantai = [];
            $id = $this->organization->id;
            for ($i = 0; $id !== null && $i < 10; $i++) {
                $rantai[] = $id;
                $id = $petaInduk[$id] ?? null;
            }

            return $rantai;
        });
    }

    /**
     * Memo peran efektif per user (Phase E1, diperluas jadi daftar peran saat
     * otorisasi pindah ke matriks kapabilitas). Ditaruh di sini, bukan di
     * model, karena instance ini scoped per request sehingga memo tidak pernah
     * bocor antar request - instance model bisa hidup melintasi beberapa
     * request dalam satu proses tes.
     */
    public function ingatPeranEfektif(int|string $userKey, \Closure $hitung): array
    {
        return $this->peranEfektif[$userKey] ??= $hitung();
    }

    public function tetapkan(Domain $domain): void
    {
        $this->domain = $domain;
        $this->organization = $domain->organization;
        // Di produksi instance ini baru tiap request; di proses tes container
        // dipakai ulang antar request, jadi memo direset di sini (tetapkan
        // dipanggil resolver tepat sekali di awal tiap request).
        $this->peranEfektif = [];
        $this->memo = [];
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
