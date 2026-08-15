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
}
