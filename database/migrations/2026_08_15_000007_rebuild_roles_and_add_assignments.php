<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E1 multi-tenant: katalog peran generik + assignment ber-scope
 * (lihat .ai/AUDIT-MULTITENANT.md bagian 10 dan dokumen arsitektur §8-§10).
 *
 * Tabel `roles` lama (era PWA: rtFilter, readOnly, nol rujukan di kode)
 * TIDAK dipakai ulang - bentuknya membawa asumsi single-RW. Ia di-RENAME ke
 * `roles_legacy_pwa`, bukan di-drop, supaya baris yang mungkin masih ada di
 * produksi tidak lenyap; drop manual setelah dipastikan kosong.
 *
 * Kolom `legacy_level` adalah jembatan transisi: CheckRole menerjemahkan
 * assignment ke level lama sehingga hierarki yang ada tetap berlaku persis.
 * Dihapus nanti bersama kolom `users.level` saat sistem lama pensiun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('roles', 'roles_legacy_pwa');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('scope_type');   // tingkat organisasi tempat peran ini lazim dipasang
            $table->string('legacy_level'); // padanan level lama untuk CheckRole selama transisi
            $table->timestamps();
        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->foreignId('organization_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'organization_id'], 'ura_unik');
            $table->index('user_id', 'ura_user_idx');
            $table->index('organization_id', 'ura_org_idx');
        });

        $this->seedKatalogPeran();
        $this->backfillDariLevel();
    }

    /** Peran generik (§8): TANPA nama desa/RW di dalamnya. */
    private function seedKatalogPeran(): void
    {
        $now = now();
        $katalog = [
            ['super_admin', 'Super Admin Platform', 'platform', 'superadmin'],
            ['desa_admin', 'Admin Desa', 'desa', 'ketua_rw'],
            ['rw_admin', 'Admin RW', 'rw', 'ketua_rw'],
            ['rw_finance', 'Bendahara RW', 'rw', 'bendahara'],
            ['rt_admin', 'Pengurus RT', 'rt', 'petugas_rt'],
            // Peran warga dipasang di organisasi RW tempat ia tinggal;
            // kepemilikan datanya sendiri tetap lewat users.keluarga_id.
            ['warga', 'Warga', 'rw', 'warga'],
        ];

        DB::table('roles')->insert(array_map(fn ($r) => [
            'slug' => $r[0], 'name' => $r[1], 'scope_type' => $r[2],
            'legacy_level' => $r[3], 'created_at' => $now, 'updated_at' => $now,
        ], $katalog));
    }

    /**
     * Petakan users.level existing ke assignment ber-scope. Kolom level TIDAK
     * dihapus: ia tetap fallback CheckRole selama transisi, jadi user tanpa
     * assignment (termasuk yang dibuat setelah migrasi ini) berperilaku
     * persis seperti sebelumnya.
     */
    private function backfillDariLevel(): void
    {
        $roleId = DB::table('roles')->pluck('id', 'slug');
        $platformId = DB::table('organizations')->where('slug', 'platform')->value('id');
        $rwId = DB::table('organizations')->where('slug', 'rw-10-sukakarya')->value('id');
        if (! $platformId || ! $rwId) {
            echo "  PERINGATAN: hierarki organisasi tidak ditemukan, backfill peran dilewati.\n";

            return;
        }

        // superadmin & admin hari ini adalah operator penuh sistem; padanannya
        // super_admin di platform. Pemilahan "admin tenant vs staf platform"
        // yang sesungguhnya adalah keputusan manusia, bukan tebakan migrasi.
        $peta = [
            'superadmin' => ['super_admin', $platformId],
            'admin' => ['super_admin', $platformId],
            'ketua_rw' => ['rw_admin', $rwId],
            'bendahara' => ['rw_finance', $rwId],
            'warga' => ['warga', $rwId],
        ];

        $now = now();
        foreach ($peta as $level => [$slug, $orgId]) {
            $userIds = DB::table('users')->where('level', $level)->pluck('id');
            foreach ($userIds as $uid) {
                DB::table('user_role_assignments')->insertOrIgnore([
                    'user_id' => $uid, 'role_id' => $roleId[$slug],
                    'organization_id' => $orgId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            echo "  {$level} -> {$slug}: {$userIds->count()} akun.\n";
        }

        // petugas_rt dipasang di organisasi RT-nya masing-masing.
        $tanpaRt = 0;
        $petugas = DB::table('users')->where('level', 'petugas_rt')->get(['id', 'username', 'rt']);
        foreach ($petugas as $u) {
            $rt = str_pad(trim((string) $u->rt), 2, '0', STR_PAD_LEFT);
            $rtOrgId = DB::table('organizations')
                ->where('slug', "rt-{$rt}-rw-10-sukakarya")->value('id');
            if (! $rtOrgId) {
                echo "  PERINGATAN: {$u->username} (RT '{$u->rt}') tidak punya organisasi RT; dilewati. TANPA assignment akun ini efektif warga - buka Manajemen Akun, isi RT-nya, dan simpan ulang levelnya.\n";
                $tanpaRt++;

                continue;
            }
            DB::table('user_role_assignments')->insertOrIgnore([
                'user_id' => $u->id, 'role_id' => $roleId['rt_admin'],
                'organization_id' => $rtOrgId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        echo '  petugas_rt -> rt_admin: '.($petugas->count() - $tanpaRt)." akun ({$tanpaRt} dilewati).\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('roles');
        Schema::rename('roles_legacy_pwa', 'roles');
    }
};
