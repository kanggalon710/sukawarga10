<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keluarga;
use App\Models\Anggota;
use App\Services\AuditLogService;

class KeluargaController extends Controller
{
    public function indexWeb(Request $request) {
        $query = Keluarga::withCount('anggota')->orderBy('rt')->orderBy('nama');
        if ($request->rt) $query->where('rt', $request->rt);
        if ($request->q) {
            $safeQ = str_replace(['%', '_'], ['\\%', '\\_'], $request->q);
            $query->where('nama', 'like', '%' . $safeQ . '%');
        }
        if ($request->status) $query->where('status', $request->status);
        // Filter: warga belum punya NIK KK
        if ($request->filter === 'belum_kk') {
            $query->where(function ($q) { $q->whereNull('noKK')->orWhere('noKK', ''); });
        }
        // Dipaginasi supaya halaman tidak ikut membesar seiring bertambahnya KK.
        // withQueryString() menjaga filter rt/q/status saat pindah halaman.
        $keluarga = $query->paginate(50)->withQueryString();
        return view('warga.index', compact('keluarga'));
    }

    public function create() {
        return view('warga.create');
    }

    public function storeWeb(Request $request) {
        $validated = $request->validate([
            'nama' => 'required', 'rt' => 'required',
            'alamat' => 'required', 'jumlahAnggota' => 'required|min:1',
            'noHP' => ['nullable', 'string', 'max:25'], // format apa pun diterima → dinormalisasi otomatis ke 62xxxx
        ]);

        $data = $request->only([
            // Step 1: Identitas
            'nama', 'rt', 'noKK', 'nik', 'noHP', 'jumlahAnggota', 'alamat',
            'tanggalLahirKK', 'jenisKelaminKK',
            // Step 2: Rumah & Sanitasi
            'statusRumah', 'tipeBangunan', 'luasLantai', 'jmlKamarTidur',
            'bahanLantai', 'bahanDinding', 'bahanAtap',
            'sumberAirMinum', 'sumberAirMandi', 'sumberMasak',
            'kepemilikanJamban', 'pembuanganTinja', 'caraBuangSampah', 'sumberListrik',
            'dayaListrik', 'aksesInternet', 'rawanBencana',
            'jenisSertifikat',
            // Step 3: Ekonomi
            'pekerjaan', 'sumberPendapatan', 'penghasilan', 'aksesKredit', 'jumlahTanggungan',
            // Step 4: misc
            'catatan',
        ]);

        $data['noHP'] = normalizeWa($request->input('noHP')); // auto → 62xxxx
        $data['keluarga_id'] = uniqid('kk_');
        $data['rw'] = '10';
        $data['kelurahan'] = 'Sukakarya';
        $data['kecamatan'] = 'Tarogong Kidul';
        $data['status'] = 'aktif';
        $data['ikutSampah'] = $request->boolean('ikutSampah');
        $data['ikutPadaringan'] = $request->boolean('ikutPadaringan');
        $data['punyaTabungan'] = $request->boolean('punyaTabungan');
        $data['punyaHutangUsaha'] = $request->boolean('punyaHutangUsaha');

        // JSON fields
        $data['aset'] = [
            'motor' => $request->boolean('aset_motor'),
            'mobil' => $request->boolean('aset_mobil'),
            'tanah' => $request->boolean('aset_tanah'),
            'sawah' => $request->boolean('aset_sawah'),
            'ternak' => $request->boolean('aset_ternak'),
            'komputer' => $request->boolean('aset_komputer'),
        ];
        $data['bansos'] = [
            'bpjs' => $request->boolean('bansos_bpjs'),
            'pkh' => $request->boolean('bansos_pkh'),
            'bpnt' => $request->boolean('bansos_bpnt'),
            'blt' => $request->boolean('bansos_blt'),
            'rutilahu' => $request->boolean('bansos_rutilahu'),
            'kis' => $request->boolean('bansos_kis'),
            'kip' => $request->boolean('bansos_kip'),
        ];
        $data['kelompokRentan'] = [
            'lansia' => $request->boolean('rentan_lansia'),
            'balita' => $request->boolean('rentan_balita'),
            'hamil' => $request->boolean('rentan_hamil'),
            'disabilitas' => $request->boolean('rentan_disabilitas'),
            'jandaDuda' => $request->boolean('rentan_jandaDuda'),
            'yatimPiatu' => $request->boolean('rentan_yatimPiatu'),
        ];
        $data['kesehatan'] = [
            'penyakitKronis' => array_values(array_filter($request->input('penyakitKronis', []))),
            'ikutKB' => $request->boolean('ikutKB'),
            'stunting' => $request->boolean('stunting'),
        ];
        $data['tags'] = array_filter($request->input('tags', []));

        // Handle file uploads
        foreach (['fotoKK', 'fotoRumah', 'dokumenPBB'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('dokumen', 'public');
            }
        }

        $kk = Keluarga::create($data);

        // Handle inline anggota from create form
        $anggotaNames = $request->input('anggota_nama', []);
        $anggotaCount = 0;
        if (!empty($anggotaNames)) {
            $jk = $request->input('anggota_jk', []);
            $status = $request->input('anggota_status', []);
            $nik = $request->input('anggota_nik', []);
            $kerja = $request->input('anggota_kerja', []);
            $bpjs = $request->input('anggota_bpjs', []);
            $tgl = $request->input('anggota_tgllahir', []);
            $pend = $request->input('anggota_pendidikan', []);
            $kawin = $request->input('anggota_kawin', []);
            $agama = $request->input('anggota_agama', []);
            $skerja = $request->input('anggota_statuskerja', []);
            $phasil = $request->input('anggota_penghasilan', []);
            foreach ($anggotaNames as $i => $nama) {
                if (empty(trim($nama))) continue;
                $kk->anggota()->create([
                    'nama' => $nama,
                    'jenisKelamin' => $jk[$i] ?? null,
                    'statusKeluarga' => $status[$i] ?? null,
                    'nik' => $nik[$i] ?? null,
                    'pekerjaan' => $kerja[$i] ?? null,
                    'statusBPJS' => $bpjs[$i] ?? null,
                    'tanggalLahir' => !empty($tgl[$i]) ? $tgl[$i] : null,
                    'pendidikan' => $pend[$i] ?? null,
                    'statusPerkawinan' => $kawin[$i] ?? null,
                    'agama' => $agama[$i] ?? null,
                    'statusPekerjaan' => ($skerja[$i] ?? '') ?: statusKerjaDariPekerjaan($kerja[$i] ?? ''),
                    'penghasilan' => ($phasil[$i] ?? '') ?: null,
                ]);
                $anggotaCount++;
            }
            $kk->update(['jumlahAnggota' => 1 + $anggotaCount]);
        }

        AuditLogService::log('create', 'warga', 'Tambah warga: ' . $data['nama'] . ' RT ' . $data['rt'] . ($anggotaCount ? " + {$anggotaCount} anggota" : ''));
        $msg = 'Data Keluarga '. $data['nama'] .' berhasil ditambahkan!';
        if ($anggotaCount) $msg .= " ({$anggotaCount} anggota keluarga ditambahkan)";
        return redirect()->route('warga.index')->with('success', $msg);
    }

