<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemetaan hostname ke organisasi (Phase B1 multi-tenant).
 * Resolver yang membacanya menyusul di Phase B2; hostname tak terdaftar
 * harus berujung 404, bukan fallback diam-diam ke tenant lain.
 */
class Domain extends Model
{
    // Harus cocok dengan kolom tabel `domains`.
    protected $fillable = [
        'organization_id', 'hostname', 'is_primary', 'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
