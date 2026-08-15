<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Harus cocok dengan kolom tabel `users`. 'nama' dan 'noHP' dihapus dari
    // daftar ini karena kolomnya tidak ada (nama lengkap = namaLengkap,
    // nomor WA = wa) — menulis ke sana hanya dibuang diam-diam.
    protected $fillable = [
        'user_id', 'username', 'namaLengkap', 'pin',
        'wa', 'rt', 'keluarga_id', 'level', 'status', 'isDefault',
        'last_login_at', 'failed_login_count', 'locked_until',
    ];

    protected $hidden = ['pin', 'remember_token'];

    protected $casts = [
        'isDefault' => 'boolean',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    // Auth uses PIN field instead of password
    public function getAuthPassword() { return $this->pin; }

    /**
     * Level setara superadmin. Akun default aplikasi memakai level 'admin',
     * dan middleware CheckRole sudah menyetarakannya (power 5). Semua
     * pengecekan izin di bawah WAJIB memakai daftar ini supaya konsisten,
     * jangan pernah membandingkan ke 'superadmin' saja.
     */
    public const LEVEL_ADMIN = ['superadmin', 'super_admin', 'admin'];

    public function isSuperAdmin(): bool { return in_array($this->level, self::LEVEL_ADMIN, true); }
    public function isKetuaRW(): bool { return $this->level === 'ketua_rw'; }
    public function isBendahara(): bool { return $this->level === 'bendahara'; }
    public function isPetugasRT(): bool { return $this->level === 'petugas_rt'; }

    public function canVoid(): bool { return $this->isSuperAdmin() || $this->level === 'ketua_rw'; }
    public function canManageUsers(): bool { return $this->isSuperAdmin(); }
    public function canManageFinance(): bool { return $this->isSuperAdmin() || in_array($this->level, ['ketua_rw', 'bendahara'], true); }

    public function isActive(): bool { return $this->status === 'aktif'; }

    public function getLevelLabelAttribute(): string {
        return match($this->level) {
            'superadmin' => 'Super Admin',
            'ketua_rw' => 'Ketua RW',
            'bendahara' => 'Bendahara',
            'petugas_rt' => 'Petugas RT',
            'warga' => 'Warga',
            default => ucfirst($this->level),
        };
    }
}
