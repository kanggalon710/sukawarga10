<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserRoleAssignment;

/**
 * NotificationService — PENYALURAN notifikasi (siapa yang dikirimi).
 *
 * Pengirimannya sendiri milik MpwaService. Sebelumnya kelas ini punya
 * implementasi HTTP-nya sendiri dengan endpoint, cara auth, dan normalisasi
 * nomor yang berbeda (hanya menangani awalan 08, sehingga nomor 8xx dan +62
 * salah kirim). Satu aturan pengiriman, satu tempat.
 */
class NotificationService
{
    /**
     * Send WA notification to a specific phone number.
     */
    public static function sendWA(string $phone, string $message): bool
    {
        return MpwaService::send($phone, $message);
    }

    /**
     * Check if an aturan otomatis is enabled.
     */
    public static function isEnabled(string $key): bool
    {
        return MpwaService::isEnabled($key);
    }

    /**
     * Penerima aktif ber-WA yang assignment perannya relevan dengan tenant
     * request: organisasinya ada di rantai leluhur tenant (super_admin
     * platform ikut menerima) atau di subtree-nya (pengurus RT).
     *
     * Kolom users.level TIDAK dipakai di jalur ber-tenant - kolom itu berlaku
     * lintas tenant, dan aduan RW A tidak boleh membunyikan WA pengurus RW B.
     * Jalur konsol (importer/artisan) belum ber-tenant: penyaluran lama
     * berbasis kolom dipertahankan sampai pengiriman pindah ke queue (fase G).
     */
    private static function penerimaLevel(array $levels): \Illuminate\Support\Collection
    {
        $context = app(TenantContext::class);
        if (!$context->sudahDitetapkan() || $context->organisasi() === null) {
            return User::whereIn('level', $levels)
                ->where('status', 'aktif')->whereNotNull('wa')->get(['wa']);
        }

        $orgRelevan = array_unique(array_merge(
            $context->rantaiLeluhurIds(),
            Organization::idSubtree($context->organisasi()->id)
        ));

        return User::where('status', 'aktif')->whereNotNull('wa')
            ->whereIn('id', UserRoleAssignment::query()
                ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
                ->whereIn('roles.legacy_level', $levels)
                ->whereIn('user_role_assignments.organization_id', $orgRelevan)
                ->select('user_role_assignments.user_id'))
            ->get(['wa']);
    }

    /**
     * Send notification to all pengurus (RT/RW/Admin).
     */
    public static function notifyPengurus(string $message): void
    {
        foreach (self::penerimaLevel(['superadmin', 'ketua_rw', 'bendahara', 'petugas_rt']) as $u) {
            self::sendWA($u->wa, $message);
        }
    }

    /**
     * Send notification to users of a specific level.
     */
    public static function notifyByLevel(string $level, string $message): void
    {
        foreach (self::penerimaLevel([$level]) as $u) {
            self::sendWA($u->wa, $message);
        }
    }

    /**
     * Send notification to a specific user by ID.
     */
    public static function notifyUser(int $userId, string $message): void
    {
        $user = User::find($userId);
        if ($user && $user->wa) {
            self::sendWA($user->wa, $message);
        }
    }

    /**
     * Send notification to RT petugas for a specific RT.
     */
    public static function notifyRT(string $rt, string $message): void
    {
        $context = app(TenantContext::class);
        $rw = $context->sudahDitetapkan() ? $context->rw() : null;

        if ($rw === null) {
            // Jalur konsol: penyaluran lama berbasis kolom (lihat penerimaLevel).
            $users = User::where('level', 'petugas_rt')
                ->where('rt', $rt)
                ->where('status', 'aktif')
                ->whereNotNull('wa')
                ->get(['wa']);
        } else {
            // Pengurus RT = pemegang assignment di organisasi RT tenant ini;
            // RT tenant lain bernomor sama tidak ikut terbunyikan.
            $orgRtId = Organization::where('slug', Organization::slugRt($rw, $rt))->value('id');
            $users = $orgRtId === null
                ? collect()
                : User::where('status', 'aktif')->whereNotNull('wa')
                    ->whereIn('id', UserRoleAssignment::query()
                        ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
                        ->where('roles.legacy_level', 'petugas_rt')
                        ->where('user_role_assignments.organization_id', $orgRtId)
                        ->select('user_role_assignments.user_id'))
                    ->get(['wa']);
        }

        foreach ($users as $u) {
            self::sendWA($u->wa, $message);
        }

        // Also notify superadmin & ketua_rw
        foreach (self::penerimaLevel(['superadmin', 'ketua_rw']) as $u) {
            self::sendWA($u->wa, $message);
        }
    }

    /**
     * Notify about a warga's phone number directly.
     */
    public static function notifyWarga(string $phone, string $message): void
    {
        if ($phone) {
            self::sendWA($phone, $message);
        }
    }
}
