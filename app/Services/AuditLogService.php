<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public static function log(string $aksi, string $modul, string $keterangan = ''): void
    {
        try {
            AuditLog::create([
                'log_id' => 'LOG-' . uniqid(),
                'tanggal' => now(),
                'aksi' => $aksi,
                'collection' => $modul,
                'recordId' => null,
                'deskripsi' => $keterangan,
                'operator' => Auth::user()->username ?? 'system',
            ]);
        } catch (\Exception $e) {
            // Silently fail — don't break the app if logging fails
            \Log::warning('AuditLog failed: ' . $e->getMessage());
        }
    }
}
