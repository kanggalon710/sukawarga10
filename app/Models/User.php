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
     * Return null bila tidak ada assignment relevan; pemanggil memakai lantai
     * 'warga'. Jembatan fallback ke users.level sudah dicabut (2026-08-16):
     * kolom level kini hanya catatan tampilan & sasaran notifikasi.
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

        // Subtree, digali dari peta yang sama (tanpa query tambahan);
        // akar ganda tidak masalah karena array_flip mendedupe.
        $relevan = array_flip(array_merge(
            $relevan,
            Organization::idSubtree($org->id, $petaInduk)
        ));

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
     * Level efektif untuk tenant request ini: HANYA dari assignment ber-scope
     * (Phase E1); tanpa assignment yang relevan, lantainya 'warga'. Fallback
     * transisi ke users.level dicabut 2026-08-16 - fallback membuat level lama
     * berlaku di SEMUA tenant, bocor begitu tenant kedua hidup. Seluruh cek
     * izin di bawah WAJIB lewat ini, bukan kolom level mentah.
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
            fn () => $this->levelEfektifUntuk($context->organisasi()) ?? 'warga'
        );
    }

    /**
     * Organisasi RW asal user - dari keluarganya, atau dari assignment-nya.
     * Dipakai penjaga login lintas tenant (menunjukkan alamat portal yang
     * benar) dan beranda platform (mengarahkan pengurus ke portalnya).
     */
    public function rwAsal(): ?Organization
    {
        if ($this->keluarga_id) {
            $kk = Keluarga::withoutGlobalScope('organisasi')
                ->where('keluarga_id', $this->keluarga_id)->first();
            $rw = $kk?->organization?->leluhur(Organization::TYPE_RW);
            if ($rw !== null) {
                return $rw;
            }
        }

        foreach ($this->roleAssignments()->with('organization')->get() as $assignment) {
            $rw = $assignment->organization?->leluhur(Organization::TYPE_RW);
            if ($rw !== null) {
                return $rw;
            }
        }

        return null;
    }

    /**
     * Boleh mengelola AKUN pada tingkat organisasi host $org?
     * - platform: hanya admin platform (owner) - semua akun;
     * - desa: pemegang peran ber-power >= ketua_rw yang assignment-nya di
     *   RANTAI LELUHUR desa itu (desa_admin di desa tsb / super_admin
     *   platform) - subtree TIDAK dihitung: admin RW bukan pengelola desa;
     * - rw: superadmin efektif tenant (aturan lama Manajemen Akun).
     */
    public function bolehKelolaAkunDi(?Organization $org): bool
    {
        if ($org === null) {
            return false;
        }
        if ($org->type === Organization::TYPE_PLATFORM) {
            return $this->adalahAdminPlatform();
        }
        if ($org->leluhur(Organization::TYPE_RW) !== null) {
            return $this->isSuperAdmin();
        }

        $context = app(\App\Services\TenantContext::class);

        return $context->ingat("kelola.akun.{$this->id}.{$org->id}", function () use ($org) {
            $rantai = [];
            $node = $org;
            for ($i = 0; $node !== null && $i < 10; $i++) {
                $rantai[] = $node->id;
                $node = $node->parent;
            }

            return UserRoleAssignment::query()
                ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
                ->where('user_role_assignments.user_id', $this->id)
                ->whereIn('user_role_assignments.organization_id', $rantai)
                ->whereIn('roles.legacy_level', ['superadmin', 'ketua_rw'])
                ->exists();
        });
    }

    /**
     * Pemegang super_admin di organisasi PLATFORM - gerbang fitur lintas
     * tenant (Manajemen Desa). Superadmin ber-scope tenant (buatan Manajemen
     * Akun) bukan admin platform. Memo per request di TenantContext.
     */
    public function adalahAdminPlatform(): bool
    {
        $context = app(\App\Services\TenantContext::class);

        return $context->ingat("admin.platform.{$this->id}", function () {
            return UserRoleAssignment::query()
                ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
                ->join('organizations', 'organizations.id', '=', 'user_role_assignments.organization_id')
                ->where('user_role_assignments.user_id', $this->id)
                ->where('roles.slug', 'super_admin')
                ->where('organizations.type', Organization::TYPE_PLATFORM)
                ->exists();
        });
    }

    public function isSuperAdmin(): bool { return in_array($this->levelEfektif(), self::LEVEL_ADMIN, true); }
    public function isKetuaRW(): bool { return $this->levelEfektif() === 'ketua_rw'; }
    public function isBendahara(): bool { return $this->levelEfektif() === 'bendahara'; }
    public function isPetugasRT(): bool { return $this->levelEfektif() === 'petugas_rt'; }

    public function canVoid(): bool { return $this->isSuperAdmin() || $this->levelEfektif() === 'ketua_rw'; }
    public function canManageUsers(): bool { return $this->isSuperAdmin(); }
    public function canManageFinance(): bool { return $this->isSuperAdmin() || in_array($this->levelEfektif(), ['ketua_rw', 'bendahara'], true); }

    public function isActive(): bool { return $this->status === 'aktif'; }

    public static function labelUntukLevel(string $level): string {
        return match($level) {
            'superadmin' => 'Super Admin',
            'ketua_rw' => 'Ketua RW',
            'bendahara' => 'Bendahara',
            'petugas_rt' => 'Petugas RT',
            'warga' => 'Warga',
            default => ucfirst($level),
        };
    }

    /** Label kolom level tersimpan — untuk daftar Manajemen Akun (tanpa query per baris). */
    public function getLevelLabelAttribute(): string {
        return self::labelUntukLevel($this->level ?? 'warga');
    }

    /**
     * Label level EFEKTIF — untuk identitas user yang sedang login: warga yang
     * diangkat lewat assignment harus melihat peran yang berlaku, bukan kolom
     * lama. Jangan dipakai di loop daftar user: levelEfektif() menjalankan
     * query assignment per user.
     */
    public function getLevelEfektifLabelAttribute(): string {
        return self::labelUntukLevel($this->levelEfektif());
    }
}
