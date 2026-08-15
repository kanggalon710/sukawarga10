<?php

namespace App\Services;

use App\Models\User;

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
     * Send notification to all pengurus (RT/RW/Admin).
     */
    public static function notifyPengurus(string $message): void
    {
        if (!class_exists(\App\Models\User::class)) return;

        $pengurus = User::whereIn('level', ['superadmin', 'ketua_rw', 'bendahara', 'petugas_rt'])
            ->where('status', 'aktif')
            ->whereNotNull('wa')
            ->get();

        foreach ($pengurus as $u) {
            self::sendWA($u->wa, $message);
        }
    }

    /**
     * Send notification to users of a specific level.
     */
    public static function notifyByLevel(string $level, string $message): void
    {
        $users = User::where('level', $level)
            ->where('status', 'aktif')
            ->whereNotNull('wa')
            ->get();

        foreach ($users as $u) {
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
        $users = User::where('level', 'petugas_rt')
            ->where('rt', $rt)
            ->where('status', 'aktif')
            ->whereNotNull('wa')
            ->get();

        foreach ($users as $u) {
            self::sendWA($u->wa, $message);
        }

        // Also notify superadmin & ketua_rw
        $admins = User::whereIn('level', ['superadmin', 'ketua_rw'])
            ->where('status', 'aktif')
            ->whereNotNull('wa')
            ->get();

        foreach ($admins as $u) {
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
