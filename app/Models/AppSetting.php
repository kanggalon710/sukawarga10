<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

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

    /**
     * Key rahasia: tersimpan terenkripsi dan TIDAK PERNAH ikut semuaEfektif().
     *
     * semuaEfektif() diteruskan utuh ke view (Pengaturan, MPWA, cetak surat),
     * jadi apa pun yang ada di sana bisa berakhir di HTML tenant. Dulu kunci
     * API MPWA milik desa/platform ikut terwarisi ke RW dan tampil polos di
     * form. Rahasia hanya dibaca server lewat rahasia().
     */
    public const KEY_RAHASIA = ['mpwa_api_key'];

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
                ->whereNotIn('key', self::KEY_RAHASIA)
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
        if (in_array($key, self::KEY_RAHASIA, true)) {
            throw new \InvalidArgumentException("Key rahasia {$key} wajib lewat simpanRahasia().");
        }

        return static::tulisUntukHost($key, $value);
    }

    /**
     * Nilai rahasia terdekat di rantai tenant (RW -> desa -> platform), sudah
     * didekripsi. HANYA untuk dipakai server (mis. memanggil gateway); jangan
     * pernah diteruskan ke view atau respons JSON.
     */
    public static function rahasia(string $key): ?string
    {
        $baris = static::barisRahasiaTerdekat($key);
        if ($baris === null || $baris->value === null || $baris->value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($baris->value);
        } catch (DecryptException) {
            Log::error('Setting rahasia tidak dapat didekripsi (APP_KEY berubah?)', ['key' => $key]);

            return null;
        }
    }

    /**
     * Status rahasia untuk UI tanpa membuka nilainya:
     * 'sendiri' (milik organisasi host), 'warisan' (dari desa/platform), 'kosong'.
     */
    public static function statusRahasia(string $key): string
    {
        $baris = static::barisRahasiaTerdekat($key);
        if ($baris === null || $baris->value === null || $baris->value === '') {
            return 'kosong';
        }

        return $baris->organization_id === static::orgHost() ? 'sendiri' : 'warisan';
    }

    /**
     * Simpan rahasia terenkripsi untuk organisasi host. Nilai kosong MENGHAPUS
     * baris milik host (kembali memakai warisan, bila ada); form memakai
     * checkbox eksplisit untuk itu, bukan input kosong.
     */
    public static function simpanRahasia(string $key, ?string $nilai): void
    {
        if (! in_array($key, self::KEY_RAHASIA, true)) {
            throw new \InvalidArgumentException("{$key} bukan key rahasia.");
        }

        if ($nilai === null || $nilai === '') {
            static::where('key', $key)->where('organization_id', static::orgHost())->delete();
            app(TenantContext::class)->lupakan('app_settings.efektif');

            return;
        }

        static::tulisUntukHost($key, Crypt::encryptString($nilai));
    }

    /**
     * Nilai MILIK organisasi host saja (bukan efektif/warisan), atau null bila
     * host tidak punya baris sendiri. Untuk keputusan "boleh hapus berkas lama"
     * dan "tampilkan tombol kembalikan": nilai warisan milik desa/platform
     * tidak boleh disentuh dari tenant.
     */
    public static function milikHost(string $key): ?string
    {
        return static::where('key', $key)->where('organization_id', static::orgHost())->value('value');
    }

    /** Hapus baris milik host sehingga nilai kembali diwarisi (desa/platform/bawaan). */
    public static function hapusMilikHost(string $key): void
    {
        static::where('key', $key)->where('organization_id', static::orgHost())->delete();
        app(TenantContext::class)->lupakan('app_settings.efektif');
    }

    private static function barisRahasiaTerdekat(string $key): ?self
    {
        $rantai = app(TenantContext::class)->rantaiLeluhurIds();
        $peringkat = array_flip($rantai);

        return static::query()
            ->where('key', $key)
            ->where(function ($q) use ($rantai) {
                $q->whereNull('organization_id');
                if ($rantai !== []) {
                    $q->orWhereIn('organization_id', $rantai);
                }
            })
            ->get(['key', 'value', 'organization_id'])
            ->sortBy(fn ($b) => $b->organization_id === null
                ? PHP_INT_MAX
                : ($peringkat[$b->organization_id] ?? PHP_INT_MAX - 1))
            ->first();
    }

    private static function orgHost(): ?int
    {
        $context = app(TenantContext::class);

        return $context->sudahDitetapkan() ? $context->organisasi()?->id : null;
    }

    private static function tulisUntukHost(string $key, $value): static
    {
        $context = app(TenantContext::class);
        $orgId = static::orgHost();

        $baris = static::updateOrCreate(
            ['key' => $key, 'organization_id' => $orgId],
            ['value' => $value]
        );
        $context->lupakan('app_settings.efektif');

        return $baris;
    }

    /**
     * Tulis setting untuk organisasi TERTENTU, bukan organisasi host request.
     * Dipakai alat koreksi lintas-tenant (Manajemen Desa mengedit setting
     * milik RW/desa dari host platform).
     */
    public static function simpanUntuk(?int $orgId, string $key, $value): static
    {
        if (in_array($key, self::KEY_RAHASIA, true)) {
            throw new \InvalidArgumentException("Key rahasia {$key} wajib lewat simpanRahasia().");
        }

        $baris = static::updateOrCreate(
            ['key' => $key, 'organization_id' => $orgId],
            ['value' => $value]
        );
        app(TenantContext::class)->lupakan('app_settings.efektif');

        return $baris;
    }
}
