<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B1 multi-tenant (lihat .ai/AUDIT-MULTITENANT.md bagian 10).
 *
 * Additive murni: membuat hierarki organisasi + pemetaan domain dan mengisi
 * keadaan existing (Platform > Desa Sukakarya > RW 10 > RT dari data nyata).
 * BELUM ADA kode aplikasi yang membacanya, jadi perilaku produksi tidak
 * berubah; tabel ini fondasi untuk fase B2 (tenant context) dan seterusnya.
 *
 * Seed dilakukan DI DALAM migrasi, bukan di DatabaseSeeder, karena DEPLOY.md
 * menjadikan db:seed opsional di produksi ("bila perlu") - kalau hierarki
 * dititip di seeder, produksi bisa selesai migrate tanpa pernah punya
 * organisasi, dan fase berikutnya jatuh. Preseden pola ini: backfill
 * users.keluarga_id (migrasi 2026_08_15_000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            // nullOnDelete, bukan cascade: menghapus induk tidak boleh
            // diam-diam melenyapkan seluruh sub-organisasi beserta rujukannya.
            $table->foreignId('parent_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('type');   // platform | desa | rw | rt
            $table->string('name');
            $table->string('code');   // dipakai untuk nomor surat dsb, mis. RW10
            $table->string('slug')->unique();
            $table->string('status')->default('aktif');
            $table->timestamps();

            $table->index('type');
            // parent_id sudah ter-index oleh constrained() di MySQL, tapi
            // TIDAK di SQLite (lingkungan tes) - index eksplisit menyamakan.
            $table->index('parent_id');
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        $this->seedHierarkiExisting();
    }

    /**
     * Petakan keadaan tunggal saat ini ke hierarki organisasi.
     * Idempoten tidak diperlukan (tabel baru dibuat di atas), tapi nilai
     * diambil dari data nyata supaya benar di produksi maupun instalasi baru.
     */
    private function seedHierarkiExisting(): void
    {
        $now = now();
        $baris = fn (?int $parent, string $type, string $name, string $code, string $slug) => [
            'parent_id' => $parent, 'type' => $type, 'name' => $name,
            'code' => $code, 'slug' => $slug, 'status' => 'aktif',
            'created_at' => $now, 'updated_at' => $now,
        ];

        $platformId = DB::table('organizations')->insertGetId(
            $baris(null, 'platform', 'Jabnet', 'PLATFORM', 'platform')
        );
        $desaId = DB::table('organizations')->insertGetId(
            $baris($platformId, 'desa', 'Sukakarya', 'SUKAKARYA', 'sukakarya')
        );
        $rwId = DB::table('organizations')->insertGetId(
            $baris($desaId, 'rw', 'RW 10', 'RW10', 'rw-10-sukakarya')
        );

        // RT dari data nyata (keluargas + users), bukan daftar tebakan.
        // Di instalasi baru kedua tabel kosong sehingga tidak ada RT dibuat;
        // RT untuk data yang masuk belakangan diurus Phase C.
        $daftarRt = DB::table('keluargas')->whereNotNull('rt')->where('rt', '!=', '')
            ->distinct()->pluck('rt')
            ->merge(
                DB::table('users')->whereNotNull('rt')->where('rt', '!=', '')
                    ->distinct()->pluck('rt')
            )
            // Normalisasi '1'/'01' ke dua digit supaya tidak lahir RT kembar.
            ->map(fn ($rt) => str_pad(trim((string) $rt), 2, '0', STR_PAD_LEFT))
            ->unique()->sort()->values();

        foreach ($daftarRt as $rt) {
            DB::table('organizations')->insert(
                $baris($rwId, 'rt', "RT {$rt}", "RT{$rt}", "rt-{$rt}-rw-10-sukakarya")
            );
        }

        // Domain yang benar-benar mengarah ke aplikasi ini hari ini.
        // desa.jabnet.id SENGAJA tidak dimasukkan: DNS-nya belum ada, dan di
        // arsitektur target belum diputuskan ia milik level desa atau RW.
        DB::table('domains')->insert([
            [
                'organization_id' => $rwId, 'hostname' => 'paru.jabnet.id',
                'is_primary' => true, 'status' => 'aktif',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                // Deployment lama; bila hostname ini sampai ke aplikasi,
                // resolver (Phase B2) harus mengarah ke RW 10, bukan 404.
                'organization_id' => $rwId, 'hostname' => 'sukawarga10.jabnet.id',
                'is_primary' => false, 'status' => 'legacy',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        echo '  Organisasi: platform/desa/rw + '.count($daftarRt)
            .' RT ('.$daftarRt->implode(', ')."), 2 domain.\n";
    }

    public function down(): void
    {
        // Urutan anak dulu: domains merujuk organizations.
        Schema::dropIfExists('domains');
        Schema::dropIfExists('organizations');
    }
};
