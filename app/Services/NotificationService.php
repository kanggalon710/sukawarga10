<?php

namespace App\Services;

use App\Models\User;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send WA notification to a specific phone number.
     */
    public static function sendWA(string $phone, string $message): bool
    {
        try {
            $apiKey = AppSetting::where('key', 'mpwa_api_key')->value('value');
            $sender = AppSetting::where('key', 'mpwa_sender')->value('value');
            $apiUrl = AppSetting::where('key', 'mpwa_api_url')->value('value') ?: 'https://mpwa.jabnet.id';

            if (!$apiKey || !$sender) {
                Log::warning('NotificationService: MPWA not configured');
                return false;
            }

            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($phone, '08')) $phone = '62' . substr($phone, 1);

            $res = Http::withHeaders(['Authorization' => $apiKey])
                ->post("$apiUrl/api/send-message", [
                    'sender'  => $sender,
                    'number'  => $phone,
                    'message' => $message,
                ]);

            return $res->successful();
        } catch (\Exception $e) {
            Log::error('NotificationService WA error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an aturan otomatis is enabled.
     */
    public static function isEnabled(string $key): bool
    {
        $val = AppSetting::where('key', $key)->value('value');
        return ($val ?? '1') == '1';
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
