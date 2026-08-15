<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keluarga;

class ProfilWargaController extends Controller
{
    /**
     * KK milik user yang sedang login.
     *
     * Dicari lewat kunci eksplisit `users.keluarga_id`, bukan lewat kecocokan
     * nama seperti sebelumnya — dua warga bernama sama membuat orang bisa
     * membuka dan mengubah data KK orang lain. Akun yang belum tertaut
     * mendapat null, dan pemanggilnya menolak dengan pesan yang jelas.
     */
    private function findKeluarga()
    {
        $keluargaId = auth()->user()->keluarga_id;
        if (!$keluargaId) return null;

        return Keluarga::with('anggota')->where('keluarga_id', $keluargaId)->first();
    }

    private function calcCompletion($kk)
    {
        $fields = ['noKK', 'nik', 'tanggalLahirKK', 'jenisKelaminKK', 'alamat',
            'statusRumah', 'tipeBangunan', 'jenisSertifikat',
            'pekerjaan', 'fotoKK', 'fotoRumah', 'dokumenPBB'];
        $filled = 0;
        foreach ($fields as $f) {
            if (!empty($kk->$f)) $filled++;
        }
        return round($filled / count($fields) * 100);
    }

    public function index()
    {
        $kk = $this->findKeluarga();

        if (!$kk) {
            return redirect()->route('dashboard')->with('error', 'Data Keluarga Anda belum ditemukan. Hubungi admin RT.');
        }

        $completion = $this->calcCompletion($kk);

        return view('warga.profil', compact('kk', 'completion'));
    }

    public function update(Request $request)
    {
        $kk = $this->findKeluarga();

        if (!$kk) {
            return redirect()->route('dashboard')->with('error', 'Data Keluarga Anda belum ditemukan.');
        }

        $oldCompletion = $this->calcCompletion($kk);

        $data = $request->only([
            'noKK', 'nik', 'noHP', 'tanggalLahirKK', 'jenisKelaminKK', 'alamat',
            'statusRumah', 'tipeBangunan', 'jenisSertifikat',
            'luasLantai', 'jmlKamarTidur',
            'bahanLantai', 'bahanDinding', 'bahanAtap',
            'sumberAirMinum', 'sumberAirMandi', 'sumberMasak', 'sumberListrik',
            'kepemilikanJamban', 'pembuanganTinja', 'caraBuangSampah',
            'pekerjaan', 'sumberPendapatan', 'penghasilan',
        ]);

        // Handle file uploads
        foreach (['fotoKK', 'fotoRumah', 'dokumenPBB'] as $field) {
            if ($request->hasFile($field)) {
                if ($kk->$field && \Storage::disk('public')->exists($kk->$field)) {
                    \Storage::disk('public')->delete($kk->$field);
                }
                $data[$field] = $request->file($field)->store('dokumen', 'public');
            }
        }

        $kk->update($data);
        $kk->refresh();

        $newCompletion = $this->calcCompletion($kk);

        \App\Services\AuditLogService::log('update_profil', 'warga', 'Warga update profil sendiri: ' . $kk->nama . ' (' . $newCompletion . '%)');

        // Milestone notifications
        $milestoneMsg = '';
        if ($oldCompletion < 75 && $newCompletion >= 75 && $newCompletion < 100) {
            $milestoneMsg = '🎉 Hebat! Data Anda sudah ' . $newCompletion . '% lengkap! Tinggal sedikit lagi menuju 100%. Data yang lengkap membantu RT/RW memberikan pelayanan terbaik untuk keluarga Anda. Ayo lanjutkan! 💪';
            // Send WA notification
            if ($kk->noHP) {
                try {
                    \App\Services\MpwaService::send($kk->noHP,
                        "🎉 *Selamat {$kk->nama}!*\n\n" .
                        "Data profil keluarga Anda di " . namaAplikasi() . " sudah *{$newCompletion}%* lengkap! 📊\n\n" .
                        "Tinggal sedikit lagi menuju 100%. Data yang lengkap akan membantu RT/RW dalam:\n" .
                        "✅ Penyaluran bantuan sosial yang tepat sasaran\n" .
                        "✅ Pendataan kependudukan yang akurat\n" .
                        "✅ Pelayanan administrasi yang lebih cepat\n\n" .
                        "Ayo lengkapi sekarang di: sukawarga10.jabnet.id 💪"
                    );
                } catch (\Exception $e) {}
            }
        } elseif ($oldCompletion < 100 && $newCompletion >= 100) {
            $milestoneMsg = '🏆 Luar biasa! Data Anda sudah 100% lengkap! Terima kasih atas kontribusi Anda. Data lengkap membantu program kesejahteraan warga berjalan optimal. Anda adalah warga teladan! ⭐';
            // Send WA notification
            if ($kk->noHP) {
                try {
                    \App\Services\MpwaService::send($kk->noHP,
                        "🏆 *Luar Biasa, {$kk->nama}!*\n\n" .
                        "Data profil keluarga Anda di " . namaAplikasi() . " sudah *100% LENGKAP!* 🎊\n\n" .
                        "Terima kasih atas kontribusi Anda! Data yang lengkap memungkinkan:\n" .
                        "🎯 Bantuan sosial tepat sasaran untuk keluarga Anda\n" .
                        "📋 Surat-menyurat & administrasi lebih cepat\n" .
                        "📊 Data kependudukan RW yang akurat\n\n" .
                        "Anda adalah *warga teladan*! ⭐\n" .
                        "- Tim Pengurus " . namaAplikasi()
                    );
                } catch (\Exception $e) {}
            }
        }

        $successMsg = 'Data Anda berhasil diperbarui! ✅';
        if ($milestoneMsg) {
            $successMsg .= ' ' . $milestoneMsg;
        }

        return redirect()->route('profil.index')->with('success', $successMsg);
    }

