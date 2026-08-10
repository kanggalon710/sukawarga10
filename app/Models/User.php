<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_id', 'username', 'nama', 'namaLengkap', 'pin',
        'noHP', 'wa', 'rt', 'level', 'status', 'isDefault',
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

    public function isSuperAdmin(): bool { return $this->level === 'superadmin'; }
    public function isKetuaRW(): bool { return $this->level === 'ketua_rw'; }
    public function isBendahara(): bool { return $this->level === 'bendahara'; }
    public function isPetugasRT(): bool { return $this->level === 'petugas_rt'; }

    public function canVoid(): bool { return in_array($this->level, ['superadmin', 'ketua_rw']); }
    public function canManageUsers(): bool { return in_array($this->level, ['superadmin']); }
    public function canManageFinance(): bool { return in_array($this->level, ['superadmin', 'ketua_rw', 'bendahara']); }

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
