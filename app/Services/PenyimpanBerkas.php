<?php

namespace App\Services;

use App\Models\Keluarga;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur penyimpanan berkas unggahan (dokumen warga dan logo kop).
 *
 * Kenapa satu pintu: dulu tiga controller masing-masing memanggil store() ke
 * disk PUBLIK tanpa cek isi, sehingga scan KK bisa dibuka siapa pun lewat
 * /storage dan berkas .jpg berisi skrip diterima apa adanya. Aturannya kini
 * hidup di sini saja:
 *
 * - Jenis ditentukan dari ISI berkas (finfo), bukan dari nama atau MIME klien.
 * - Batas ukuran per jenis (gambar 8 MB, PDF 5 MB, logo 1 MB).
 * - Gambar DIKODE ULANG lewat GD: EXIF/GPS lokasi rumah terbuang, orientasi
 *   dibetulkan, sisi terpanjang dibatasi, dan muatan polyglot ikut hancur.
 * - Nama berkas UUID dengan ekstensi dari jenis hasil sniff. Nama asli
 *   dibuang karena sering memuat nama orang.
 * - Folder tujuan dari PROFIL yang ditulis tetap, tidak pernah dari request.
 * - Hapus hanya di dalam folder yang dikenal (cek realpath).
 */
class PenyimpanBerkas
{
    private const MB = 1048576;

    /** Profil unggahan: disk, folder, jenis => batas MB, sisi terpanjang gambar. */
    private const PROFIL = [
        'dokumen_warga' => [
            'disk' => 'local', 'folder' => 'dokumen-warga', 'sisiMaks' => 2000,
            'jenis' => ['image/jpeg' => 8, 'image/png' => 8, 'image/webp' => 8, 'application/pdf' => 5],
        ],
        'foto_warga' => [
            'disk' => 'local', 'folder' => 'dokumen-warga', 'sisiMaks' => 2000,
            'jenis' => ['image/jpeg' => 8, 'image/png' => 8, 'image/webp' => 8],
        ],
        // Logo kop tetap publik: dicetak di kop surat dan bukan data pribadi.
        // Tanpa SVG (bisa memuat skrip, dilayani same-origin dari /storage).
        'logo_kop' => [
            'disk' => 'public', 'folder' => 'kop', 'sisiMaks' => 800,
            'jenis' => ['image/png' => 1, 'image/jpeg' => 1, 'image/webp' => 1],
        ],
        // Merek aplikasi (Pengaturan > Tampilan): publik karena tampil di
        // halaman login dan favicon. Tanpa SVG, alasan sama dengan logo kop.
        'logo_aplikasi' => [
            'disk' => 'public', 'folder' => 'merek', 'sisiMaks' => 1024,
            'jenis' => ['image/png' => 1, 'image/jpeg' => 1, 'image/webp' => 1],
        ],
        'ikon_aplikasi' => [
            'disk' => 'public', 'folder' => 'merek', 'sisiMaks' => 512,
            'jenis' => ['image/png' => 1, 'image/webp' => 1],
        ],
    ];

    /**
     * Lokasi berkas warisan (sebelum 2026-09-30) yang masih boleh dibaca dan
     * dihapus: store('dokumen', 'public'), lalu dipindah perintah
     * dokumen:amankan ke disk privat dengan path relatif yang sama.
     */
    private const FOLDER_WARISAN = [
        'dokumen_warga' => [['local', 'dokumen'], ['public', 'dokumen']],
        'foto_warga' => [['local', 'dokumen'], ['public', 'dokumen']],
        'logo_kop' => [],
        'logo_aplikasi' => [],
        'ikon_aplikasi' => [],
    ];

    /** Kolom berkas pada tabel keluargas beserta profilnya. */
    public const KOLOM_KK = [
        'fotoKK' => 'dokumen_warga',
        'fotoRumah' => 'foto_warga',
        'dokumenPBB' => 'dokumen_warga',
    ];

