<?php

namespace Tests\Feature;

use App\Models\IuranPadaringan;
use App\Models\IuranSampah;
use App\Models\Keluarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regresi: iuran yang dicatat lewat alur bayar menyimpan keluarga_id NUMERIK
 * (keluargas.id), tetapi Dashboard mencocokkannya dengan ID bisnis (kk_...).
 * Akibatnya setiap pembayaran yang sah tetap terhitung "menunggak" dan
 * tingkat penagihan selalu 0%.
 */
class DashboardIuranTest extends TestCase
{
    use RefreshDatabase;

    private function pengurus(): User
    {
        Http::fake();

        return $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_dash_iur', 'username' => 'dashiur', 'namaLengkap' => 'Bendahara',
            'pin' => Hash::make('123456'), 'level' => 'bendahara', 'status' => 'aktif',
        ]));
    }

    private function kk(string $id): Keluarga
    {
        return Keluarga::create([
            'keluarga_id' => $id, 'nama' => "KK {$id}", 'alamat' => 'x', 'rt' => '01',
            'status' => 'aktif', 'ikutSampah' => true, 'ikutPadaringan' => true,
        ]);
    }

    public function test_pembayaran_dengan_id_numerik_tidak_dihitung_menunggak(): void
    {
        $bulan = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'][now()->month - 1];
        $lunas = $this->kk('kk_lunas');
        $this->kk('kk_belum');

        // Alur bayar (TransaksiController): keluarga_id = id numerik.
        IuranSampah::create(['keluarga_id' => $lunas->id, 'tahun' => now()->year, 'weeks' => ["{$bulan}-M1" => 'lunas']]);
        IuranPadaringan::create(['keluarga_id' => $lunas->id, 'tahun' => now()->year, 'months' => [$bulan => true]]);

        $this->actingAs($this->pengurus())->get('/')
            ->assertOk()
            ->assertViewHas('tunggakanSampah', 1)
            ->assertViewHas('tunggakanPadaringan', 1);
    }
}
