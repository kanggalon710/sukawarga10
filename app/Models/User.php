<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, MilikOrganisasi, Notifiable;

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

    /**
     * Kekuatan hierarki level. Satu-satunya sumber kebenaran: dipakai
     * CheckRole dan pemilihan assignment terkuat di levelEfektifUntuk().
     */
    public const LEVEL_POWER = [
        'superadmin' => 5,
        'admin' => 5,
        'ketua_rw' => 4,
        'bendahara' => 3,
        'petugas_rt' => 2,
        'warga' => 1,
    ];

    public function roleAssignments()
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /**
     * Level efektif user untuk sebuah organisasi tenant (Phase E1).
     *
     * Assignment dianggap relevan bila organisasinya berada di rantai leluhur
     * organisasi tenant (super_admin di platform berlaku di semua tenant di
     * bawahnya) ATAU di subtree-nya (rt_admin RT 01 berlaku di tenant RW
     * induknya). Dari yang relevan diambil legacy_level terkuat.
     *
     * Return null bila tidak ada assignment relevan; pemanggil jatuh kembali
     * ke users.level - jembatan transisi yang membuat perilaku lama utuh dan
     * dihapus saat kolom level pensiun (tercatat di TODO).
     */
    public function levelEfektifUntuk(?Organization $org): ?string
    {
        if ($org === null) {
            return null;
        }

        // Maksimal DUA query, konstan terhadap jumlah organisasi maupun
        // assignment - dijaga tes hitung-query Dashboard, jangan diubah jadi
        // query per-tingkat tanpa memeriksa tes itu.
        // Query 1: seluruh assignment user + padanan levelnya (join, bukan
        // eager load, supaya tetap satu query). Mayoritas user tidak punya
        // assignment, jadi jalur umum berhenti di sini.
        $milik = $this->roleAssignments()
            ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->get(['user_role_assignments.organization_id', 'roles.legacy_level']);
        if ($milik->isEmpty()) {
            return null;
        }

        // Query 2: peta induk seluruh organisasi (tabel kecil), rantai
        // leluhur + subtree dihitung di memori.
        $petaInduk = Organization::pluck('parent_id', 'id');

        // Rantai leluhur, termasuk dirinya (batas 10 = pagar data siklik).
        $relevan = [];
        $id = $org->id;
        for ($i = 0; $id !== null && $i < 10; $i++) {
            $relevan[] = $id;
            $id = $petaInduk[$id] ?? null;
        }

        // Subtree, digali per tingkat dari peta yang sama.
        $anakDari = [];
        foreach ($petaInduk as $anakId => $indukId) {
            if ($indukId !== null) {
                $anakDari[$indukId][] = $anakId;
            }
        }
        $frontier = [$org->id];
        for ($i = 0; $frontier !== [] && $i < 10; $i++) {
            $berikut = [];
            foreach ($frontier as $fid) {
                $berikut = array_merge($berikut, $anakDari[$fid] ?? []);
            }
            $relevan = array_merge($relevan, $berikut);
            $frontier = $berikut;
        }
        $relevan = array_flip($relevan);

        $terkuat = null;
        foreach ($milik as $assignment) {
            if (! isset($relevan[$assignment->organization_id])) {
                continue;
            }
            $level = $assignment->legacy_level;
            if ((self::LEVEL_POWER[$level] ?? 0) > (self::LEVEL_POWER[$terkuat] ?? 0)) {
                $terkuat = $level;
            }
        }

        return $terkuat;
    }

    /**
     * Level efektif untuk tenant request ini (Phase E1): assignment ber-scope
     * menang, users.level jadi fallback transisi. Seluruh cek izin di bawah
     * WAJIB lewat ini, bukan kolom level mentah - kalau tidak, hak dari
     * assignment lolos middleware tapi ditolak controller.
     *
     * Memo-nya dititip di TenantContext (scoped per request), BUKAN di
     * instance model: instance user bisa hidup melintasi beberapa request
     * dalam satu proses tes, dan memo yang menempel padanya membuat request
     * kedua "lebih murah" secara semu.
     */
    public function levelEfektif(): string
    {
        $context = app(\App\Services\TenantContext::class);

        return $context->ingatLevelEfektif(
            $this->id ?? spl_object_id($this),
            fn () => $this->levelEfektifUntuk($context->organisasi()) ?? $this->level ?? 'warga'
        );
    }

    public function isSuperAdmin(): bool { return in_array($this->levelEfektif(), self::LEVEL_ADMIN, true); }
    public function isKetuaRW(): bool { return $this->levelEfektif() === 'ketua_rw'; }
    public function isBendahara(): bool { return $this->levelEfektif() === 'bendahara'; }
    public function isPetugasRT(): bool { return $this->levelEfektif() === 'petugas_rt'; }

    public function canVoid(): bool { return $this->isSuperAdmin() || $this->levelEfektif() === 'ketua_rw'; }
    public function canManageUsers(): bool { return $this->isSuperAdmin(); }
    public function canManageFinance(): bool { return $this->isSuperAdmin() || in_array($this->levelEfektif(), ['ketua_rw', 'bendahara'], true); }

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