    private const EKSTENSI = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
    ];

    /**
     * Batas piksel sebelum didekode: GD memakai +-4 byte per piksel, jadi
     * 25 MP = +-100 MB untuk salinan pertama. Foto ponsel biasa (12 MP) jauh
     * di bawahnya; mode 48/50 MP ditolak dengan pesan, bukan galat memori.
     */
    private const PIKSEL_MAKS = 25_000_000;

    /**
     * Periksa lalu simpan semua berkas yang dikirim untuk kolom-kolom ini.
     *
     * Semua berkas diperiksa DULU sebelum satu pun ditulis, dan bila penulisan
     * di tengah gagal, yang sudah tertulis dihapus lagi. Mengembalikan
     * [kolom => path baru] hanya untuk kolom yang memang mengirim berkas.
     *
     * @param  array<string, string>  $kolomProfil  kolom => nama profil
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function simpanDariRequest(Request $request, array $kolomProfil): array
    {
        $antrean = [];
        foreach ($kolomProfil as $kolom => $profil) {
            $berkas = $request->file($kolom);
            if (! $berkas instanceof UploadedFile) {
                continue;
            }
            $antrean[$kolom] = [$berkas, $profil, $this->periksa($berkas, $profil, $kolom)];
        }

        $tersimpan = [];
        try {
            foreach ($antrean as $kolom => [$berkas, $profil, $mime]) {
                $tersimpan[$kolom] = $this->tulis($berkas, $profil, $mime, $kolom);
            }
        } catch (\Throwable $e) {
            foreach ($tersimpan as $kolom => $path) {
                $this->hapus($path, $kolomProfil[$kolom]);
            }
            throw $e;
        }

        return $tersimpan;
    }

    /** Periksa dan simpan satu berkas; mengembalikan path relatif di disk profil. */
    public function simpan(UploadedFile $berkas, string $profil, string $kolom): string
    {
        return $this->tulis($berkas, $profil, $this->periksa($berkas, $profil, $kolom), $kolom);
    }

    /**
     * Simpan ikon persegi sebagai beberapa PNG berukuran tetap
     * (`<dasar>-<ukuran>.png`) dari SATU berkas yang sudah diperiksa.
     * Mengembalikan path dasar tanpa ukuran & ekstensi.
     *
     * @param  list<int>  $ukuran
     *
     * @throws ValidationException
     */
    public function simpanIkon(UploadedFile $berkas, array $ukuran, string $kolom): string
    {
        $profil = 'ikon_aplikasi';
        $p = self::profil($profil);
        $mime = $this->periksa($berkas, $profil, $kolom);

        [$lebar, $tinggi] = getimagesize($berkas->getRealPath());
        if (abs($lebar - $tinggi) > max(2, (int) round(0.02 * max($lebar, $tinggi)))) {
            $this->tolak($kolom, 'Ikon harus persegi (lebar sama dengan tinggi), minimal 512 x 512 piksel disarankan.');
        }

        $dasar = $p['folder'].'/'.Str::uuid();
        $tertulis = [];
        // Didekode SEKALI, lalu tiap ukuran di-resample dari gambar di memori:
        // mendekode ulang sumber untuk lima ukuran membayar CPU & memori 5x.
        $asal = $this->dekode($berkas->getRealPath(), $mime, $kolom, $p);
        try {
            foreach ($ukuran as $sisi) {
                $path = "{$dasar}-{$sisi}.png";
                $isi = $this->enkode($this->kecilkan($asal, $sisi, true, false), 'image/png', $kolom, $p);
                if (! Storage::disk($p['disk'])->put($path, $isi)) {
                    $this->tolak($kolom, 'Berkas tidak dapat disimpan. Coba lagi.');
                }
                $tertulis[] = $path;
            }
        } catch (\Throwable $e) {
            foreach ($tertulis as $path) {
                Storage::disk($p['disk'])->delete($path);
            }
            throw $e;
        } finally {
            imagedestroy($asal);
        }

        return $dasar;
    }

    /**
     * Hapus seluruh varian ikon hasil simpanIkon().
     *
     * @param  list<int>  $ukuran
     */
    public function hapusIkon(?string $dasar, array $ukuran): void
    {
        if (! is_string($dasar) || $dasar === '') {
            return;
        }
        foreach ($ukuran as $sisi) {
            $this->hapus("{$dasar}-{$sisi}.png", 'ikon_aplikasi');
        }
    }

    /**
     * Hapus berkas bila path-nya berada di folder yang dikenal profil ini.
     * Path di luar folder (termasuk upaya ../) diabaikan, tidak pernah dihapus.
     */
    public function hapus(?string $path, string $profil): bool
    {
        $lokasi = $this->temukan($path, $profil);
        if ($lokasi === null) {
            return false;
        }

        return Storage::disk($lokasi[0])->delete($path);
    }

    /**
     * Hapus berkas-berkas milik KK yang SUDAH dihapus dari database.
     *
     * Dipanggil setelah commit, supaya penghapusan yang dibatalkan tidak
     * meninggalkan baris yang menunjuk berkas hilang. Berkas yang masih
     * dirujuk baris KK lain (di tenant mana pun) dilewati.
     *
     * @param  iterable<Keluarga|array<string, mixed>>  $daftarKk
     */
    public function hapusBerkasKeluarga(iterable $daftarKk): int
    {
        $jumlah = 0;
        foreach ($daftarKk as $kk) {
            foreach (self::KOLOM_KK as $kolom => $profil) {
                $path = is_array($kk) ? ($kk[$kolom] ?? null) : $kk->{$kolom};
                if ($path && ! $this->masihDirujukKeluarga($path) && $this->hapus($path, $profil)) {
                    $jumlah++;
                }
            }
        }

        return $jumlah;
    }

    /**
     * Hapus berkas lama yang baru saja diganti, kecuali masih dirujuk baris lain.
     *
     * @param  array<string, ?string>  $pathLama  kolom => path lama
     */
    public function hapusYangDiganti(array $pathLama): void
    {
        foreach ($pathLama as $kolom => $path) {
            if ($path && ! $this->masihDirujukKeluarga($path)) {
                $this->hapus($path, self::KOLOM_KK[$kolom]);
            }
        }
    }

    /**
     * Path absolut untuk disajikan, atau null bila tidak ada / di luar folder.
     * Disk privat didahulukan; disk publik hanya untuk berkas warisan.
     */
    public function lokasiAbsolut(?string $path, string $profil): ?string
    {
        $lokasi = $this->temukan($path, $profil);

        return $lokasi[1] ?? null;
    }

    /** Jenis berkas dari isinya. */
    public function jenisIsi(string $pathAbsolut): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($pathAbsolut);
    }

    /** Apakah jenis ini termasuk yang diizinkan profil. */
    public function jenisDiizinkan(string $mime, string $profil): bool
    {
        return isset(self::profil($profil)['jenis'][$mime]);
    }

    /**
     * Semua lokasi [disk, folder] yang dikelola profil-profil dokumen warga,
     * untuk perintah pembersihan berkas yatim.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function folderDokumenWarga(): array
    {
        $hasil = [];
        foreach (array_unique(array_values(self::KOLOM_KK)) as $profil) {
            $p = self::profil($profil);
            $hasil[] = [$p['disk'], $p['folder']];
            foreach (self::FOLDER_WARISAN[$profil] as $lama) {
                $hasil[] = $lama;
            }
        }

        return array_values(array_unique($hasil, SORT_REGULAR));
    }

    // -------------------------------------------------------------------------

    /** @return array{disk: string, folder: string, sisiMaks: int, jenis: array<string, int>} */
    private static function profil(string $nama): array
    {
        if (! isset(self::PROFIL[$nama])) {
            throw new \InvalidArgumentException("Profil unggahan tidak dikenal: {$nama}");
        }

        return self::PROFIL[$nama];
    }

    /** @return string MIME hasil sniff yang sudah lolos seluruh pemeriksaan */
    private function periksa(UploadedFile $berkas, string $profil, string $kolom): string
    {
        $p = self::profil($profil);
        $batasTertinggi = max($p['jenis']);

        if (! $berkas->isValid()) {
            // Paling sering: melewati upload_max_filesize server.
            $this->tolak($kolom, "Berkas gagal diunggah. Pastikan ukurannya tidak lebih dari {$batasTertinggi} MB.");
        }

        $ukuran = (int) $berkas->getSize();
        if ($ukuran <= 0) {
            $this->tolak($kolom, 'Berkas kosong.');
        }
        if ($ukuran > $batasTertinggi * self::MB) {
            $this->tolak($kolom, "Ukuran berkas maksimal {$batasTertinggi} MB.");
        }

        $mime = $this->jenisIsi($berkas->getRealPath());
        if (! isset($p['jenis'][$mime])) {
            $this->tolak($kolom, $this->pesanJenis($p));
        }

        if ($ukuran > $p['jenis'][$mime] * self::MB) {
            $label = $mime === 'application/pdf' ? 'PDF' : 'gambar';
            $this->tolak($kolom, "Ukuran {$label} maksimal {$p['jenis'][$mime]} MB.");
        }

        if ($mime === 'application/pdf') {
            // finfo sudah bilang PDF; kepala berkas dicek juga supaya berkas
            // yang hanya MEMUAT penanda PDF di tengah tidak ikut lolos.
            $kepala = (string) file_get_contents($berkas->getRealPath(), false, null, 0, 5);
            if ($kepala !== '%PDF-') {
                $this->tolak($kolom, $this->pesanJenis($p));
            }

            return $mime;
        }

        $info = @getimagesize($berkas->getRealPath());
        if ($info === false || image_type_to_mime_type($info[2]) !== $mime) {
            $this->tolak($kolom, $this->pesanJenis($p));
        }
        if ($info[0] * $info[1] > self::PIKSEL_MAKS) {
            $this->tolak($kolom, 'Resolusi gambar terlalu besar (maks 25 megapiksel). Perkecil fotonya lalu unggah lagi.');
        }

        return $mime;
    }

    private function tulis(UploadedFile $berkas, string $profil, string $mime, string $kolom): string
    {
        $p = self::profil($profil);
        $path = $p['folder'].'/'.Str::uuid().'.'.self::EKSTENSI[$mime];

        $isi = $mime === 'application/pdf'
            ? (string) file_get_contents($berkas->getRealPath())
            : $this->kodeUlang($berkas->getRealPath(), $mime, $p['sisiMaks'], $kolom, $p);

        if (! Storage::disk($p['disk'])->put($path, $isi)) {
            Log::error('Penyimpanan unggahan gagal', ['profil' => $profil]);
            $this->tolak($kolom, 'Berkas tidak dapat disimpan. Coba lagi.');
        }

        return $path;
    }

    private function kodeUlang(string $sumber, string $mime, int $sisiMaks, string $kolom, array $p): string
    {
        // Diperkecil DULU ke kanvas truecolor transparan, baru diputar: memutar
        // gambar ukuran penuh menahan dua salinan besar di memori sekaligus.
        // imagecopyresampled ke kanvas beralfa juga menjaga transparansi PNG
        // berpalet (logo), yang hilang bila lewat imagescale.
        $gambar = $this->kecilkan($this->dekode($sumber, $mime, $kolom, $p), $sisiMaks);

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $gambar = $this->luruskanOrientasi($gambar, $sumber);
        }

        return $this->enkode($gambar, $mime, $kolom, $p);
    }

    private function dekode(string $sumber, string $mime, string $kolom, array $p): \GdImage
    {
        $gambar = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sumber),
            'image/png' => @imagecreatefrompng($sumber),
            'image/webp' => @imagecreatefromwebp($sumber),
        };
        if (! $gambar instanceof \GdImage) {
            $this->tolak($kolom, $this->pesanJenis($p));
        }

        return $gambar;
    }

    /** Kode ulang ke format keluaran; gambar dimusnahkan setelahnya. */
    private function enkode(\GdImage $gambar, string $mime, string $kolom, array $p): string
    {
        if ($mime !== 'image/jpeg') {
            imagealphablending($gambar, false);
            imagesavealpha($gambar, true);
        }

        ob_start();
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($gambar, null, 85),
            'image/png' => imagepng($gambar, null, 6),
            'image/webp' => imagewebp($gambar, null, 85),
        };
        $hasil = (string) ob_get_clean();
        imagedestroy($gambar);

        if (! $ok || $hasil === '') {
            $this->tolak($kolom, $this->pesanJenis($p));
        }

        return $hasil;
    }

    /** Salin ke kanvas truecolor beralfa, diperkecil bila melewati sisi terpanjang. */
    private function kecilkan(\GdImage $gambar, int $sisiMaks, bool $ukuranTepat = false, bool $musnahkanAsal = true): \GdImage
    {
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        // Ukuran tepat (favicon 16..512) boleh memperbesar; foto dan logo hanya diperkecil.
        $skala = $sisiMaks / max($lebar, $tinggi);
        if (! $ukuranTepat) {
            $skala = min(1, $skala);
        }
        $lebarBaru = max(1, (int) round($lebar * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        $kanvas = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagealphablending($kanvas, false);
        imagesavealpha($kanvas, true);
        imagefill($kanvas, 0, 0, imagecolorallocatealpha($kanvas, 0, 0, 0, 127));
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        if ($musnahkanAsal) {
            imagedestroy($gambar);
        }

        return $kanvas;
    }

    /** Foto ponsel sering tersimpan miring dengan tag Orientation; kode ulang membuang tag itu. */
    private function luruskanOrientasi(\GdImage $gambar, string $sumber): \GdImage
    {
        $exif = @exif_read_data($sumber);
        $sudut = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($sudut === 0) {
            return $gambar;
        }
        $putar = imagerotate($gambar, $sudut, 0);
        if (! $putar instanceof \GdImage) {
            return $gambar;
        }
        imagedestroy($gambar);

        return $putar;
    }

    /**
     * Cari [disk, path absolut] untuk path ini di folder milik profil, atau null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function temukan(?string $path, string $profil): ?array
    {
        if (! is_string($path) || $path === '' || str_contains($path, "\0")) {
            return null;
        }

        $p = self::profil($profil);
        $kandidat = array_merge([[$p['disk'], $p['folder']]], self::FOLDER_WARISAN[$profil]);

        foreach ($kandidat as [$disk, $folder]) {
            if (! str_starts_with($path, $folder.'/')) {
                continue;
            }
            $akar = realpath(Storage::disk($disk)->path($folder));
            $nyata = realpath(Storage::disk($disk)->path($path));
            if ($akar !== false && $nyata !== false && is_file($nyata)
                && str_starts_with($nyata, $akar.DIRECTORY_SEPARATOR)) {
                return [$disk, $nyata];
            }
        }

        return null;
    }

    /**
     * Masih dirujuk baris KK mana pun (lintas tenant, sengaja): berkas bersama
     * tidak boleh hilang hanya karena satu tenant menghapus barisnya.
     */
    private function masihDirujukKeluarga(string $path): bool
    {
        return Keluarga::withoutGlobalScope('organisasi')
            ->where(function ($q) use ($path) {
                foreach (array_keys(self::KOLOM_KK) as $kolom) {
                    $q->orWhere($kolom, $path);
                }
            })->exists();
    }

    private function pesanJenis(array $p): string
    {
        $nama = array_map(fn ($m) => ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP', 'application/pdf' => 'PDF'][$m], array_keys($p['jenis']));
        $akhir = array_pop($nama);

        return 'Berkas harus berupa '.($nama ? implode(', ', $nama).', atau ' : '').$akhir.'.';
    }

    /** @return never */
    private function tolak(string $kolom, string $pesan): void
    {
        throw ValidationException::withMessages([$kolom => $pesan]);
    }
}
