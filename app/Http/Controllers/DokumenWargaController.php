<?php

namespace App\Http\Controllers;

use App\Models\Keluarga;
use App\Services\PenyimpanBerkas;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Penyaji dokumen warga (scan KK, foto rumah, PBB) dari disk privat.
 *
 * Pengecualian sadar atas konvensi "controller gemuk tanpa kelas tambahan":
 * dokumen ini dulu dilayani langsung dari /storage tanpa izin apa pun, dan
 * dua pintu baca (pengurus dan warga pemilik) harus memakai header yang sama
 * persis. Satu controller kecil menjaga keduanya tetap seragam.
 */
class DokumenWargaController extends Controller
{
    /** Pengurus berizin warga.lihat; KK tenant lain sudah 404 oleh global scope. */
    public function pengurus(int $id, string $jenis, PenyimpanBerkas $penyimpan): BinaryFileResponse
    {
        return $this->sajikan(Keluarga::findOrFail($id), $jenis, $penyimpan);
    }

    /** Warga membuka dokumen KK-nya sendiri: kepemilikan lewat users.keluarga_id. */
    public function milikSendiri(string $jenis, PenyimpanBerkas $penyimpan): BinaryFileResponse
    {
        $keluargaId = auth()->user()->keluarga_id;
        abort_unless($keluargaId, 404);

        return $this->sajikan(
            Keluarga::where('keluarga_id', $keluargaId)->firstOrFail(), $jenis, $penyimpan
        );
    }

    private function sajikan(Keluarga $kk, string $jenis, PenyimpanBerkas $penyimpan): BinaryFileResponse
    {
        $profil = PenyimpanBerkas::KOLOM_KK[$jenis] ?? abort(404);
        $path = $penyimpan->lokasiAbsolut($kk->{$jenis}, $profil);
        abort_if($path === null, 404);

        // Jenis dibaca ulang dari isi: berkas warisan tidak pernah diperiksa
        // saat diunggah. Yang di luar daftar tetap bisa diunduh, tapi tidak
        // pernah ditampilkan inline dengan tipe yang bisa dieksekusi browser.
        $mime = $penyimpan->jenisIsi($path);
        $aman = $penyimpan->jenisDiizinkan($mime, $profil);
        // Hanya GAMBAR yang tampil inline (di bawah CSP sandbox). PDF diunduh:
        // penampil PDF Chrome menolak berjalan di dokumen ber-sandbox, dan
        // tanpa sandbox PDF bisa membawa skripnya sendiri.
        $inline = $aman && str_starts_with($mime, 'image/');
        $ekstensi = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'application/pdf' => '.pdf'][$mime] ?? '';

        return response()->file($path, [
            'Content-Type' => $aman ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$jenis.$ekstensi.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
