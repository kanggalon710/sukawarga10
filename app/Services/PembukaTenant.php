<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\Hash;

/**
 * Satu-satunya tempat logika pembukaan tenant (Phase D/G): organisasi desa +
 * RW + baris domain + akun admin per RW. Dipakai perintah `tenant:buat` DAN
 * halaman Manajemen Desa - jangan tulis ulang aturannya di tempat lain.
 *
 * Idempotent: firstOrCreate di semua lapisan; akun admin yang sudah ada
 * dilewati tanpa reset PIN. PIN acak hanya dikembalikan pada pemanggilan yang
 * membuatnya - tidak pernah disimpan selain sebagai hash.
 */
class PembukaTenant
{
    public const POLA_LABEL = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/';

    /**
     * @param  list<string>  $daftarRw  Nomor RW mentah ('1', '01', boleh duplikat)
     * @return array{desa: Organization, baris: list<array{rw: string, hostname: string, username: ?string, pin: ?string}>}
     *
     * @throws \InvalidArgumentException bila label/daftar RW tidak sah.
     */
    public function buka(
        string $nama,
        string $label,
        string $kecamatan,
        array $daftarRw,
        string $basis = 'desa.jabnet.id',
        bool $buatAdmin = true,
    ): array {
        $label = strtolower(trim($label));
        $basis = strtolower(trim($basis));

        // Label jadi bagian hostname: batasi ke huruf/angka/strip ala DNS.
        if (! preg_match(self::POLA_LABEL, $label)) {
            throw new \InvalidArgumentException(
                "Label '{$label}' tidak sah: pakai huruf kecil/angka/strip, mis. cibunar atau cibunar-kota."
            );
        }

        $daftarRw = collect($daftarRw)
            ->flatMap(fn ($v) => explode(',', (string) $v))
            ->map(fn ($v) => trim($v))
            ->filter()
            // Normalisasi dua digit, konsisten dengan seed migrasi B1.
            ->map(fn ($v) => str_pad($v, 2, '0', STR_PAD_LEFT))
            ->unique()->sort()->values();

        if ($daftarRw->isEmpty()) {
            throw new \InvalidArgumentException('Sebutkan minimal satu RW, mis. 01,02,03.');
        }

        $platformId = Organization::where('slug', 'platform')->value('id');
        if ($platformId === null) {
            throw new \InvalidArgumentException('Organisasi platform tidak ditemukan; jalankan migrasi dulu.');
        }

        $nama = trim($nama);
        $kecamatan = trim($kecamatan);
        $namaLengkap = $kecamatan !== '' ? "{$nama} ({$kecamatan})" : $nama;

        $desa = Organization::where('slug', $label)->first();
        if ($desa !== null) {
            // Salah ketik nama saat menambah RW tidak boleh diam-diam
            // menempelkan RW ke desa lain.
            if (strcasecmp($desa->name, $namaLengkap) !== 0) {
                throw new \InvalidArgumentException(
                    "Label '{$label}' sudah dipakai {$desa->name}. Samakan nama & kecamatannya untuk menambah RW, atau pakai label lain untuk desa baru."
                );
            }
        } else {
            // Desa yang sama tidak boleh terdaftar dua kali di bawah label
            // lain: dua "Desa Cibunar (Tarogong Kidul)" itu duplikat.
            $kembar = Organization::where('type', Organization::TYPE_DESA)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($namaLengkap)])
                ->first();
            if ($kembar !== null) {
                throw new \InvalidArgumentException(
                    "{$namaLengkap} sudah terdaftar dengan label '{$kembar->slug}'. Tambahkan RW lewat label itu, atau bedakan nama/kecamatannya."
                );
            }

            $desa = Organization::create([
                'parent_id' => $platformId, 'type' => Organization::TYPE_DESA,
                'name' => $namaLengkap, 'slug' => $label,
                'code' => strtoupper($label), 'status' => 'aktif',
            ]);
        }

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

            $username = null;
            $pin = null;
            if ($buatAdmin) {
                $username = "{$label}-rw{$rw}";
                $admin = User::where('username', $username)->first();
                if ($admin === null) {
                    // PIN acak dikembalikan SEKALI; tersimpan hanya sebagai hash.
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
                }

                UserRoleAssignment::firstOrCreate([
                    'user_id' => $admin->id,
                    'role_id' => $roleRwAdmin,
                    'organization_id' => $orgRw->id,
                ]);
            }

            $baris[] = ['rw' => (string) $rw, 'hostname' => $hostname, 'username' => $username, 'pin' => $pin];
        }

        return ['desa' => $desa, 'baris' => $baris];
    }
}
