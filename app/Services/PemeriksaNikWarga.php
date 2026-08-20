<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\Organization;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Satu-satunya pemeriksa No. KK / NIK di aplikasi ini.
 *
 * Sebelum kelas ini ada, tidak ada satu pun validasi keunikan identitas warga -
 * bahkan di dalam satu RW. Akibatnya orang yang sama bisa tercatat sebagai istri
 * di satu KK dan anak di KK lain, dan statistik jiwa ikut menggelembung tanpa
 * ada yang tahu. Kejadian nyata di RW 07 Bagendit, 2026-08-20.
 *
 * Tiga aturan yang membentuk kelas ini:
 *
 * 1. Kosong bukan salah. Pendataan lapangan memang bertahap; menolak KK yang
 *    NIK-nya belum dikumpulkan hanya memindahkan masalah ke petugas RT.
 * 2. Nilai yang TIDAK BERUBAH tidak diperiksa. Kalau tidak, 14 KK RW 07 yang
 *    identitasnya telanjur rusak jadi tidak bisa disunting sama sekali - termasuk
 *    untuk membetulkan RT-nya, yang justru pekerjaan yang sedang didorong.
 * 3. Pesannya menyebut KK pemilik. Aman, karena pencarian di kelas ini masih
 *    terbatas satu tenant (global scope Eloquent). Pencarian lintas tenant punya
 *    aturan privasi sendiri dan menyusul di rilis berikutnya.
 */
class PemeriksaNikWarga
{
    /**
     * Periksa NIK seseorang, baik kepala keluarga maupun anggota.
     *
     * NIK dicari di `keluargas` DAN `anggotas`, karena satu orang bisa salah
     * tercatat di kedua tabel sekaligus - itu persis bentuk duplikat yang paling
     * sering terjadi.
     *
     * @param  string|null  $lama  nilai tersimpan; sama = lewati pemeriksaan
     * @param  int|null  $kecualiKeluarga  id baris keluargas yang sedang disunting
     * @param  int|null  $kecualiAnggota  id baris anggotas yang sedang disunting
     * @return string|null pesan kesalahan, atau null bila aman
     */
    public static function periksaNik($baru, $lama = null, ?int $kecualiKeluarga = null, ?int $kecualiAnggota = null): ?string
    {
        if (self::tidakBerubah($baru, $lama)) {
            return null;
        }

        if (identitasRusak($baru)) {
            return self::pesanRusak('NIK', $baru);
        }

        $nik = normalisasiIdentitas($baru);
        if ($nik === null) {
            return null;   // kosong: boleh
        }

        $pemilik = self::pemilikNik($nik, $kecualiKeluarga, $kecualiAnggota);
        if ($pemilik) {
            return "NIK {$nik} sudah dipakai {$pemilik}.";
        }

        return self::pesanLintasTenant('NIK', $nik, self::rwLainPemakaiNik($nik, $kecualiKeluarga, $kecualiAnggota));
    }

    /**
     * Siapa yang sudah memakai NIK ini di tenant sekarang, sebagai label yang
     * bisa dibaca pengurus. null bila belum dipakai siapa pun.
     *
     * Dicari di dua tabel karena satu orang bisa tercatat sebagai kepala di satu
     * KK dan sebagai anggota di KK lain - bentuk duplikat yang paling sering.
     *
     * PERINGATAN: hasilnya memuat NAMA WARGA. Jangan pernah ditampilkan ke
     * halaman yang bisa dibuka tanpa login; pakai sudahDipakai() di sana.
     */
    public static function pemilikNik(string $nik, ?int $kecualiKeluarga = null, ?int $kecualiAnggota = null): ?string
    {
        $kk = Keluarga::where('nik', $nik)
            ->when($kecualiKeluarga, fn ($q) => $q->where('id', '!=', $kecualiKeluarga))
            ->first();
        if ($kk) {
            return "kepala keluarga {$kk->nama} (RT {$kk->rt})";
        }

        $anggota = Anggota::where('nik', $nik)
            ->when($kecualiAnggota, fn ($q) => $q->where('id', '!=', $kecualiAnggota))
            ->first();
        if ($anggota) {
            $induk = Keluarga::where('keluarga_id', $anggota->keluarga_id)->first();

            return $anggota->nama.($induk ? " di KK {$induk->nama}" : '');
        }

        return null;
    }

    /**
     * Versi tanpa nama siapa pun, untuk halaman yang terbuka tanpa login.
     * Pendaftaran warga baru memakai ini: cukup tahu NIK-nya sudah ada, tanpa
     * memberi tahu tamu siapa pemiliknya.
     */
    public static function sudahDipakai($nik, $noKK = null): bool
    {
        $nik = normalisasiIdentitas($nik);
        if ($nik !== null && self::pemilikNik($nik) !== null) {
            return true;
        }

        $noKK = normalisasiIdentitas($noKK);

        return $noKK !== null && Keluarga::where('noKK', $noKK)->exists();
    }

