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
     * Peran setara operator portal (tak terbatas). Akun bawaan aplikasi
     * memakai level 'admin', bukan 'superadmin', jadi daftar inilah yang
     * dipakai peranEfektifUntuk() dan MatriksKapabilitas untuk menyetarakan
     * ketiganya - jangan pernah membandingkan ke 'superadmin' saja.
     */
    public const LEVEL_ADMIN = ['superadmin', 'super_admin', 'admin'];

    // LEVEL_POWER (hierarki linier) dihapus 2026-08-18 bersama CheckRole:
    // peran tidak lagi berjenjang. Urutan untuk memilih LABEL saat seseorang
    // merangkap beberapa peran ada di MatriksKapabilitas::URUTAN_TAMPIL, dan
    // itu sengaja BUKAN urutan hak.

    public function roleAssignments()
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /**
     * SELURUH peran efektif user untuk sebuah organisasi tenant (Phase E1,
     * diperluas saat otorisasi pindah ke matriks kapabilitas).
     *
     * Assignment dianggap relevan bila organisasinya berada di rantai leluhur
     * organisasi tenant (super_admin di platform berlaku di semua tenant di
     * bawahnya) ATAU di subtree-nya (rt_admin RT 01 berlaku di tenant RW
     * induknya).
     *
     * Mengembalikan SEMUA peran relevan, bukan yang "terkuat": kapabilitasnya
     * digabung, sehingga pengurus yang merangkap sekretaris dan bendahara
     * memegang keduanya. Itulah bedanya matriks dengan hierarki. Urutannya
     * mengikuti MatriksKapabilitas::URUTAN_TAMPIL (menurun) supaya elemen
     * pertama bisa dipakai langsung sebagai label.
     *
     * Array kosong bila tidak ada assignment relevan; pemanggil memakai lantai
     * 'warga'. Jembatan fallback ke users.level sudah dicabut (2026-08-16):
     * kolom level kini hanya catatan tampilan & sasaran notifikasi.
     */
    public function peranEfektifUntuk(?Organization $org): array
    {
        if ($org === null) {
            return [];
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
            return [];
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

        $peran = [];
        foreach ($milik as $assignment) {
            if (! isset($relevan[$assignment->organization_id])) {
                continue;
            }
            // Normalisasi setara-superadmin: kalau ini terlewat, akun bawaan
            // aplikasi jatuh ke lantai warga dan mengunci semua orang.
            $level = $assignment->legacy_level;
            $peran[in_array($level, self::LEVEL_ADMIN, true) ? 'superadmin' : $level] = true;
        }

        $peran = array_keys($peran);
        $urutan = \App\Services\MatriksKapabilitas::URUTAN_TAMPIL;
        usort($peran, fn ($a, $b) => ($urutan[$b] ?? 0) <=> ($urutan[$a] ?? 0));

        return $peran;
    }

    /**
     * Peran efektif TERKUAT untuk sebuah organisasi, atau null bila tidak ada.
     * Dipertahankan untuk label dan penyaringan data; otorisasi memakai
     * peranEfektif() + bolehkah(), bukan ini.
     */
    public function levelEfektifUntuk(?Organization $org): ?string
    {
        return $this->peranEfektifUntuk($org)[0] ?? null;
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
        return $this->peranEfektif()[0];
    }

    /**
     * Seluruh peran efektif user di tenant request ini, lantai ['warga'].
     * Inilah dasar otorisasi sekarang (lihat MatriksKapabilitas::untukUser).
     * Elemen pertama adalah peran dengan URUTAN_TAMPIL tertinggi, dipakai
     * levelEfektif() sebagai label.
     */
    public function peranEfektif(): array
    {
        $context = app(\App\Services\TenantContext::class);

        return $context->ingatPeranEfektif(
            $this->id ?? spl_object_id($this),
            fn () => $this->peranEfektifUntuk($context->organisasi()) ?: ['warga']
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
     * - rw: pemegang kapabilitas `akun.kelola` di tenant itu (bawaannya ketua
     *   RW dan operator portal). Dulu `isSuperAdmin()`, sehingga ketua RW lolos
     *   middleware tapi ditolak di sini - menu Manajemen Akun tampil untuk
     *   halaman yang pasti 403.
     *
     * Ini menjawab "boleh menyentuh akun?" pada TINGKAT host; `izin:akun.kelola`
     * di rute menjawab pertanyaan yang sama untuk aksinya, dan
     * `AkunController::cakupanKelola()` menjawab "akun yang MANA".
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
            return \App\Services\MatriksKapabilitas::userPunya($this, 'akun.kelola');
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

    // Helper izin berbasis NAMA LEVEL (isSuperAdmin/isKetuaRW/canVoid dst)
    // sudah dihapus 2026-08-18. Penggantinya bolehkah('modul.aksi'), supaya
    // izin punya satu sumber: MatriksKapabilitas. Dikunci
    // tests/Feature/PensiunHierarkiLamaTest.php.

    public function isActive(): bool { return $this->status === 'aktif'; }

    public static function labelUntukLevel(string $level): string {
        return match($level) {
            'superadmin' => 'Super Admin',
            'ketua_rw' => 'Ketua RW',
            'sekretaris' => 'Sekretaris RW',
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
