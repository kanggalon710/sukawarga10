<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Simpul hierarki tenant: platform > desa > rw > rt.
 * Fondasi Phase B1 multi-tenant; belum dibaca controller mana pun.
 * Lihat AI_AGENT_MULTI_TENANT_ARCHITECTURE.md dan .ai/AUDIT-MULTITENANT.md.
 */
class Organization extends Model
{
    public const TYPE_PLATFORM = 'platform';

    public const TYPE_DESA = 'desa';

    public const TYPE_RW = 'rw';

    public const TYPE_RT = 'rt';

    // Harus cocok dengan kolom tabel `organizations` (pelajaran dari kasus
    // Transaksi::$fillable: atribut non-fillable dibuang tanpa bersuara).
    protected $fillable = [
        'parent_id', 'type', 'name', 'code', 'slug', 'status',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Leluhur bertipe tertentu (mis. desa induk dari sebuah RT), termasuk
     * dirinya sendiri bila tipenya sudah cocok. Hierarki maksimal 4 tingkat,
     * jadi penelusuran per-induk ini murah; batas 10 hanya pagar pengaman
     * terhadap data siklik.
     */
    public function leluhur(string $type): ?self
    {
        $node = $this;
        for ($i = 0; $node !== null && $i < 10; $i++) {
            if ($node->type === $type) {
                return $node;
            }
            $node = $node->parent;
        }

        return null;
    }

    /**
     * Slug organisasi RT di bawah sebuah RW, dengan normalisasi dua digit
     * yang sama dengan seed migrasi B1 ('1' dan '01' adalah RT yang sama).
     * Satu-satunya sumber format slug RT: pembuatan (AkunController) dan
     * pencarian (NotificationService) harus selalu sepakat.
     */
    public static function slugRt(self $rw, string $rt): string
    {
        $rt = str_pad(trim($rt), 2, '0', STR_PAD_LEFT);

        return "rt-{$rt}-{$rw->slug}";
    }

    /**
     * Seluruh id organisasi di subtree $akarId, termasuk akarnya sendiri.
     * $petaInduk opsional untuk pemanggil yang sudah memegang hasil
     * `pluck('parent_id', 'id')` dan tidak boleh menambah query (jalur
     * levelEfektifUntuk yang dijaga tes hitung-query).
     */
    public static function idSubtree(int $akarId, $petaInduk = null): array
    {
        $petaInduk ??= static::pluck('parent_id', 'id');

        $anakDari = [];
        foreach ($petaInduk as $anakId => $indukId) {
            if ($indukId !== null) {
                $anakDari[$indukId][] = $anakId;
            }
        }

        // Digali per tingkat; batas 10 = pagar terhadap data siklik.
        $hasil = [$akarId];
        $frontier = [$akarId];
        for ($i = 0; $frontier !== [] && $i < 10; $i++) {
            $berikut = [];
            foreach ($frontier as $fid) {
                $berikut = array_merge($berikut, $anakDari[$fid] ?? []);
            }
            $hasil = array_merge($hasil, $berikut);
            $frontier = $berikut;
        }

        return $hasil;
    }
}
