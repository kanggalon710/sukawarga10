<?php

namespace Tests\Feature;

use App\Services\MatriksKapabilitas;
use Illuminate\Routing\Route as RuteLaravel;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Penjaga mekanis untuk aturan AGENTS.md "setiap rute yang mengubah data wajib
 * punya penjaga peran".
 *
 * Sebelumnya aturan itu hanya prosa + daftar manual di OtorisasiTest, dan
 * terbukti bolong: SELURUH modul keuangan dan MPWA tidak punya penjaga peran
 * sama sekali selama berbulan-bulan tanpa ada tes yang jatuh. Tes ini membaca
 * tabel rute apa adanya, jadi rute BARU yang lupa dijaga langsung ketahuan.
 */
class KapabilitasRuteTest extends TestCase
{
    /**
     * Rute pengubah data yang memang tidak dijaga kapabilitas, dengan alasannya.
     * Menambah baris di sini adalah keputusan sadar, bukan kelalaian.
     */
    private const TANPA_IZIN = [
        // Publik: belum ada yang login.
        'login.post' => 'form login',
        'login.register' => 'pendaftaran warga baru dari halaman login publik',
        'login.forgot' => 'lupa kredensial dari halaman login publik',
        'logout' => 'mengakhiri sesi sendiri',
        // Self-service: gerbangnya KEPEMILIKAN (users.keluarga_id), bukan peran.
        'akunSaya.simpan' => 'mengganti username/PIN sendiri dengan verifikasi PIN lama',
        'profil.update' => 'warga mengubah datanya sendiri',
        'profil.anggota.store' => 'warga menambah anggota keluarganya sendiri',
        'profil.anggota.destroy' => 'warga menghapus anggota keluarganya sendiri',
    ];

    /** @return list<RuteLaravel> */
    private function ruteWeb(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RuteLaravel $r) => in_array('web', $r->gatherMiddleware(), true)
        ));
    }

    private function argumenIzin(RuteLaravel $rute): array
    {
        $hasil = [];
        foreach ($rute->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'izin:')) {
                $hasil = array_merge($hasil, explode(',', substr($m, strlen('izin:'))));
            }
        }

        return $hasil;
    }

    public function test_setiap_rute_pengubah_data_dijaga_kapabilitas(): void
    {
        $bolong = [];

        foreach ($this->ruteWeb() as $rute) {
            $metode = array_intersect($rute->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
            if ($metode === []) {
                continue;
            }
            if (array_key_exists((string) $rute->getName(), self::TANPA_IZIN)) {
                continue;
            }
            if ($this->argumenIzin($rute) === []) {
                $bolong[] = implode('|', $metode).' /'.$rute->uri().' ('.($rute->getName() ?: 'tanpa nama').')';
            }
        }

        $this->assertSame([], $bolong,
            'Rute pengubah data tanpa middleware `izin:`. Tambahkan penjaganya, atau '
            ."daftarkan di KapabilitasRuteTest::TANPA_IZIN beserta alasannya.\n- "
            .implode("\n- ", $bolong));
    }

    public function test_setiap_argumen_izin_dikenal_katalog(): void
    {
        $asing = [];

        foreach ($this->ruteWeb() as $rute) {
            foreach ($this->argumenIzin($rute) as $kapabilitas) {
                if (! isset(MatriksKapabilitas::KATALOG[$kapabilitas])) {
                    $asing[] = $kapabilitas.' pada /'.$rute->uri();
                }
            }
        }

        $this->assertSame([], $asing, 'Kapabilitas tidak dikenal (salah ketik?): '.implode(', ', $asing));
    }

    public function test_rute_modul_tenant_tetap_berpasangan_dengan_feature_flag(): void
    {
        // Aturan AGENTS.md #11. Modul platform sengaja dikecualikan: ia bukan
        // modul tenant yang boleh dimatikan lewat feature flag.
        $menu = array_map(fn ($m) => $m['key'], getAllMenuItems());
        $bolong = [];

        foreach ($this->ruteWeb() as $rute) {
            foreach ($this->argumenIzin($rute) as $kapabilitas) {
                $modul = explode('.', $kapabilitas)[0];
                if (! in_array($modul, $menu, true)) {
                    continue;
                }
                $punyaFlag = (bool) array_filter(
                    $rute->gatherMiddleware(),
                    fn ($m) => is_string($m) && str_starts_with($m, 'fitur:')
                );
                if (! $punyaFlag) {
                    $bolong[] = '/'.$rute->uri().' butuh fitur:'.$modul;
                }
            }
        }

        $this->assertSame([], array_unique($bolong), implode(', ', array_unique($bolong)));
    }

    public function test_feature_flag_selalu_diperiksa_sebelum_izin(): void
    {
        // Kalau terbalik, modul yang dimatikan menjawab 403 dan justru
        // membocorkan keberadaannya; seharusnya 404.
        $salahUrut = [];

        foreach ($this->ruteWeb() as $rute) {
            $urut = array_values(array_filter(
                $rute->gatherMiddleware(),
                fn ($m) => is_string($m) && (str_starts_with($m, 'fitur:') || str_starts_with($m, 'izin:'))
            ));
            $posisiFitur = null;
            foreach ($urut as $i => $m) {
                if (str_starts_with($m, 'fitur:')) {
                    $posisiFitur = $i;
                } elseif ($posisiFitur === null && str_starts_with($m, 'izin:')) {
                    // `izin:` muncul sebelum `fitur:` mana pun.
                    if (array_filter($urut, fn ($x) => str_starts_with($x, 'fitur:'))) {
                        $salahUrut[] = '/'.$rute->uri();
                    }
                    break;
                }
            }
        }

        $this->assertSame([], array_unique($salahUrut), implode(', ', array_unique($salahUrut)));
    }

    public function test_kapabilitas_platform_hanya_di_rute_platform(): void
    {
        foreach ($this->ruteWeb() as $rute) {
            foreach ($this->argumenIzin($rute) as $kapabilitas) {
                if (! str_starts_with($kapabilitas, 'platform.')) {
                    continue;
                }
                $this->assertTrue(
                    str_starts_with($rute->uri(), 'tenant') || str_starts_with($rute->uri(), 'pembaruan'),
                    "Kapabilitas platform dipakai di rute tenant: /{$rute->uri()}"
                );
            }
        }
    }
}
