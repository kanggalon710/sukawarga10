<?php

namespace Tests\Feature;

use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pembatasan upaya login: maksimal 3 percobaan gagal, setelah itu diblokir
 * sementara meskipun kredensial berikutnya benar (permintaan pemilik,
 * sebelumnya 5 percobaan).
 */
class BatasUpayaLoginTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'cibunar-rw01.desa.jabnet.id';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01'],
        ])->assertSuccessful();

        $kk = new Keluarga([
            'keluarga_id' => 'kk_asep01', 'nama' => 'Asep Sunandar',
            'alamat' => 'Jl. Cibunar', 'rt' => '01', 'status' => 'aktif',
        ]);
        $kk->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
        $kk->saveQuietly();

        User::create([
            'user_id' => 'u_asep', 'username' => 'asep', 'namaLengkap' => 'Asep Sunandar',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
            'keluarga_id' => 'kk_asep01',
        ]);
    }

    private function coba(string $pin)
    {
        return $this->post('https://'.self::HOST.'/login', [
            'username' => 'asep', 'pin' => $pin,
        ]);
    }

    public function test_login_terblokir_setelah_tiga_kali_gagal(): void
    {
        foreach (range(1, 3) as $i) {
            $this->coba('000000')->assertRedirect();
            $this->assertGuest();
        }

        // Percobaan ke-4 DENGAN PIN benar tetap diblokir sementara.
        $this->coba('123456')->assertRedirect();
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan', (string) session('error'));
    }

    public function test_login_benar_masih_bisa_sebelum_batas_tercapai(): void
    {
        $this->coba('000000')->assertRedirect();
        $this->coba('000000')->assertRedirect();
        $this->assertGuest();

        $this->coba('123456');
        $this->assertAuthenticated();
    }
}
