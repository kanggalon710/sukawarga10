<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Key-value konfigurasi, ber-scope organisasi sejak Phase F.
 *
 * organization_id NULL = default level platform, diwarisi semua tenant;
 * baris ber-organisasi menimpanya, yang terdekat menang (RW mengalahkan
 * desa mengalahkan platform). SELURUH pembacaan wajib lewat nilai()/
 * semuaEfektif() dan penulisan lewat simpan() - query where('key') polos
 * tidak tahu inheritance dan bisa mengambil baris tenant lain.
 */
class AppSetting extends Model
{
    protected $guarded = [];

    /** Nilai efektif satu key untuk tenant request ini. */
    public static function nilai(string $key, ?string $default = null): ?string
    {
        return static::semuaEfektif()[$key] ?? $default;
    }

    /**
     * Seluruh setting efektif (key => value) untuk tenant request ini:
     * satu query, inheritance dihitung di memori, di-memo per request.
     */
    public static function semuaEfektif(): array
    {
        $context = app(TenantContext::class);

        return $context->ingat('app_settings.efektif', function () use ($context) {
            $rantai = $context->rantaiLeluhurIds(); // terdekat dulu; kosong di konsol

            $baris = static::query()
                ->where(function ($q) use ($rantai) {
                    $q->whereNull('organization_id');
                    if ($rantai !== []) {
                        $q->orWhereIn('organization_id', $rantai);
                    }
                })
                ->get(['key', 'value', 'organization_id']);

            // Peringkat: indeks di rantai (kecil = dekat = menang); NULL paling jauh.
            $peringkat = array_flip($rantai);
            $efektif = [];
            $asal = [];
            foreach ($baris as $b) {
                $rank = $b->organization_id === null
                    ? PHP_INT_MAX
                    : ($peringkat[$b->organization_id] ?? PHP_INT_MAX - 1);
                if (! array_key_exists($b->key, $efektif) || $rank < $asal[$b->key]) {
                    $efektif[$b->key] = $b->value;
                    $asal[$b->key] = $rank;
                }
            }

            return $efektif;
        });
    }

    /**
     * Tulis setting untuk tenant request ini (di konsol: default platform).
     * Idempoten per (organisasi, key); memo request langsung dilupakan.
     *
     * Targetnya organisasi HOST request: RW di host RW, desa di host desa
     * (diwariskan ke RW-RW-nya), platform di host platform. Dulu memakai
     * rw() saja, sehingga tulisan dari host non-RW jatuh diam-diam ke baris
     * NULL (bawaan platform) - salah tingkat.
     */
    public static function simpan(string $key, $value): static
    {
        $context = app(TenantContext::class);
        $orgId = $context->sudahDitetapkan() ? $context->organisasi()?->id : null;

        $baris = static::updateOrCreate(
            ['key' => $key, 'organization_id' => $orgId],
            ['value' => $value]
        );
        $context->lupakan('app_settings.efektif');

        return $baris;
    }
}