    public function edit($id) {
        $kk = Keluarga::with('anggota')->findOrFail($id);
        return view('warga.edit', compact('kk'));
    }

    public function update(Request $request, $id) {
        $kk = Keluarga::findOrFail($id);

        $data = $request->only([
            'nama', 'rt', 'noKK', 'nik', 'noHP', 'jumlahAnggota', 'alamat', 'status',
            'tanggalLahirKK', 'jenisKelaminKK', 'jenisSertifikat',
            'statusRumah', 'tipeBangunan', 'luasLantai', 'jmlKamarTidur',
            'bahanLantai', 'bahanDinding', 'bahanAtap',
            'sumberAirMinum', 'sumberAirMandi', 'sumberMasak',
            'kepemilikanJamban', 'pembuanganTinja', 'caraBuangSampah', 'sumberListrik',
            'dayaListrik', 'aksesInternet', 'rawanBencana', 'jumlahTanggungan',
            'pekerjaan', 'sumberPendapatan', 'penghasilan', 'aksesKredit', 'catatan',
        ]);

        $data['noHP'] = normalizeWa($request->input('noHP')); // auto → 62xxxx
        $data['ikutSampah'] = $request->boolean('ikutSampah');
        $data['ikutPadaringan'] = $request->boolean('ikutPadaringan');
        $data['punyaTabungan'] = $request->boolean('punyaTabungan');
        $data['punyaHutangUsaha'] = $request->boolean('punyaHutangUsaha');

        $data['aset'] = [
            'motor' => $request->boolean('aset_motor'), 'mobil' => $request->boolean('aset_mobil'),
            'tanah' => $request->boolean('aset_tanah'), 'sawah' => $request->boolean('aset_sawah'),
            'ternak' => $request->boolean('aset_ternak'), 'komputer' => $request->boolean('aset_komputer'),
        ];
        $data['bansos'] = [
            'bpjs' => $request->boolean('bansos_bpjs'), 'pkh' => $request->boolean('bansos_pkh'),
            'bpnt' => $request->boolean('bansos_bpnt'), 'blt' => $request->boolean('bansos_blt'),
            'rutilahu' => $request->boolean('bansos_rutilahu'),
            'kis' => $request->boolean('bansos_kis'), 'kip' => $request->boolean('bansos_kip'),
        ];
        $data['kelompokRentan'] = [
            'lansia' => $request->boolean('rentan_lansia'), 'balita' => $request->boolean('rentan_balita'),
            'hamil' => $request->boolean('rentan_hamil'), 'disabilitas' => $request->boolean('rentan_disabilitas'),
            'jandaDuda' => $request->boolean('rentan_jandaDuda'), 'yatimPiatu' => $request->boolean('rentan_yatimPiatu'),
        ];
        $data['kesehatan'] = [
            'penyakitKronis' => array_values(array_filter($request->input('penyakitKronis', []))),
            'ikutKB' => $request->boolean('ikutKB'),
            'stunting' => $request->boolean('stunting'),
        ];
        $data['tags'] = array_filter($request->input('tags', []));

        // Handle file uploads
        foreach (['fotoKK', 'fotoRumah', 'dokumenPBB'] as $field) {
            if ($request->hasFile($field)) {
                // Delete old file if exists
                if ($kk->$field && \Storage::disk('public')->exists($kk->$field)) {
                    \Storage::disk('public')->delete($kk->$field);
                }
                $data[$field] = $request->file($field)->store('dokumen', 'public');
            }
        }

        $kk->update($data);
        return redirect()->route('warga.index')->with('success', 'Data ' . $kk->nama . ' berhasil diperbarui.');
    }

