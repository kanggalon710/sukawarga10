<?php

namespace Tests;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pasangkan assignment yang setara dengan kolom level fixture, di RW 10.
     *
     * Sejak fallback users.level pensiun (2026-08-16), hak akses hanya datang
     * dari assignment; fixture user pengurus wajib lewat sini agar levelnya
     * sungguh berlaku. Warga tidak butuh baris (lantai default levelEfektif).
     */
    protected function pasangPeranSetaraLevel(User $user): User
    {
        if ($user->level !== null && $user->level !== 'warga') {
            // legacy_level ambigu untuk 'ketua_rw' (desa_admin & rw_admin):
            // utamakan peran ber-scope RW, cocok dengan organisasi pemasangan.
            $roleId = Role::where('legacy_level', $user->level)
                ->where('scope_type', 'rw')->value('id')
                ?? Role::where('legacy_level', $user->level)->value('id');

            UserRoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
            ]);
        }

        return $user;
    }
}