    /**
     * Add anggota keluarga from profil page
     */
    public function storeAnggota(Request $request)
    {
        $kk = $this->findKeluarga();

        if (!$kk) {
            return redirect()->route('dashboard')->with('error', 'Data Keluarga Anda belum ditemukan.');
        }

        $request->validate([
            'nama' => 'required',
            'jenisKelamin' => 'required|in:L,P',
            'statusKeluarga' => 'required',
        ]);

        $kk->anggota()->create($request->only([
            'nama', 'nik', 'jenisKelamin', 'statusKeluarga',
            'pekerjaan', 'tempatLahir', 'tanggalLahir',
            'statusBPJS', 'noBPJS',
        ]));

        // Update jumlahAnggota
        $kk->update(['jumlahAnggota' => 1 + $kk->anggota()->count()]);

        \App\Services\AuditLogService::log('add_anggota_profil', 'warga', 'Warga tambah anggota via profil: ' . $request->nama . ' (KK: ' . $kk->nama . ')');

        return redirect()->route('profil.index')->with('success', 'Anggota keluarga ' . $request->nama . ' berhasil ditambahkan! ✅');
    }

    /**
     * Delete anggota keluarga from profil page
     */
    public function destroyAnggota($anggotaId)
    {
        $kk = $this->findKeluarga();

        if (!$kk) {
            return redirect()->route('dashboard')->with('error', 'Data Keluarga Anda belum ditemukan.');
        }

        $anggota = $kk->anggota()->findOrFail($anggotaId);
        $namaAnggota = $anggota->nama;
        $anggota->delete();

        $kk->update(['jumlahAnggota' => 1 + $kk->anggota()->count()]);

        \App\Services\AuditLogService::log('delete_anggota_profil', 'warga', 'Warga hapus anggota via profil: ' . $namaAnggota . ' (KK: ' . $kk->nama . ')');

        return redirect()->route('profil.index')->with('success', 'Anggota ' . $namaAnggota . ' berhasil dihapus.');
    }
}
