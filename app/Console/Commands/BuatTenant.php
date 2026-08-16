<?php

namespace App\Console\Commands;

use App\Services\PembukaTenant;
use Illuminate\Console\Command;

/**
 * Phase D: buka tenant baru (desa + RW + domain + admin) dalam satu perintah.
 * Logikanya di App\Services\PembukaTenant (dipakai juga halaman Manajemen
 * Desa); perintah ini hanya membungkusnya untuk terminal.
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

    public function handle(PembukaTenant $pembuka): int
    {
        try {
            $hasil = $pembuka->buka(
                (string) $this->argument('nama'),
                (string) $this->argument('label'),
                (string) $this->option('kecamatan'),
                (array) $this->option('rw'),
                (string) $this->option('basis'),
                ! $this->option('tanpa-admin'),
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Desa: {$hasil['desa']->name} (slug {$hasil['desa']->slug})");
        $this->table(
            ['RW', 'Alamat portal', 'Akun admin'],
            array_map(fn ($b) => [
                "RW {$b['rw']}",
                $b['hostname'],
                $b['username'] === null
                    ? '-'
                    : ($b['pin'] !== null
                        ? "{$b['username']} / PIN {$b['pin']} (GANTI setelah login pertama)"
                        : "{$b['username']} (sudah ada, PIN tidak diubah)"),
            ], $hasil['baris'])
        );
        $this->line('Langkah berikutnya per alamat: cPanel > Domains > Create a New Domain');
        $this->line('(document root = folder public aplikasi) lalu Run AutoSSL, dan setelah');
        $this->line('login: menu Pengaturan (identitas, alamat portal, tarif, WhatsApp API).');

        return self::SUCCESS;
    }
}