    /**
     * Periksa No. KK. Hanya dicari di `keluargas`: nomor kartu keluarga milik
     * rumah tangga, bukan milik orang, jadi tidak ada padanannya di `anggotas`.
     */
    public static function periksaNoKK($baru, $lama = null, ?int $kecualiKeluarga = null): ?string
    {
        if (self::tidakBerubah($baru, $lama)) {
            return null;
        }

        if (identitasRusak($baru)) {
            return self::pesanRusak('No. KK', $baru);
        }

        $noKK = normalisasiIdentitas($baru);
        if ($noKK === null) {
            return null;
        }

        $kk = Keluarga::where('noKK', $noKK)
            ->when($kecualiKeluarga, fn ($q) => $q->where('id', '!=', $kecualiKeluarga))
            ->first();
        if ($kk) {
            return "No. KK {$noKK} sudah dipakai keluarga {$kk->nama} (RT {$kk->rt}).";
        }

        return self::pesanLintasTenant('No. KK', $noKK, self::rwLainPemakaiNoKK($noKK, $kecualiKeluarga));
    }

    /**
     * Alasan menolak satu baris impor, atau null bila baris itu boleh masuk.
     *
     * Jalur massal SENGAJA tidak pernah menyebut lokasi. Satu unggahan CSV bisa
     * memuat ribuan baris, jadi kalau tiap baris menjawab "ada di Desa X RW Y",
     * satu klik menghasilkan peta tempat tinggal ribuan orang sekaligus - dan
     * kuota harian tidak menolong, karena semuanya terjadi dalam satu request.
     */
    public static function alasanTolakMassal($nik, $noKK = null): ?string
    {
        if (identitasRusak($nik) || identitasRusak($noKK)) {
            return 'No. KK atau NIK tidak sah';
        }

        $nik = normalisasiIdentitas($nik);
        if ($nik !== null) {
            if (self::pemilikNik($nik) !== null) {
                return 'NIK sudah dipakai warga lain di RW ini';
            }
            if (self::rwLainPemakaiNik($nik, null, null) !== null) {
                AuditLogService::log('nik_lintas_tenant', 'warga',
                    'Impor massal: NIK bersidik '.self::sidik($nik).' sudah terdaftar di portal lain');

                return 'NIK sudah terdaftar di portal desa lain';
            }
        }

        $noKK = normalisasiIdentitas($noKK);
        if ($noKK !== null && self::rwLainPemakaiNoKK($noKK, null) !== null) {
            return 'No. KK sudah terdaftar di portal desa lain';
        }

        return null;
    }

    /**
     * Berapa kali seorang pengurus boleh diberi tahu LOKASI sebuah NIK per hari.
     *
     * Ini bukan pembatas beban kerja: menyimpan data warga tetap tidak dibatasi.
     * Yang dibatasi hanya berapa kali portal mau menyebut "NIK ini ada di Desa X
     * RW Y". Tanpa batas itu, satu akun pengurus yang jatuh ke tangan orang lain
     * bisa dipakai menembak NIK satu per satu sampai jadi peta tempat tinggal
     * seluruh warga tiga desa.
     */
    private const KUOTA_LOKASI_HARIAN = 5;

    /**
     * Cari NIK di SELURUH tenant, lalu susun pesannya.
     *
     * Aturan privasi yang mengikat seluruh method ini:
     * - Yang boleh keluar hanya nama DESA dan RW. Tidak pernah nama orang,
     *   alamat, tanggal lahir, atau nomor HP. Query di bawah sengaja hanya
     *   mengambil `organization_id`, supaya kolom lain tidak mungkin ikut
     *   terbawa karena kelalaian di kemudian hari.
     * - Kalau kuota harian habis, pesannya turun jadi tanpa lokasi. Simpannya
     *   tetap ditolak, jadi pekerjaan tidak terhalang; yang hilang hanya
     *   kenyamanannya.
     * - Setiap temuan dicatat di Log Sistem dengan NIK ter-sidik, bukan mentah.
     */
    private static function pesanLintasTenant(string $label, string $nomor, ?Organization $rw): ?string
    {
        if (! $rw) {
            return null;
        }

        $bolehSebutLokasi = self::bolehSebutLokasi();

        AuditLogService::log(
            'nik_lintas_tenant',
            'warga',
            "{$label} bersidik ".self::sidik($nomor)." ditemukan di organisasi #{$rw->id}"
                .($bolehSebutLokasi ? '' : ' (kuota lokasi habis, lokasi tidak disebut)')
        );

        if (! $bolehSebutLokasi) {
            return "{$label} ini sudah terdaftar di portal desa lain. "
                .'Hubungi pengelola desa untuk memeriksanya.';
        }

        return "{$label} ini sudah terdaftar di {$rw->name}, ".self::namaDesa($rw).'. '
            .'Kalau warga ini memang pindah ke sini, mintakan pemindahan datanya ke pengelola desa, '
            .'jangan didaftarkan ulang.';
    }

