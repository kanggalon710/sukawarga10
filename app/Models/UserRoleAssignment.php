<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penempatan (user, peran, organisasi) - Phase E1 multi-tenant.
 * Satu user boleh memegang peran yang sama di beberapa organisasi
 * (mis. rt_admin di dua RT), masing-masing satu baris.
 */
class UserRoleAssignment extends Model
{
    // Harus cocok dengan kolom tabel `user_role_assignments`.
    protected $fillable = ['user_id', 'role_id', 'organization_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
