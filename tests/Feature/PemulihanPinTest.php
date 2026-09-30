<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\PemulihanPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P0 pemulihan PIN publik.
 *
 * Dulu: nomor tak dikenal dijawab "tidak ditemukan" (enumerasi), PIN baru
 * DISIMPAN sebelum WA terkirim (gagal kirim = akun terkunci tanpa PIN),
 * PIN yang bisa dipakai ulang dikirim polos, tanpa pembatasan laju, dan
 * nomor + username tercatat utuh di log. Sekarang: kode sekali pakai 10
 * menit, PIN hanya berubah setelah kode terbukti, jawaban publik seragam.
 */
class PemulihanPinTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '6281234567890';

    private User $warga;

    /** @var list<string> */
    private array $log = [];

    /** Http::fake kedua tidak menimpa stub pertama, jadi gateway diatur lewat saklar. */
    private bool $gatewayGagal = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->aturMpwa();
        Http::fake(fn () => $this->gatewayGagal
            ? Http::response(['status' => false, 'message' => 'device offline'], 500)
            : Http::response(['status' => true, 'id' => 'msg1']));

        $this->warga = User::create([
            'user_id' => 'usr_pulih', 'username' => 'budipulih', 'namaLengkap' => 'Budi Pulih',
            'pin' => Hash::make('111111'), 'level' => 'warga', 'status' => 'aktif',
            'wa' => self::NOMOR, 'isDefault' => false,
        ]);

        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->log[] = $e->message.' '.json_encode($e->context);
        });
    }

    private function aturMpwa(): void
    {
        AppSetting::simpanRahasia('mpwa_api_key', 'kunci-uji-pemulihan');
        AppSetting::simpan('mpwa_sender', '6280000000001');
    }

    private function mintaKode(string $nomor = '081234567890', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/login/forgot', ['no_wa' => $nomor]);
    }

    private function verifikasi(string $kode, string $pin = '246810', string $nomor = '081234567890', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/login/forgot/verifikasi', [
            'no_wa' => $nomor, 'kode' => $kode,
            'pin_baru' => $pin, 'pin_baru_confirmation' => $pin,
        ]);
    }

    /** Kode 6 digit dari pesan WA terakhir yang dikirim ke gateway. */
    private function kodeTerkirim(): string
    {
        $pesan = null;
        Http::assertSent(function (PermintaanHttp $r) use (&$pesan) {
            $pesan = $r['message'] ?? null;

            return true;
        });
        $this->assertNotNull($pesan);
        $this->assertMatchesRegularExpression('/\b(\d{6})\b/', $pesan);
        preg_match('/\b(\d{6})\b/', $pesan, $m);

        return $m[1];
    }

    private function pinMasih(string $pin): bool
    {
        return Hash::check($pin, $this->warga->fresh()->pin);
    }

    // --- anti-enumerasi ------------------------------------------------------

    public function test_nomor_terdaftar_dan_tidak_terdaftar_dijawab_sama(): void
    {
        $dikenal = $this->mintaKode()->assertOk()->json();
        $asing = $this->mintaKode('089999999999', '10.0.0.2')->assertOk()->json();

        $this->assertSame($dikenal, $asing);
        $this->assertTrue($dikenal['success']);
        $this->assertSame('kode', $dikenal['langkah']);
        $this->assertStringNotContainsString('tidak ditemukan', strtolower($dikenal['message']));
    }

    public function test_verifikasi_nomor_tak_dikenal_dijawab_sama_dengan_kode_salah(): void
    {
        $this->mintaKode();
        $salah = $this->verifikasi('000000')->json();
        $asing = $this->verifikasi('000000', '246810', '089999999999', '10.0.0.9')->json();

        $this->assertSame($salah, $asing);
        $this->assertFalse($salah['success']);
    }

    // --- PIN tidak berubah sebelum kode terbukti ------------------------------

    public function test_minta_kode_tidak_mengubah_pin_dan_tidak_mengirim_pin(): void
    {
        $this->mintaKode()->assertOk();

        $this->assertTrue($this->pinMasih('111111'));
        Http::assertSent(fn (PermintaanHttp $r) => ! str_contains((string) $r['message'], 'PIN Baru')
            && ! str_contains((string) $r['message'], 'budipulih'));
    }

    public function test_pengiriman_gagal_pin_tetap_dan_kode_hangus(): void
    {
        $this->gatewayGagal = true;

        $jawaban = $this->mintaKode()->assertOk()->json();

        $this->assertTrue($jawaban['success'], 'jawaban publik tidak boleh membedakan gagal kirim');
        $this->assertTrue($this->pinMasih('111111'));
        $this->assertSame(0, PemulihanPin::whereNull('dipakai_pada')->count(), 'kode yang tak terkirim harus hangus');
    }

    // --- alur sukses -----------------------------------------------------------

    public function test_kode_benar_mengganti_pin_dan_bisa_login(): void
    {
        $this->warga->forceFill(['failed_login_count' => 7, 'locked_until' => now()->addMinutes(10)])->save();
        $this->mintaKode();

        $this->verifikasi($this->kodeTerkirim(), '246810')
            ->assertOk()
            ->assertJson(['success' => true, 'username' => 'budipulih']);

        $this->assertTrue($this->pinMasih('246810'));
        $this->assertSame(0, (int) $this->warga->fresh()->failed_login_count);
        $this->assertNull($this->warga->fresh()->locked_until);

        $this->post('/login', ['username' => 'budipulih', 'pin' => '246810'])->assertRedirect();
        $this->assertAuthenticatedAs($this->warga->fresh());
    }

    public function test_konfirmasi_pin_tidak_cocok_ditolak_tanpa_menghabiskan_kode(): void
    {
        $this->mintaKode();
        $kode = $this->kodeTerkirim();

        $this->postJson('/login/forgot/verifikasi', [
            'no_wa' => '081234567890', 'kode' => $kode,
            'pin_baru' => '246810', 'pin_baru_confirmation' => '999999',
        ])->assertStatus(422);

        $this->verifikasi($kode)->assertJson(['success' => true]);
    }

    // --- replay, kedaluwarsa, percobaan ---------------------------------------

    public function test_kode_tidak_bisa_dipakai_ulang(): void
    {
        $this->mintaKode();
        $kode = $this->kodeTerkirim();

        $this->verifikasi($kode, '246810')->assertJson(['success' => true]);
        $this->verifikasi($kode, '135790')->assertJson(['success' => false]);

        $this->assertTrue($this->pinMasih('246810'));
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        $this->mintaKode();
        $kode = $this->kodeTerkirim();

        $this->travel(11)->minutes();

        $this->verifikasi($kode)->assertJson(['success' => false]);
        $this->assertTrue($this->pinMasih('111111'));
    }

    public function test_lima_kode_salah_menghanguskan_kode(): void
    {
        $this->mintaKode();
        $kode = $this->kodeTerkirim();
        $salah = $kode === '000000' ? '000001' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->verifikasi($salah)->assertJson(['success' => false]);
        }

        $this->verifikasi($kode)->assertJson(['success' => false]);
        $this->assertTrue($this->pinMasih('111111'));
    }

    public function test_kode_baru_membatalkan_kode_lama(): void
    {
        $this->mintaKode();
        $lama = $this->kodeTerkirim();

        $this->mintaKode();
        $baru = $this->kodeTerkirim();

        if ($lama !== $baru) {
            $this->verifikasi($lama)->assertJson(['success' => false]);
        }
        $this->verifikasi($baru)->assertJson(['success' => true]);
    }

    // --- pembatasan laju --------------------------------------------------------

    public function test_permintaan_kode_dibatasi_per_nomor_lintas_ip(): void
    {
        foreach (['10.1.0.1', '10.1.0.2', '10.1.0.3'] as $ip) {
            $this->mintaKode('081234567890', $ip)->assertOk();
        }

        // Format nomor lain yang sama maknanya tetap dihitung satu identitas.
        $this->mintaKode('+62 812-3456-7890', '10.1.0.4')->assertStatus(429)->assertJson(['success' => false]);
    }

    public function test_permintaan_kode_dibatasi_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->mintaKode('08111111111'.$i, '10.2.0.1')->assertOk();
        }

        $this->mintaKode('081111111119', '10.2.0.1')->assertStatus(429);
    }

    public function test_jawaban_pembatasan_sama_untuk_nomor_dikenal_dan_asing(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->mintaKode('081234567890', "10.3.0.{$i}");
            $this->mintaKode('089999999999', "10.4.0.{$i}");
        }

        $this->assertSame(
            $this->mintaKode('081234567890', '10.3.0.9')->json(),
            $this->mintaKode('089999999999', '10.4.0.9')->json(),
        );
    }

    public function test_verifikasi_dibatasi_per_nomor(): void
    {
        $this->mintaKode();
        for ($i = 0; $i < 10; $i++) {
            $this->verifikasi('000000', '246810', '081234567890', "10.5.0.{$i}");
        }

        $this->verifikasi('000000', '246810', '081234567890', '10.5.0.99')->assertStatus(429);
    }

    // --- log -----------------------------------------------------------------------

    public function test_log_tidak_memuat_nomor_username_maupun_pin(): void
    {
        $this->mintaKode();
        $kode = $this->kodeTerkirim();
        $this->verifikasi($kode, '246810');

        $this->gatewayGagal = true;
        $this->mintaKode('081234567890', '10.6.0.1');

        $semua = implode("\n", array_merge($this->log, AuditLog::pluck('deskripsi')->all()));
        foreach ([self::NOMOR, '081234567890', 'budipulih', '246810', $kode] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $semua);
        }
    }
}
