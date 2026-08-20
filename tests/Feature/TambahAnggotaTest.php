<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regresi: `anggotas.anggota_id` NOT NULL + UNIQUE tanpa default, dan model
 * Anggota tidak punya hook creating. Dua jalur membuat anggota lewat relasi
 * `$kk->anggota()->create()` tanpa mengisi kolom itu, sehingga insert ditolak
 * database - form Tambah KK dengan anggota inline, dan menu Profil warga.
 *
 * Tiap kasus membuat DUA anggota: satu saja lolos lewat celah nilai kosong di
 * MySQL non-strict, yang kedua barulah melanggar UNIQUE.
 */
class TambahAnggotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function pengurus(): User
    {
        return $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sekre', 'username' => 'sekre',
            'namaLengkap' => 'Sekretaris Uji', 'pin' => Hash::make('123456'),
            'level' => 'sekretaris', 'status' => 'aktif', 'isDefault' => false,
        ]));
    }

    public function test_tambah_kk_dengan_dua_anggota_inline_tersimpan(): void
    {
        $this->actingAs($this->pengurus())->post('/warga', [
            'nama' => 'Bapak Uji',
            'rt' => '01',
            'alamat' => 'Jl. Uji No. 1',
            'jumlahAnggota' => 3,
            'anggota_nama' => ['Istri Uji', 'Anak Uji'],
            'anggota_jk' => ['P', 'L'],
            'anggota_status' => ['Istri', 'Anak'],
        ])->assertRedirect();

        $kk = Keluarga::where('nama', 'Bapak Uji')->firstOrFail();
        $anggota = Anggota::where('keluarga_id', $kk->keluarga_id)->get();

        $this->assertCount(2, $anggota, 'Kedua anggota inline harus tersimpan.');
        $this->assertCount(2, $anggota->pluck('anggota_id')->filter()->unique(),
            'anggota_id wajib terisi dan berbeda untuk tiap anggota.');
    }

    public function test_warga_menambah_dua_anggota_lewat_profil(): void
    {
        $kk = Keluarga::create([
            'keluarga_id' => 'kk_profil01', 'nama' => 'Bapak Profil',
            'alamat' => 'Jl. Profil No. 2', 'rt' => '02',
        ]);

        $warga = User::create([
            'user_id' => 'usr_warga', 'username' => 'warga1',
            'namaLengkap' => 'Bapak Profil', 'pin' => Hash::make('123456'),
            'level' => 'warga', 'status' => 'aktif', 'isDefault' => false,
            'keluarga_id' => $kk->keluarga_id,
        ]);

        foreach ([['Istri Profil', 'P', 'Istri'], ['Anak Profil', 'L', 'Anak']] as [$nama, $jk, $status]) {
            $this->actingAs($warga)->post('/profil/anggota', [
                'nama' => $nama, 'jenisKelamin' => $jk, 'statusKeluarga' => $status,
            ])->assertRedirect();
        }

        $anggota = Anggota::where('keluarga_id', $kk->keluarga_id)->get();

        $this->assertCount(2, $anggota, 'Warga harus bisa menambah lebih dari satu anggota.');
        $this->assertCount(2, $anggota->pluck('anggota_id')->filter()->unique(),
            'anggota_id wajib terisi dan berbeda untuk tiap anggota.');
    }
}
