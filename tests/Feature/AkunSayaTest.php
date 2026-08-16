<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akun Saya: setiap user (termasuk kepala keluarga) bisa mengganti username
 * dan PIN-nya sendiri, dengan verifikasi PIN lama - bukan lewat pengurus.
 */
class AkunSayaTest extends TestCase
{
    use RefreshDatabase;

    private User $warga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warga = User::create([
            'user_id' => 'u_saya', 'username' => 'asepsaya', 'namaLengkap' => 'Asep Saya',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
        ]);
    }

    public function test_halaman_akun_saya_terbuka_untuk_user_login(): void
    {
        // Tamu dulu: actingAs menetap sepanjang tes.
        $this->get('/akun-saya')->assertRedirect();
        $this->actingAs($this->warga)->get('/akun-saya')
            ->assertOk()->assertSee('asepsaya');
    }

    public function test_ganti_username_dan_pin_dengan_pin_lama_benar(): void
    {
        $this->actingAs($this->warga)->post('/akun-saya', [
            'username' => 'asepbaru', 'pin_lama' => '123456', 'pin_baru' => '654321',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $segar = $this->warga->fresh();
        $this->assertSame('asepbaru', $segar->username);
        $this->assertTrue(Hash::check('654321', $segar->pin));
    }

    public function test_pin_lama_salah_ditolak_tanpa_mengubah_apa_pun(): void
    {
        $this->actingAs($this->warga)->post('/akun-saya', [
            'username' => 'asepbaru', 'pin_lama' => '000000', 'pin_baru' => '654321',
        ])->assertSessionHasErrors('pin_lama');

        $segar = $this->warga->fresh();
        $this->assertSame('asepsaya', $segar->username);
        $this->assertTrue(Hash::check('123456', $segar->pin));
    }

    public function test_username_terpakai_ditolak(): void
    {
        User::create([
            'user_id' => 'u_lain', 'username' => 'sudahada', 'namaLengkap' => 'Lain',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
        ]);

        $this->actingAs($this->warga)->post('/akun-saya', [
            'username' => 'sudahada', 'pin_lama' => '123456',
        ])->assertSessionHasErrors('username');
    }

    public function test_boleh_hanya_ganti_username_tanpa_pin_baru(): void
    {
        $this->actingAs($this->warga)->post('/akun-saya', [
            'username' => 'asepbaru', 'pin_lama' => '123456',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $segar = $this->warga->fresh();
        $this->assertSame('asepbaru', $segar->username);
        $this->assertTrue(Hash::check('123456', $segar->pin), 'PIN tidak berubah bila pin_baru kosong.');
    }
}
