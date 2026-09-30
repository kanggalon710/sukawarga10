<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kode pemulihan PIN sekali pakai yang dikirim lewat WA.
 *
 * Kode disimpan sebagai HMAC ber-APP_KEY yang terikat user_id, bukan bcrypt:
 * bcrypt yang hanya dijalankan untuk nomor TERDAFTAR membuat waktu respons
 * membocorkan keberadaan akun. Ruang kode yang kecil ditutup oleh batas
 * percobaan, masa berlaku, dan pembatas laju, bukan oleh lambatnya hash.
 */
class PemulihanPin extends Model
{
    public const MASA_BERLAKU_MENIT = 10;

    public const PERCOBAAN_MAKS = 5;

    protected $fillable = ['user_id', 'kode_hash', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'dipakai_pada' => 'datetime',
    ];

    /** Terbitkan kode baru untuk user; kode lama yang belum dipakai dihanguskan. */
    public static function terbitkan(User $user, string $kode): self
    {
        static::hanguskanMilik($user);

        return static::create([
            'user_id' => $user->id,
            'kode_hash' => static::sidik($user, $kode),
            'expires_at' => now()->addMinutes(self::MASA_BERLAKU_MENIT),
        ]);
    }

    public static function hanguskanMilik(User $user): void
    {
        static::where('user_id', $user->id)->whereNull('dipakai_pada')
            ->update(['dipakai_pada' => now()]);
    }

    public function cocok(User $user, string $kode): bool
    {
        return hash_equals($this->kode_hash, static::sidik($user, $kode));
    }

    private static function sidik(User $user, string $kode): string
    {
        return hash_hmac('sha256', 'pemulihan-pin|'.$user->id.'|'.$kode, (string) config('app.key'));
    }
}
