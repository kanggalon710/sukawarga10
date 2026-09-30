<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * P0 keluaran galat: pesan exception database (SQL beserta nilai data warga),
 * path absolut server, dan keluaran shell dulu diteruskan mentah ke browser.
 * Sekarang pengguna menerima kode rujukan; detailnya ke log server dalam
 * bentuk tersamarkan.
 */
class KeluaranGalatTest extends TestCase
{
    use RefreshDatabase;

    /** Pesan exception tiruan yang memuat semua hal yang tidak boleh bocor. */
    private const PESAN_BOCOR = 'SQLSTATE[23000]: Integrity constraint violation (Connection: mysql, SQL: '
        .'delete from keluargas where nik = 3205111122223333) at /home/jabnet/public_html/app/Models/X.php';

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->log[] = $e->message.' '.json_encode($e->context);
        });
    }

    private function superadmin(): User
    {
        return $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_galat', 'username' => 'galatadmin', 'namaLengkap' => 'Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    private function gagalkanSql(string $potongan): void
    {
        DB::connection()->beforeExecuting(function (string $sql) use ($potongan) {
            if (str_contains($sql, $potongan)) {
                throw new \RuntimeException(self::PESAN_BOCOR);
            }
        });
    }

    private function assertTidakBocor(string $teks): void
    {
        foreach (['SQLSTATE', '/home/jabnet', '3205111122223333', 'delete from'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $teks);
        }
    }

    private function assertLogTersamarkan(): void
    {
        $gabungan = implode("\n", $this->log);
        $this->assertStringContainsString('ERR-', $gabungan, 'detail tetap tercatat di log dengan kode rujukan');
        $this->assertStringNotContainsString('3205111122223333', $gabungan);
        $this->assertStringNotContainsString('/home/jabnet', $gabungan);
    }

    public function test_reset_data_gagal_tidak_menampilkan_pesan_database(): void
    {
        $this->gagalkanSql('delete from "keluargas"');

        $this->actingAs($this->superadmin())->post('/pengaturan/reset-data', ['confirm' => 'RESET'])
            ->assertRedirect()->assertSessionHas('error');

        $pesan = session('error');
        $this->assertTidakBocor($pesan);
        $this->assertMatchesRegularExpression('/ERR-[A-Z0-9]{6}/', $pesan);
        $this->assertLogTersamarkan();
    }

    public function test_hapus_duplikat_gagal_tidak_menampilkan_pesan_database(): void
    {
        Keluarga::create(['keluarga_id' => 'kk_d1', 'nama' => 'Kembar', 'alamat' => 'x', 'rt' => '01']);
        Keluarga::create(['keluarga_id' => 'kk_d2', 'nama' => 'Kembar', 'alamat' => 'x', 'rt' => '01']);
        $this->gagalkanSql('delete from "keluargas"');

        $this->actingAs($this->superadmin())->post('/pengaturan/remove-duplicates')
            ->assertSessionHas('error');

        $this->assertTidakBocor(session('error'));
        $this->assertLogTersamarkan();
    }

    public function test_impor_gagal_tidak_menampilkan_pesan_database(): void
    {
        $this->gagalkanSql('insert into "keluargas"');
        $csv = UploadedFile::fake()->createWithContent('kk.csv', "Nama KK,RT,Alamat\nBudi Uji,01,Kp. Uji\n");

        $this->actingAs($this->superadmin())->post('/warga/import/keluarga', ['file_keluarga' => $csv])
            ->assertSessionHas('error');

        $this->assertTidakBocor(session('error'));
        $this->assertLogTersamarkan();
    }

    public function test_broadcast_gagal_tidak_meneruskan_pesan_exception(): void
    {
        AppSetting::simpanRahasia('mpwa_api_key', 'kunci-uji');
        AppSetting::simpan('mpwa_sender', '6280000000001');
        Keluarga::create(['keluarga_id' => 'kk_b1', 'nama' => 'Penerima', 'alamat' => 'x', 'rt' => '01', 'noHP' => '6281200000001', 'status' => 'aktif']);
        Http::fake(fn () => throw new ConnectionException('cURL error 7: connect to 10.0.0.5 port 443 via /home/jabnet/proxy failed'));

        $res = $this->actingAs($this->superadmin())->postJson('/mpwa/broadcast', [
            'pesan' => 'Uji', 'target' => 'semua', 'sender' => '6280000000001',
        ]);

        $res->assertOk()->assertJson(['failed' => 1]);
        $this->assertStringNotContainsString('cURL', $res->getContent());
        $this->assertStringNotContainsString('/home/jabnet', $res->getContent());
    }

    public function test_pembaruan_gagal_tidak_menampilkan_keluaran_shell(): void
    {
        $admin = User::create([
            'user_id' => 'u_upd_g', 'username' => 'updgalat', 'namaLengkap' => 'Admin Pembaruan',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $admin->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);

        $bocor = "fatal: could not read from https://kanggalon:ghp_TOKENRAHASIA@github.com/x.git\n"
            .'error: cannot open /home/jabnet/public_html/.git/FETCH_HEAD';
        Process::fake([
            'git diff*' => Process::result(''),
            'git pull*' => Process::result(output: 'Updating ab12..cd34', errorOutput: $bocor, exitCode: 1),
            'git log HEAD..*' => Process::result("ee55 Rilis baru\n"),
            'git log*' => Process::result("ab12cd3|2026-08-16|Rilis\n"),
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("1\n"),
        ]);

        $this->actingAs($admin)->post('/pembaruan/jalankan')->assertRedirect()->assertSessionHas('error');
        $html = $this->actingAs($admin)->get('/pembaruan')->assertOk()->getContent();

        foreach (['ghp_TOKENRAHASIA', '/home/jabnet', 'FETCH_HEAD', 'Updating ab12'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $html);
        }
        $this->assertStringContainsString('git pull', $html, 'nama langkah yang gagal tetap ditampilkan');

        $gabungan = implode("\n", $this->log);
        $this->assertStringContainsString('cannot open', $gabungan, 'keluaran lengkap tetap tercatat di log server');
        $this->assertStringNotContainsString('ghp_TOKENRAHASIA', $gabungan);
        $this->assertStringNotContainsString('/home/jabnet', $gabungan);
    }
}
