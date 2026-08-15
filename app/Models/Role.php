<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Katalog peran generik (Phase E1 multi-tenant). Peran TIDAK memuat nama
 * desa/RW; penempatannya di organisasi tertentu diurus UserRoleAssignment.
 *
 * Model lama di berkas ini melayani tabel `roles` era PWA yang sudah
 * di-rename ke `roles_legacy_pwa` (nol rujukan kode, lihat DECISIONS).
 */
class Role extends Model
{
    // Harus cocok dengan kolom tabel `roles`.
    protected $fillable = ['slug', 'name', 'scope_type', 'legacy_level'];
}
