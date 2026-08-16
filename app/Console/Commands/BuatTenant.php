<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Phase D: buka tenant baru (desa + RW + domain + admin) dalam satu perintah.
 *
 * Idempotent: firstOrCreate di semua lapisan, jadi aman diulang untuk
 * menambah RW ke desa yang sudah ada. Akun admin yang sudah ada dilewati
 * tanpa reset PIN. Skema subdomain flat `{label}-rw{nn}.{basis}` mengikuti
 * keputusan di .ai/DECISIONS.md (satu wildcard DNS *.{basis} menutup semua
 * tenant; AutoSSL cPanel per subdomain).
 */
class BuatTenant extends Command
{
    protected $signature = 'tenant:buat
        {nama : Nama tampilan desa, mis. "Desa Cibunar"}
        {label : Label domain huruf kecil tanpa spasi, mis. cibunar}
        {--kecamatan= : Nama kecamatan, jadi pembeda di nama organisasi}
        {--rw=* : Nomor RW (boleh dipisah koma), mis. --rw=01,02,03}
        {--basis=desa.jabnet.id : Domain induk subdomain tenant}
        {--tanpa-admin : Jangan buatkan akun admin per RW}';

    protected $description = 'Buka tenant baru: organisasi desa + RW + domain + akun admin RW';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        $label = strtolower(trim((string) $this->argument('label')));
        $basis = strtolower(trim((string) $this->option('basis')));
        $kecamatan = trim((string) $this->option('kecamatan'));

        // Label jadi bagian hostname: batasi ke huruf/angka/strip ala DNS.
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
            $this->error("Label '{$label}' tidak sah: pakai huruf kecil/angka/strip, mis. cibunar atau cibunar-kota.");

            return self::FAILURE;
        }

        $daftarRw = collect($this->option('rw'))
            ->flatMap(fn ($v) => explode(',', (string) $v))
            ->map(fn ($v) => trim($v))
            ->filter()
            // Normalisasi dua digit, konsisten dengan seed migrasi B1.
            ->map(fn ($v) => str_pad($v, 2, '0', STR_PAD_LEFT))
            ->unique()->sort()->values();

        if ($daftarRw->isEmpty()) {
            $this->error('Sebutkan minimal satu RW, mis. --rw=01,02,03.');

            return self::FAILURE;
        }

        $platformId = Organization::where('slug', 'platform')->value('id');
        if ($platformId === null) {
            $this->error('Organisasi platform tidak ditemukan; jalankan migrasi dulu.');

            return self::FAILURE;
        }

        $desa = Organization::firstOrCreate(
            ['slug' => $label],
            [
                'parent_id' => $platformId, 'type' => Organization::TYPE_DESA,
                'name' => $kecamatan !== '' ? "{$nama} ({$kecamatan})" : $nama,
                'code' => strtoupper($label), 'status' => 'aktif',
            ]
        );
        $this->info("Desa: {$desa->name} (slug {$desa->slug})");

        $roleRwAdmin = Role::where('slug', 'rw_admin')->value('id');
        $baris = [];

        foreach ($daftarRw as $rw) {
            $orgRw = Organization::firstOrCreate(
                ['slug' => "rw-{$rw}-{$label}"],
                [
                    'parent_id' => $desa->id, 'type' => Organization::TYPE_RW,
                    'name' => "RW {$rw}", 'code' => strtoupper("{$label}-RW{$rw}"),
                    'status' => 'aktif',
                ]
            );

            $hostname = "{$label}-rw{$rw}.{$basis}";
            Domain::firstOrCreate(
                ['hostname' => $hostname],
                ['organization_id' => $orgRw->id, 'is_primary' => true, 'status' => 'aktif']
            );

            $infoAdmin = '-';
            if (! $this->option('tanpa-admin')) {
                $username = "{$label}-rw{$rw}";
                $admin = User::where('username', $username)->first();
                if ($admin === null) {
                    // PIN acak dicetak SEKALI; tidak disimpan di mana pun selain hash.
                    $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $admin = User::create([
                        'user_id' => 'USR-'.uniqid(),
                        'username' => $username,
                        'namaLengkap' => "Admin RW {$rw} {$nama}",
                        'pin' => Hash::make($pin),
                        'level' => 'ketua_rw',
                        'status' => 'aktif',
                        'isDefault' => false,
                    ]);
                    $infoAdmin = "{$username} / PIN {$pin} (GANTI setelah login pertama)";
                } else {
                    $infoAdmin = "{$username} (sudah ada, PIN tidak diubah)";
                }

                UserRoleAssignment::firstOrCreate([
                    'user_id' => $admin->id,
                    'role_id' => $roleRwAdmin,
                    'organization_id' => $orgRw->id,
                ]);
            }

            $baris[] = [$orgRw->name, $hostname, $infoAdmin];
        }

        $this->table(['RW', 'Alamat portal', 'Akun admin'], $baris);
        $this->line('Langkah berikutnya per alamat: cPanel > Domains > Create a New Domain');
        $this->line('(document root = folder public aplikasi) lalu Run AutoSSL, dan setelah');
        $this->line('login: menu Pengaturan (identitas, alamat portal, tarif, WhatsApp API).');

        return self::SUCCESS;
    }
}