    public function destroy($id) {
        $kk = Keluarga::findOrFail($id);
        $nama = $kk->nama;
        // Cascade: delete anggota too
        Anggota::where('keluarga_id', $kk->keluarga_id)->delete();
        $kk->delete();
        AuditLogService::log('delete', 'warga', 'Hapus warga: ' . $nama);
        return redirect()->route('warga.index')->with('success', "Data $nama berhasil dihapus.");
    }

    // ========================
    // ANGGOTA KELUARGA CRUD
    // ========================

    public function storeAnggota(Request $request, $id) {
        $kk = Keluarga::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:100',
            'nik' => 'nullable|string|size:16',
            'jenisKelamin' => 'required|in:L,P',
            'statusKeluarga' => 'required|string|max:30',
        ]);

        Anggota::create([
            'anggota_id' => 'ag_' . uniqid(),
            'keluarga_id' => $kk->keluarga_id,
            'nama' => $request->nama,
            'nik' => $request->nik,
            'tempatLahir' => $request->tempatLahir,
            'tanggalLahir' => $request->tanggalLahir,
            'jenisKelamin' => $request->jenisKelamin,
            'statusKeluarga' => $request->statusKeluarga,
            'pekerjaan' => $request->pekerjaan,
            'statusBPJS' => $request->statusBPJS,
            'noBPJS' => $request->noBPJS,
            'pendidikan' => $request->pendidikan,
            'statusPerkawinan' => $request->statusPerkawinan,
            'agama' => $request->agama,
            'statusPekerjaan' => $request->statusPekerjaan ?: statusKerjaDariPekerjaan($request->pekerjaan),
            'penghasilan' => $request->penghasilan,
        ]);

        // Auto-update jumlahAnggota
        $kk->update(['jumlahAnggota' => 1 + $kk->anggota()->count()]);

        return back()->with('success', 'Anggota ' . $request->nama . ' berhasil ditambahkan.');
    }

    public function updateAnggota(Request $request, $id, $anggotaId) {
        $kk = Keluarga::findOrFail($id);
        $anggota = Anggota::where('keluarga_id', $kk->keluarga_id)->findOrFail($anggotaId);

        $anggota->update($request->only([
            'nama', 'nik', 'tempatLahir', 'tanggalLahir',
            'jenisKelamin', 'statusKeluarga', 'pekerjaan',
            'statusBPJS', 'noBPJS',
            'pendidikan', 'statusPerkawinan', 'agama',
            'statusPekerjaan', 'penghasilan',
        ]));

        return back()->with('success', 'Data anggota ' . $anggota->nama . ' berhasil diperbarui.');
    }

    public function destroyAnggota($id, $anggotaId) {
        $kk = Keluarga::findOrFail($id);
        $anggota = Anggota::where('keluarga_id', $kk->keluarga_id)->findOrFail($anggotaId);
        $nama = $anggota->nama;
        $anggota->delete();

        // Auto-update jumlahAnggota
        $kk->update(['jumlahAnggota' => 1 + $kk->anggota()->count()]);

        return back()->with('success', 'Anggota ' . $nama . ' berhasil dihapus.');
    }
}
