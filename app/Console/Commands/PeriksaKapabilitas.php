<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Services\MatriksKapabilitas;
use Illuminate\Console\Command;

/**
 * Periksa tiap RW: adakah kemampuan yang TIDAK dipegang satu akun aktif pun?
 *
 * Dijalankan sesudah deploy pembagian tugas pengurus. Tanpa ini, RW yang belum
 * mengangkat sekretaris baru ketahuan saat warga mengeluh suratnya mengendap.
 * Membaca langsung dari database (bukan lewat tenant context) supaya bisa
 * dijalankan dari konsol tanpa hostname.
 */
class PeriksaKapabilitas extends Command
{
    protected $signature = 'izin:periksa {--rw= : Batasi ke satu slug organisasi RW}';

    protected $description = 'Periksa kemampuan yang belum dipegang siapa pun di tiap RW';

    public function handle(): int
    {
        $rws = Organization::where('type', Organization::TYPE_RW)
            ->when($this->option('rw'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('slug')->get();

        if ($rws->isEmpty()) {
            $this->warn('Tidak ada organisasi RW yang cocok.');

            return self::SUCCESS;
        }

        // Peta peran -> id, sekali saja (bukan query per RW).
        $levelPerRole = Role::pluck('legacy_level', 'id');
        $adaMasalah = false;

        foreach ($rws as $rw) {
            $relevan = array_unique(array_merge(
                $this->rantaiLeluhur($rw),
                Organization::idSubtree($rw->id)
            ));

            $peran = UserRoleAssignment::whereIn('organization_id', $relevan)
                ->whereIn('user_id', \App\Models\User::where('status', 'aktif')->select('id'))
                ->pluck('role_id')
                ->map(fn ($id) => $levelPerRole[$id] ?? null)
                ->filter()->unique()->values()->all();

            $dipegang = [];
            foreach ($peran as $satu) {
                foreach (MatriksKapabilitas::untukPeran($satu) as $kapabilitas) {
                    $dipegang[$kapabilitas] = true;
                }
            }

            // Yang diperiksa hanya pekerjaan PENGURUS. Kapabilitas khusus
            // superadmin (fitur platform, reset data) memang bukan urusan
            // pembagian tugas RW - ia melekat pada operator portal.
            $wajib = array_diff(MatriksKapabilitas::semua(), MatriksKapabilitas::KHUSUS_SUPERADMIN);
            $kosong = array_values(array_diff($wajib, array_keys($dipegang)));

            if ($kosong === []) {
                $this->info("OK  {$rw->slug}: semua kemampuan ada pemegangnya.");

                continue;
            }

            $adaMasalah = true;
            $this->warn("!!  {$rw->slug}: ".count($kosong).' kemampuan tanpa pemegang');
            foreach ($kosong as $kapabilitas) {
                $this->line('      - '.$kapabilitas.' ('.(MatriksKapabilitas::KATALOG[$kapabilitas]['label'] ?? '').')');
            }
            $this->line('      Angkat pengurus yang sesuai lewat Manajemen Akun portal RW itu.');
        }

        return $adaMasalah ? self::FAILURE : self::SUCCESS;
    }

    /** Rantai leluhur organisasi, termasuk dirinya (batas 10 = pagar siklik). */
    private function rantaiLeluhur(Organization $org): array
    {
        $petaInduk = Organization::pluck('parent_id', 'id');
        $rantai = [];
        $id = $org->id;
        for ($i = 0; $id !== null && $i < 10; $i++) {
            $rantai[] = $id;
            $id = $petaInduk[$id] ?? null;
        }

        return $rantai;
    }
}