    /** Organisasi RW pemakai NIK ini di tenant LAIN, atau null. */
    private static function rwLainPemakaiNik(string $nik, ?int $kecualiKeluarga, ?int $kecualiAnggota): ?Organization
    {
        $sekarang = self::organisasiSekarang();

        // withoutGlobalScope DISENGAJA (aturan AGENTS #9): pertanyaannya justru
        // "apakah NIK ini sudah dipakai di portal desa lain", yang mustahil
        // dijawab dari dalam scope tenant. Hanya organization_id yang diambil.
        $orgId = Keluarga::withoutGlobalScope('organisasi')
            ->where('nik', $nik)
            ->where('status', '!=', 'pindah')
            ->when($sekarang, fn ($q) => $q->where('organization_id', '!=', $sekarang))
            ->when($kecualiKeluarga, fn ($q) => $q->where('id', '!=', $kecualiKeluarga))
            ->value('organization_id');

        if (! $orgId) {
            // `anggotas` tidak punya organization_id sendiri; lokasinya dibaca
            // lewat keluarga induknya.
            $orgId = Anggota::withoutGlobalScope('organisasi')
                ->join('keluargas', 'keluargas.keluarga_id', '=', 'anggotas.keluarga_id')
                ->where('anggotas.nik', $nik)
                ->where('keluargas.status', '!=', 'pindah')
                ->when($sekarang, fn ($q) => $q->where('keluargas.organization_id', '!=', $sekarang))
                ->when($kecualiAnggota, fn ($q) => $q->where('anggotas.id', '!=', $kecualiAnggota))
                ->value('keluargas.organization_id');
        }

        return $orgId ? Organization::find($orgId) : null;
    }

    /** Organisasi RW pemakai No. KK ini di tenant LAIN, atau null. */
    private static function rwLainPemakaiNoKK(string $noKK, ?int $kecualiKeluarga): ?Organization
    {
        $sekarang = self::organisasiSekarang();

        // Lihat alasan withoutGlobalScope di rwLainPemakaiNik().
        $orgId = Keluarga::withoutGlobalScope('organisasi')
            ->where('noKK', $noKK)
            ->where('status', '!=', 'pindah')
            ->when($sekarang, fn ($q) => $q->where('organization_id', '!=', $sekarang))
            ->when($kecualiKeluarga, fn ($q) => $q->where('id', '!=', $kecualiKeluarga))
            ->value('organization_id');

        return $orgId ? Organization::find($orgId) : null;
    }

    private static function organisasiSekarang(): ?int
    {
        return app(TenantContext::class)->rw()?->id;
    }

    private static function namaDesa(Organization $rw): string
    {
        $desa = $rw->leluhur(Organization::TYPE_DESA);

        return $desa ? "Desa {$desa->name}" : 'desa lain';
    }

    /**
     * Sidik NIK untuk Log Sistem.
     *
     * Log tidak boleh berubah jadi kumpulan NIK mentah yang bisa dibaca siapa
     * pun yang punya akses Log Sistem. Sidiknya ber-kunci APP_KEY, bukan hash
     * polos: ruang NIK terlalu kecil, hash tanpa kunci bisa dibongkar dengan
     * mencoba seluruh kemungkinan tanggal lahir.
     */
    private static function sidik(string $nomor): string
    {
        return substr(hash_hmac('sha256', $nomor, (string) config('app.key')), 0, 12);
    }

    private static function bolehSebutLokasi(): bool
    {
        $userId = auth()->id();
        if (! $userId) {
            return false;   // tanpa login, lokasi tidak pernah disebut
        }

        $kunci = 'nik-lokasi:'.$userId;
        if (RateLimiter::tooManyAttempts($kunci, self::KUOTA_LOKASI_HARIAN)) {
            return false;
        }
        RateLimiter::hit($kunci, 86400);

        return true;
    }

    private static function tidakBerubah($baru, $lama): bool
    {
        return trim((string) $baru) === trim((string) $lama);
    }

    private static function pesanRusak(string $label, $nilai): string
    {
        $t = trim((string) $nilai);
        $jumlah = strlen(preg_replace('/\D/', '', $t));

        // Notasi ilmiah selalu berasal dari spreadsheet yang menyimpan nomor
        // sebagai angka. Sebut penyebabnya, supaya tidak diketik ulang lalu
        // rusak lagi dengan cara yang sama.
        if (preg_match('/[eE]\s*\+?\s*\d/', $t) || str_contains($t, '.')) {
            return "{$label} \"{$t}\" rusak karena pernah disimpan sebagai angka di spreadsheet, "
                .'sehingga digitnya hilang. Salin ulang dari kartu aslinya, dan ubah dulu format '
                .'kolomnya jadi Teks biasa.';
        }

        if (preg_match('/[^0-9\s-]/', $t)) {
            return "{$label} \"{$t}\" memuat karakter yang bukan angka.";
        }

        return "{$label} harus 16 digit, yang diisi {$jumlah} digit.";
    }
}
