@extends('layouts.app')
@section('title', 'Edit Warga')
@section('page-title', 'Edit Data Keluarga')
@section('page-subtitle'){{ $kk->nama }} · RT {{ $kk->rt }}@endsection

@section('content')
@php
    $aset = $kk->aset ?? [];
    $bansos = $kk->bansos ?? [];
    $rentan = $kk->kelompokRentan ?? [];
    $tags = $kk->tags ?? [];
@endphp

<div class="toolbar"><div class="toolbar-left"><a href="{{ route('warga.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div></div>

<!-- Step Progress -->
<div class="edit-steps">
    @foreach(['Identitas','Rumah','Ekonomi','Program','Dokumen'] as $i => $s)
    <div id="stepDot{{ $i+1 }}" class="edit-step-dot">
        @if($i > 0)<div class="edit-step-line" id="stepLine{{ $i+1 }}"></div>@endif
        <div class="edit-step-label {{ $i === 0 ? 'active' : '' }}" id="stepLabel{{ $i+1 }}" onclick="goStep({{ $i+1 }})">
            <span>{{ $i+1 }}</span> <span>{{ $s }}</span>
        </div>
    </div>
    @endforeach
</div>

<form action="{{ route('warga.update', $kk->id) }}" method="POST" id="wargaForm" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="edit-form-wrap">

<!-- STEP 1 -->
<div id="step1" class="step-panel">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-id-card" style="color:var(--hijau);"></i> Identitas</div></div>
        <div class="edit-fields">
            <div><label class="f-label">Nama Kepala Keluarga *</label><input type="text" name="nama" value="{{ $kk->nama }}" required class="f-input"></div>
            <div class="edit-grid-2">
                <div><label class="f-label">RT *</label><select name="rt" required class="f-input">@for($i=1;$i<=6;$i++)<option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}" {{ $kk->rt == str_pad($i,2,'0',STR_PAD_LEFT) ? 'selected' : '' }}>RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor</select></div>
                <div><label class="f-label">Jumlah Jiwa</label><input type="number" name="jumlahAnggota" value="{{ $kk->jumlahAnggota }}" class="f-input" readonly style="background:#f1f5f9; cursor:not-allowed;" title="Otomatis: 1 kepala + anggota"></div>
            </div>
            <div class="edit-grid-2">
                <div><label class="f-label">NIK Kartu Keluarga</label><input type="text" name="noKK" value="{{ $kk->noKK }}" class="f-input"></div>
                <div><label class="f-label">NIK KTP</label><input type="text" name="nik" value="{{ $kk->nik }}" class="f-input"></div>
            </div>
            <div><label class="f-label">No. HP / WhatsApp</label><input type="tel" name="noHP" value="{{ $kk->noHP }}" class="f-input"></div>
            <div class="edit-grid-2">
                <div>
                    <label class="f-label">Tanggal Lahir KK</label>
                    <input type="date" name="tanggalLahirKK" value="{{ $kk->tanggalLahirKK ? $kk->tanggalLahirKK->format('Y-m-d') : '' }}" class="f-input" id="tglLahirKK">
                    @if($kk->umurKK)<div style="font-size:11px; color:var(--hijau); margin-top:4px; font-weight:700;">🎂 Umur: {{ $kk->umurKK }} tahun</div>@endif
                </div>
                <div>
                    <label class="f-label">Jenis Kelamin KK</label>
                    <select name="jenisKelaminKK" class="f-input">
                        <option value="">- Pilih -</option>
                        <option value="L" {{ $kk->jenisKelaminKK=='L'?'selected':'' }}>Laki-laki</option>
                        <option value="P" {{ $kk->jenisKelaminKK=='P'?'selected':'' }}>Perempuan</option>
                    </select>
                </div>
            </div>
            <div><label class="f-label">Alamat *</label><textarea name="alamat" rows="2" required class="f-input" style="font-family:inherit;">{{ $kk->alamat }}</textarea></div>
            {{-- "Pindah" sengaja bukan pilihan di sini: status itu hanya boleh
                 dipasang lewat alur pemindahan warga yang butuh persetujuan
                 pengelola desa. Kalau KK memang sudah pindah, statusnya
                 ditampilkan sebagai keterangan, bukan sebagai pilihan. --}}
            @if ($kk->status === 'pindah')
                <div>
                    <span class="f-label">Status</span>
                    <p class="badge badge-gray" style="margin-top:6px;">
                        <i class="fas fa-truck-moving" aria-hidden="true"></i> Sudah pindah
                    </p>
                    <small style="color:var(--text3);">Diubah lewat alur pemindahan warga, bukan dari halaman ini.</small>
                </div>
            @else
                <div>
                    <label class="f-label" for="status-kk">Status</label>
                    <select name="status" id="status-kk" class="f-input">
                        <option value="aktif" {{ $kk->status=='aktif'?'selected':'' }}>Aktif</option>
                        <option value="nonaktif" {{ $kk->status=='nonaktif'?'selected':'' }}>Nonaktif</option>
                    </select>
                </div>
            @endif
        </div>
        <button type="button" class="btn btn-primary" style="width:100%; margin-top:20px;" onclick="goStep(2)">Lanjut <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

<!-- STEP 2 -->
<div id="step2" class="step-panel" style="display:none;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-home" style="color:var(--biru);"></i> Rumah & Sanitasi</div></div>
        <div class="edit-fields">
            <div class="edit-grid-2">
                <div><label class="f-label">Status Rumah</label><select name="statusRumah" class="f-input">@foreach(['' => '-','Milik Sendiri','Sewa/Kontrak','Menumpang','Dinas'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->statusRumah == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Tipe Bangunan</label><select name="tipeBangunan" class="f-input">@foreach(['' => '-','Permanen','Semi Permanen','Kayu/Bilik'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->tipeBangunan == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div>
                <label class="f-label">📜 Jenis Sertifikat Tanah</label>
                <select name="jenisSertifikat" class="f-input">
                    @foreach(['' => '- Pilih Jenis Sertifikat -', 'SHM' => 'SHM (Sertifikat Hak Milik)', 'SHGB' => 'SHGB (Sertifikat Hak Guna Bangunan)', 'AJB' => 'AJB (Akta Jual Beli)', 'Girik' => 'Girik / Letter C', 'Petok D' => 'Petok D', 'Belum Bersertifikat' => 'Belum Bersertifikat', 'Lainnya' => 'Lainnya'] as $k => $v)
                    <option value="{{ $k }}" {{ $kk->jenisSertifikat == $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="edit-grid-2">
                <div><label class="f-label">Luas Lantai (m²)</label><input type="text" name="luasLantai" value="{{ $kk->luasLantai }}" class="f-input"></div>
                <div><label class="f-label">Jml Kamar</label><input type="number" name="jmlKamarTidur" value="{{ $kk->jmlKamarTidur }}" min="0" class="f-input"></div>
            </div>
            <div class="edit-grid-3">
                <div><label class="f-label">Bahan Lantai</label><select name="bahanLantai" class="f-input">@foreach([''=> '-','Keramik','Ubin','Semen','Tanah'] as $v)<option {{ $kk->bahanLantai==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Bahan Dinding</label><select name="bahanDinding" class="f-input">@foreach([''=> '-','Tembok','Kayu','Bambu','GRC'] as $v)<option {{ $kk->bahanDinding==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Bahan Atap</label><select name="bahanAtap" class="f-input">@foreach([''=> '-','Genteng','Seng','Asbes','Daun/Jerami'] as $v)<option {{ $kk->bahanAtap==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div class="edit-grid-2">
                <div><label class="f-label">Air Minum</label><select name="sumberAirMinum" class="f-input">@foreach([''=> '-','PDAM','Sumur Bor','Sumur Gali','Mata Air','Air Hujan'] as $v)<option {{ $kk->sumberAirMinum==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Air Mandi</label><select name="sumberAirMandi" class="f-input">@foreach([''=> '-','PDAM','Sumur Bor','Sumur Gali','Mata Air','Sungai'] as $v)<option {{ $kk->sumberAirMandi==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div class="edit-grid-2">
                <div><label class="f-label">Sumber Masak</label><select name="sumberMasak" class="f-input">@foreach([''=> '-','Gas LPG','Kayu Bakar','Minyak Tanah','Listrik'] as $v)<option {{ $kk->sumberMasak==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Listrik</label><select name="sumberListrik" class="f-input">@foreach([''=> '-','PLN','PLN + Solar','Non-PLN','Tidak Ada'] as $v)<option {{ $kk->sumberListrik==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div class="edit-grid-3">
                <div><label class="f-label">Daya Listrik</label><select name="dayaListrik" class="f-input">@foreach([''=>'-','450 VA','900 VA','1300 VA','2200 VA+','Non-meteran'] as $v)<option {{ $kk->dayaListrik==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Akses Internet</label><select name="aksesInternet" class="f-input">@foreach([''=>'-','Tidak Ada','Seluler/HP','WiFi/Fixed'] as $v)<option {{ $kk->aksesInternet==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Rawan Bencana</label><select name="rawanBencana" class="f-input">@foreach([''=>'-','Tidak','Banjir','Longsor','Kebakaran'] as $v)<option {{ $kk->rawanBencana==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div class="edit-grid-3">
                <div><label class="f-label">Jamban</label><select name="kepemilikanJamban" class="f-input">@foreach([''=> '-','Sendiri','Bersama','Umum','Tidak Ada'] as $v)<option {{ $kk->kepemilikanJamban==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Tinja</label><select name="pembuanganTinja" class="f-input">@foreach([''=> '-','Septic Tank','Cemplung','Sungai','Lainnya'] as $v)<option {{ $kk->pembuanganTinja==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Sampah</label><select name="caraBuangSampah" class="f-input">@foreach([''=> '-','Diangkut Petugas','Dibakar','Dibuang Sembarangan','Ditimbun'] as $v)<option {{ $kk->caraBuangSampah==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
        </div>
        <div class="edit-btn-row">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(1)"><i class="fas fa-arrow-left"></i></button>
            <button type="button" class="btn btn-outline" style="flex:0.6;" onclick="goStep(3)">Lewati</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="goStep(3)">Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<!-- STEP 3 -->
<div id="step3" class="step-panel" style="display:none;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-wallet" style="color:var(--emas);"></i> Ekonomi & Aset</div></div>
        <div class="edit-fields">
            <div class="edit-grid-2">
                <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" value="{{ $kk->pekerjaan }}" class="f-input"></div>
                <div><label class="f-label">Sumber Pendapatan</label><select name="sumberPendapatan" class="f-input">@foreach([''=> '-','Gaji Tetap','Usaha Sendiri','Buruh Harian','Pertanian','Lainnya'] as $v)<option {{ $kk->sumberPendapatan==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div class="edit-grid-2">
                <div><label class="f-label">Penghasilan/Bulan (KK)</label><select name="penghasilan" class="f-input">@foreach([''=> '-','< 1 Juta','1 - 2.5 Juta','2.5 - 5 Juta','> 5 Juta'] as $v)<option {{ $kk->penghasilan==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
                <div><label class="f-label">Akses Kredit</label><select name="aksesKredit" class="f-input">@foreach([''=> '-','Bank','Koperasi','Tidak Punya'] as $v)<option {{ $kk->aksesKredit==$v?'selected':'' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            </div>
            <div><label class="f-label">Jumlah Tanggungan</label><input type="number" name="jumlahTanggungan" min="0" value="{{ $kk->jumlahTanggungan }}" class="f-input" placeholder="Jiwa yang ditanggung"></div>
            <div class="edit-grid-2">
                <label class="f-check"><input type="checkbox" name="punyaTabungan" value="1" {{ $kk->punyaTabungan?'checked':'' }}> Punya Tabungan</label>
                <label class="f-check"><input type="checkbox" name="punyaHutangUsaha" value="1" {{ $kk->punyaHutangUsaha?'checked':'' }}> Punya Hutang</label>
            </div>
            <div style="border-top:1px solid var(--abu2); padding-top:14px;">
                <label class="f-label">Kepemilikan Aset</label>
                <div class="edit-check-grid">
                    @foreach(['motor'=>'🏍️ Motor','mobil'=>'🚗 Mobil','tanah'=>'📐 Tanah','sawah'=>'🌾 Sawah','ternak'=>'🐄 Ternak','komputer'=>'💻 Komputer'] as $k => $v)
                    <label class="f-check"><input type="checkbox" name="aset_{{ $k }}" value="1" {{ ($aset[$k] ?? false) ? 'checked' : '' }}> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="edit-btn-row">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(2)"><i class="fas fa-arrow-left"></i></button>
            <button type="button" class="btn btn-outline" style="flex:0.6;" onclick="goStep(4)">Lewati</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="goStep(4)">Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<!-- STEP 4 -->
<div id="step4" class="step-panel" style="display:none;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-hand-holding-heart" style="color:var(--merah);"></i> Bansos & Program</div></div>
        <div class="edit-fields">
            <div>
                <label class="f-label">Bantuan Sosial</label>
                <div class="edit-check-grid">
                    @foreach(['bpjs'=>'BPJS/JKN','pkh'=>'PKH','bpnt'=>'BPNT','blt'=>'BLT','rutilahu'=>'Rutilahu','kis'=>'KIS','kip'=>'KIP'] as $k => $v)
                    <label class="f-check"><input type="checkbox" name="bansos_{{ $k }}" value="1" {{ ($bansos[$k] ?? false) ? 'checked' : '' }}> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="f-label">Kelompok Rentan</label>
                <div class="edit-check-grid">
                    @foreach(['lansia'=>'👴 Lansia','balita'=>'👶 Balita','hamil'=>'🤰 Hamil','disabilitas'=>'♿ Disabilitas','jandaDuda'=>'Janda/Duda','yatimPiatu'=>'Yatim/Piatu'] as $k => $v)
                    <label class="f-check"><input type="checkbox" name="rentan_{{ $k }}" value="1" {{ ($rentan[$k] ?? false) ? 'checked' : '' }}> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="f-label">Kesehatan Keluarga</label>
                <div class="edit-check-grid">
                    @foreach(['TBC','Hipertensi','Diabetes','Jantung','Gangguan Jiwa','Asma'] as $p)
                    <label class="f-check"><input type="checkbox" name="penyakitKronis[]" value="{{ $p }}" {{ in_array($p, data_get($kk, 'kesehatan.penyakitKronis', []) ?: []) ? 'checked' : '' }}> {{ $p }}</label>
                    @endforeach
                </div>
                <div class="edit-grid-2" style="margin-top:8px;">
                    <label class="f-check"><input type="checkbox" name="ikutKB" value="1" {{ data_get($kk, 'kesehatan.ikutKB') ? 'checked' : '' }}> Ikut KB</label>
                    <label class="f-check"><input type="checkbox" name="stunting" value="1" {{ data_get($kk, 'kesehatan.stunting') ? 'checked' : '' }}> Ada Balita Stunting</label>
                </div>
            </div>
            <div>
                <label class="f-label">Tag Sosial</label>
                <div class="edit-check-grid">
                    @foreach(['kurang_mampu'=>'Kurang Mampu','lansia'=>'Lansia','umkm'=>'UMKM','disabilitas'=>'Disabilitas','janda_duda'=>'Janda/Duda','migran'=>'Migran'] as $k => $v)
                    <label class="f-check"><input type="checkbox" name="tags[]" value="{{ $k }}" {{ in_array($k, $tags) ? 'checked' : '' }}> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
            <div style="border-top:1px solid var(--abu2); padding-top:14px;">
                <label class="f-label">Program Iuran</label>
                <div class="edit-grid-2">
                    <label class="f-check" style="background:var(--emas-muda); border-color:var(--emas);"><input type="checkbox" name="ikutSampah" value="1" {{ $kk->ikutSampah?'checked':'' }}> 🗑️ Iuran Sampah</label>
                    <label class="f-check" style="background:var(--biru-pale); border-color:var(--biru);"><input type="checkbox" name="ikutPadaringan" value="1" {{ $kk->ikutPadaringan?'checked':'' }}> 🍚 Padaringan</label>
                </div>
            </div>
            <div><label class="f-label">Catatan</label><textarea name="catatan" rows="2" class="f-input" style="font-family:inherit;">{{ $kk->catatan }}</textarea></div>
        </div>
        <div class="edit-btn-row">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(3)"><i class="fas fa-arrow-left"></i></button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="goStep(5)">Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<!-- STEP 5: DOKUMEN -->
<div id="step5" class="step-panel" style="display:none;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-file-alt" style="color:#7e57c2;"></i> Dokumen & Foto</div></div>
        <div class="edit-fields">
            <div>
                <label class="f-label">📋 Scan Kartu Keluarga</label>
                @if($kk->fotoKK)
                <div style="margin-bottom:8px;"><img src="{{ asset('storage/' . $kk->fotoKK) }}" style="max-width:200px; border-radius:8px; border:1px solid var(--abu2);" alt="Scan KK"></div>
                @endif
                <input type="file" name="fotoKK" accept="image/*,.pdf" class="f-input" style="font-size:12px;">
                <div style="font-size:10px; color:var(--text3); margin-top:4px;">Format: JPG, PNG, PDF. Maks 2MB</div>
            </div>
            <div>
                <label class="f-label">🏠 Foto Rumah</label>
                @if($kk->fotoRumah)
                <div style="margin-bottom:8px;"><img src="{{ asset('storage/' . $kk->fotoRumah) }}" style="max-width:200px; border-radius:8px; border:1px solid var(--abu2);" alt="Foto Rumah"></div>
                @endif
                <input type="file" name="fotoRumah" accept="image/*" class="f-input" style="font-size:12px;">
                <div style="font-size:10px; color:var(--text3); margin-top:4px;">Format: JPG, PNG. Maks 2MB</div>
            </div>
            <div>
                <label class="f-label">📄 Dokumen PBB</label>
                @if($kk->dokumenPBB)
                <div style="margin-bottom:8px;">
                    <a href="{{ asset('storage/' . $kk->dokumenPBB) }}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> Lihat Dokumen PBB</a>
                </div>
                @endif
                <input type="file" name="dokumenPBB" accept="image/*,.pdf" class="f-input" style="font-size:12px;">
                <div style="font-size:10px; color:var(--text3); margin-top:4px;">Format: JPG, PNG, PDF. Maks 2MB</div>
            </div>
        </div>
        <div class="edit-btn-row">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(4)"><i class="fas fa-arrow-left"></i></button>
            <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Simpan Perubahan</button>
        </div>
    </div>
</div>

</div>
</form>

<!-- ============================================ -->
<!-- ANGGOTA KELUARGA SECTION                     -->
<!-- ============================================ -->
<div class="edit-anggota-wrap">
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div class="card-title"><i class="fas fa-users" style="color:var(--biru);"></i> Anggota Keluarga</div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="font-size:12px; color:var(--text3);">👤 Kepala + {{ $kk->anggota->count() }} anggota = <strong>{{ 1 + $kk->anggota->count() }} jiwa</strong></span>
                @php
                    $bpjsAktif = $kk->anggota->whereIn('statusBPJS', ['PBI', 'Mandiri'])->count();
                    $totalAnggota = $kk->anggota->count();
                @endphp
                @if($totalAnggota > 0)
                <span style="font-size:11px; padding:3px 10px; border-radius:10px; font-weight:700; {{ $bpjsAktif == $totalAnggota ? 'background:var(--hijau-pale); color:var(--hijau);' : 'background:var(--emas-muda); color:#b45309;' }}">🏥 BPJS: {{ $bpjsAktif }}/{{ $totalAnggota }} aktif</span>
                @endif
                <button type="button" class="btn btn-primary btn-sm" onclick="toggleAddAnggota()" id="btnAddAnggota">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </div>
        </div>

        <!-- ADD ANGGOTA FORM (hidden by default) -->
        <div id="addAnggotaForm" style="display:none; padding:16px; background:var(--hijau-pale); border-radius:var(--radius-sm); margin-bottom:16px;">
            <form action="{{ route('warga.anggota.store', $kk->id) }}" method="POST">
                @csrf
                <div style="font-weight:700; font-size:13px; color:var(--hijau); margin-bottom:12px;"><i class="fas fa-user-plus"></i> Tambah Anggota Baru</div>
                <div class="edit-grid-2">
                    <div><label class="f-label">Nama Lengkap *</label><input type="text" name="nama" required class="f-input" placeholder="Nama sesuai KTP"></div>
                    <div><label class="f-label">NIK KTP</label><input type="text" name="nik" class="f-input" placeholder="16 digit" maxlength="16" pattern="\d{16}"></div>
                </div>
                <div class="edit-grid-3" style="margin-top:10px;">
                    <div>
                        <label class="f-label">Jenis Kelamin *</label>
                        <select name="jenisKelamin" required class="f-input">
                            <option value="">Pilih</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="f-label">Status Keluarga *</label>
                        <select name="statusKeluarga" required class="f-input">
                            <option value="">Pilih</option>
                            <option value="Istri">Istri</option>
                            <option value="Anak">Anak</option>
                            <option value="Cucu">Cucu</option>
                            <option value="Orang Tua">Orang Tua</option>
                            <option value="Mertua">Mertua</option>
                            <option value="Menantu">Menantu</option>
                            <option value="Famili Lain">Famili Lain</option>
                        </select>
                    </div>
                    <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" class="f-input" placeholder="Opsional"></div>
                </div>
                <div class="edit-grid-2" style="margin-top:10px;">
                    <div><label class="f-label">Tempat Lahir</label><input type="text" name="tempatLahir" class="f-input" placeholder="Kota/Kabupaten"></div>
                    <div><label class="f-label">Tanggal Lahir</label><input type="date" name="tanggalLahir" class="f-input"></div>
                </div>
                <div class="edit-grid-2" style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--abu2);">
                    <div>
                        <label class="f-label">🏥 Status BPJS</label>
                        <select name="statusBPJS" class="f-input">
                            <option value="">Belum Terdaftar</option>
                            <option value="PBI">✅ PBI (Penerima Bantuan Iuran)</option>
                            <option value="Mandiri">💳 Mandiri / Non-PBI</option>
                            <option value="Tidak Aktif">❌ Tidak Aktif</option>
                        </select>
                    </div>
                    <div><label class="f-label">No. BPJS / KIS</label><input type="text" name="noBPJS" class="f-input" placeholder="0001234567890"></div>
                </div>
                <div class="edit-grid-3" style="margin-top:10px;">
                    <div><label class="f-label">Pendidikan</label><select name="pendidikan" class="f-input"><option value="">-</option><option>Tidak/Belum Sekolah</option><option>SD</option><option>SMP</option><option>SMA/SMK</option><option>D1-D3</option><option>S1</option><option>S2/S3</option></select></div>
                    <div><label class="f-label">Status Kawin</label><select name="statusPerkawinan" class="f-input"><option value="">-</option><option>Belum Kawin</option><option>Kawin</option><option>Cerai Hidup</option><option>Cerai Mati</option></select></div>
                    <div><label class="f-label">Agama</label><select name="agama" class="f-input"><option value="">-</option><option>Islam</option><option>Kristen</option><option>Katolik</option><option>Hindu</option><option>Buddha</option><option>Konghucu</option></select></div>
                </div>
                <div class="edit-grid-2" style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--abu2);">
                    <div><label class="f-label">💼 Status Kerja</label><select name="statusPekerjaan" class="f-input"><option value="">- Otomatis dari pekerjaan -</option><option>Bekerja</option><option>Tidak Bekerja</option><option>Mencari Kerja</option><option>Sekolah</option><option>Mengurus Rumah Tangga</option><option>Pensiunan</option></select></div>
                    <div><label class="f-label">Penghasilan/Bulan</label><select name="penghasilan" class="f-input"><option value="">- Bila bekerja -</option><option>< 1 Juta</option><option>1 - 2.5 Juta</option><option>2.5 - 5 Juta</option><option>> 5 Juta</option></select></div>
                </div>
                <div style="display:flex; gap:10px; margin-top:14px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddAnggota()">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Anggota</button>
                </div>
            </form>
        </div>

        <!-- ANGGOTA LIST -->
        @if($kk->anggota->count() > 0)

        {{-- Desktop table --}}
        <div class="data-table-wrapper anggota-desktop-table" style="margin:0;">
            <table class="data-table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:30px;">No</th>
                        <th>Nama</th>
                        <th style="width:130px;">NIK KTP</th>
                        <th style="width:35px; text-align:center;">L/P</th>
                        <th style="width:80px;">Status</th>
                        <th>Pekerjaan</th>
                        <th style="width:110px;">BPJS</th>
                        <th style="width:70px; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kk->anggota as $i => $ag)
                    <tr id="rowAnggota{{ $ag->id }}">
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $ag->nama }}</strong></td>
                        <td style="font-family:monospace; font-size:11px;">{{ $ag->nik ?? '-' }}</td>
                        <td style="text-align:center;">{{ $ag->jenisKelamin }}</td>
                        <td><span style="background:var(--biru-pale); color:var(--biru); padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">{{ $ag->statusKeluarga }}</span></td>
                        <td>{{ $ag->pekerjaan ?? '-' }}</td>
                        <td>
                            @if($ag->statusBPJS == 'PBI')
                                <span style="background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">✅ PBI</span>
                            @elseif($ag->statusBPJS == 'Mandiri')
                                <span style="background:var(--biru-pale); color:var(--biru); padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">💳 Mandiri</span>
                            @elseif($ag->statusBPJS == 'Tidak Aktif')
                                <span style="background:var(--merah-pale); color:var(--merah); padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">❌ Nonaktif</span>
                            @else
                                <span style="color:var(--text3); font-size:11px;">- Belum</span>
                            @endif
                        </td>
                        <td style="text-align:center; white-space:nowrap;">
                            <button type="button" class="btn btn-outline btn-sm" style="padding:3px 6px;" onclick="toggleEditAnggota({{ $ag->id }})" title="Edit"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('warga.anggota.destroy', [$kk->id, $ag->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus anggota {{ $ag->nama }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="padding:3px 6px; color:var(--merah); border-color:var(--merah);" title="Hapus"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <!-- EDIT ROW (hidden) -->
                    <tr id="editAnggota{{ $ag->id }}" style="display:none; background:var(--biru-pale);">
                        <td colspan="8" style="padding:12px;">
                            <form action="{{ route('warga.anggota.update', [$kk->id, $ag->id]) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="edit-grid-2">
                                    <div><label class="f-label">Nama</label><input type="text" name="nama" value="{{ $ag->nama }}" required class="f-input" style="font-size:12px; padding:6px 8px;"></div>
                                    <div><label class="f-label">NIK KTP</label><input type="text" name="nik" value="{{ $ag->nik }}" class="f-input" style="font-size:12px; padding:6px 8px;" maxlength="16"></div>
                                </div>
                                <div class="edit-grid-3" style="margin-top:8px;">
                                    <div><label class="f-label">L/P</label><select name="jenisKelamin" required class="f-input" style="font-size:12px; padding:6px 8px;"><option value="L" {{ $ag->jenisKelamin=='L'?'selected':'' }}>L</option><option value="P" {{ $ag->jenisKelamin=='P'?'selected':'' }}>P</option></select></div>
                                    <div><label class="f-label">Status</label><select name="statusKeluarga" required class="f-input" style="font-size:12px; padding:6px 8px;">@foreach(['Istri','Anak','Cucu','Orang Tua','Mertua','Menantu','Famili Lain'] as $st)<option {{ $ag->statusKeluarga==$st?'selected':'' }}>{{ $st }}</option>@endforeach</select></div>
                                    <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" value="{{ $ag->pekerjaan }}" class="f-input" style="font-size:12px; padding:6px 8px;"></div>
                                </div>
                                <div class="edit-grid-2" style="margin-top:8px;">
                                    <div><label class="f-label">🏥 BPJS</label><select name="statusBPJS" class="f-input" style="font-size:12px; padding:6px 8px;"><option value="" {{ !$ag->statusBPJS?'selected':'' }}>Belum</option><option value="PBI" {{ $ag->statusBPJS=='PBI'?'selected':'' }}>✅ PBI</option><option value="Mandiri" {{ $ag->statusBPJS=='Mandiri'?'selected':'' }}>💳 Mandiri</option><option value="Tidak Aktif" {{ $ag->statusBPJS=='Tidak Aktif'?'selected':'' }}>❌ Tidak Aktif</option></select></div>
                                    <div><label class="f-label">No. BPJS</label><input type="text" name="noBPJS" value="{{ $ag->noBPJS }}" class="f-input" style="font-size:12px; padding:6px 8px;"></div>
                                </div>
                                <div style="display:flex; gap:8px; margin-top:8px;">
                                    <button type="button" class="btn btn-outline btn-sm" style="font-size:11px;" onclick="toggleEditAnggota({{ $ag->id }})">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-sm" style="font-size:11px;"><i class="fas fa-check"></i> Simpan</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="anggota-mobile-cards">
            @foreach($kk->anggota as $i => $ag)
            <div class="anggota-card">
                <div class="anggota-card-top">
                    <div class="anggota-card-avatar">{{ strtoupper(substr($ag->nama, 0, 1)) }}</div>
                    <div class="anggota-card-info">
                        <div class="anggota-card-name">{{ $ag->nama }}</div>
                        <div class="anggota-card-meta">
                            <span>{{ $ag->jenisKelamin == 'L' ? '♂' : '♀' }} {{ $ag->statusKeluarga }}</span>
                            @if($ag->pekerjaan)<span>• {{ $ag->pekerjaan }}</span>@endif
                        </div>
                    </div>
                    <div>
                        @if($ag->statusBPJS == 'PBI')
                            <span style="background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">✅ PBI</span>
                        @elseif($ag->statusBPJS == 'Mandiri')
                            <span style="background:var(--biru-pale); color:var(--biru); padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">💳 Mandiri</span>
                        @elseif($ag->statusBPJS == 'Tidak Aktif')
                            <span style="background:var(--merah-pale); color:var(--merah); padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">❌</span>
                        @endif
                    </div>
                </div>
                <div class="anggota-card-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleEditAnggota({{ $ag->id }})"><i class="fas fa-edit"></i> Edit</button>
                    <form action="{{ route('warga.anggota.destroy', [$kk->id, $ag->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus anggota {{ $ag->nama }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--merah); border-color:var(--merah);"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        @else
        <div style="text-align:center; padding:30px 20px; color:var(--text3);">
            <i class="fas fa-users" style="font-size:32px; opacity:0.3; margin-bottom:8px;"></i>
            <p style="font-size:13px;">Belum ada anggota keluarga. Klik <strong>"+ Tambah"</strong> untuk menambahkan.</p>
        </div>
        @endif
    </div>
</div>

{{-- Modal Edit Anggota · independen dari layout, dipakai bersama tampilan desktop & mobile --}}
<div class="ag-modal-overlay" id="agEditModal">
    <div class="ag-modal">
        <div class="ag-modal-head">
            <h3><i class="fas fa-user-pen" style="color:var(--hijau);"></i> Edit Anggota Keluarga</h3>
            <button type="button" class="ag-modal-close" onclick="closeEditAnggota()" aria-label="Tutup">&times;</button>
        </div>
        <div class="ag-modal-body">
            <form id="agEditForm" method="POST">
                @csrf @method('PUT')
                <div class="edit-grid-2">
                    <div><label class="f-label">Nama</label><input type="text" name="nama" id="agm_nama" required class="f-input"></div>
                    <div><label class="f-label">NIK KTP</label><input type="text" name="nik" id="agm_nik" class="f-input" maxlength="16" inputmode="numeric"></div>
                </div>
                <div class="edit-grid-3" style="margin-top:12px;">
                    <div><label class="f-label">L/P</label><select name="jenisKelamin" id="agm_jk" required class="f-input"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div><label class="f-label">Status</label><select name="statusKeluarga" id="agm_status" required class="f-input">@foreach(['Istri','Anak','Cucu','Orang Tua','Mertua','Menantu','Famili Lain'] as $st)<option>{{ $st }}</option>@endforeach</select></div>
                    <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" id="agm_kerja" class="f-input"></div>
                </div>
                <div class="edit-grid-2" style="margin-top:12px;">
                    <div><label class="f-label">Status BPJS</label><select name="statusBPJS" id="agm_bpjs" class="f-input"><option value="">Belum Terdaftar</option><option value="PBI">PBI</option><option value="Mandiri">Mandiri</option><option value="Tidak Aktif">Tidak Aktif</option></select></div>
                    <div><label class="f-label">No. BPJS</label><input type="text" name="noBPJS" id="agm_nobpjs" class="f-input"></div>
                </div>
                <div class="edit-grid-2" style="margin-top:12px;">
                    <div><label class="f-label">Tanggal Lahir</label><input type="date" name="tanggalLahir" id="agm_tgl" class="f-input"></div>
                    <div><label class="f-label">Pendidikan</label><select name="pendidikan" id="agm_pend" class="f-input"><option value="">-</option><option>Tidak/Belum Sekolah</option><option>SD</option><option>SMP</option><option>SMA/SMK</option><option>D1-D3</option><option>S1</option><option>S2/S3</option></select></div>
                </div>
                <div class="edit-grid-2" style="margin-top:12px;">
                    <div><label class="f-label">Status Kawin</label><select name="statusPerkawinan" id="agm_kawin" class="f-input"><option value="">-</option><option>Belum Kawin</option><option>Kawin</option><option>Cerai Hidup</option><option>Cerai Mati</option></select></div>
                    <div><label class="f-label">Agama</label><select name="agama" id="agm_agama" class="f-input"><option value="">-</option><option>Islam</option><option>Kristen</option><option>Katolik</option><option>Hindu</option><option>Buddha</option><option>Konghucu</option></select></div>
                </div>
                <div class="edit-grid-2" style="margin-top:12px; padding-top:12px; border-top:1px dashed var(--abu2);">
                    <div><label class="f-label">Status Kerja</label><select name="statusPekerjaan" id="agm_skerja" class="f-input"><option value="">- Otomatis dari pekerjaan -</option><option>Bekerja</option><option>Tidak Bekerja</option><option>Mencari Kerja</option><option>Sekolah</option><option>Mengurus Rumah Tangga</option><option>Pensiunan</option></select></div>
                    <div><label class="f-label">Penghasilan/Bulan</label><select name="penghasilan" id="agm_phasil" class="f-input"><option value="">- Bila bekerja -</option><option>< 1 Juta</option><option>1 - 2.5 Juta</option><option>2.5 - 5 Juta</option><option>> 5 Juta</option></select></div>
                </div>
                <div class="edit-btn-row">
                    <button type="button" class="btn btn-outline" onclick="closeEditAnggota()">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.f-label { display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px; }
.f-input { width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white; outline:none; transition:border-color 0.2s; box-sizing:border-box; }
.f-input:focus { border-color:var(--hijau); box-shadow:0 0 0 3px rgba(46,125,50,0.1); }
.f-check { display:flex; align-items:center; gap:6px; padding:8px 10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s; }
.f-check:hover { border-color:var(--hijau); }
.f-check input { accent-color:var(--hijau); width:14px; height:14px; }
.step-panel { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:none;} }

/* Edit form layout classes */
.edit-form-wrap { max-width:640px; }
.edit-anggota-wrap { max-width:900px; margin-top:24px; }
.edit-fields { display:grid; gap:14px; }
.edit-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.edit-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.edit-check-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; }
.edit-btn-row { display:flex; gap:10px; margin-top:20px; }

/* Step progress */
.edit-steps { display:flex; align-items:center; gap:0; margin-bottom:24px; padding:0 4px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
.edit-step-dot { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.edit-step-line { width:40px; height:2px; background:var(--abu2); margin-right:4px; }
.edit-step-label { display:flex; align-items:center; gap:6px; padding:8px 14px; border-radius:20px; font-size:12px; font-weight:700; transition:all 0.3s; cursor:pointer; background:var(--abu); color:var(--text3); white-space:nowrap; }
.edit-step-label.active { background:var(--hijau); color:white; }

/* Anggota mobile cards */
.anggota-mobile-cards { display:none; }
.anggota-desktop-table { display:block; }

.anggota-card { padding:12px; border:1px solid var(--abu2); border-radius:var(--radius-sm); margin-bottom:8px; }
.anggota-card-top { display:flex; align-items:center; gap:10px; }
.anggota-card-avatar { width:36px; height:36px; border-radius:50%; background:var(--biru); color:white; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; }
.anggota-card-info { flex:1; min-width:0; }
.anggota-card-name { font-weight:700; font-size:13px; }
.anggota-card-meta { font-size:11px; color:var(--text3); margin-top:2px; }
.anggota-card-actions { display:flex; gap:8px; margin-top:8px; padding-top:8px; border-top:1px solid var(--abu2); }

/* Modal edit anggota (bersama desktop + mobile) */
.ag-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:1100; align-items:center; justify-content:center; padding:16px; }
.ag-modal-overlay.show { display:flex; }
.ag-modal { background:#fff; border-radius:var(--radius); width:100%; max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-lg); animation:fadeIn .2s ease; }
.ag-modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px; border-bottom:1px solid var(--abu2); position:sticky; top:0; background:#fff; }
.ag-modal-head h3 { font-size:15px; display:flex; align-items:center; gap:8px; }
.ag-modal-close { background:none; border:none; font-size:24px; line-height:1; color:var(--text3); cursor:pointer; padding:0 4px; }
.ag-modal-body { padding:18px; }

/* Mobile overrides */
@media (max-width: 768px) {
    .edit-form-wrap, .edit-anggota-wrap { max-width:100% !important; }
    .edit-grid-2 { grid-template-columns:1fr !important; }
    .edit-grid-3 { grid-template-columns:1fr !important; }
    .edit-check-grid { grid-template-columns:1fr 1fr !important; }
    .edit-steps { padding:0; }
    .edit-step-line { width:20px; }
    .edit-step-label { padding:6px 10px; font-size:11px; }
    .anggota-desktop-table { display:none !important; }
    .anggota-mobile-cards { display:block; }
}

@media (max-width: 480px) {
    .edit-check-grid { grid-template-columns:1fr !important; }
}
</style>

<script>
let currentStep = 1;
const totalSteps = 5;
function goStep(n) {
    document.getElementById('step'+currentStep).style.display='none';
    document.getElementById('step'+n).style.display='block';
    currentStep=n;
    for(let i=1;i<=totalSteps;i++){
        const l=document.getElementById('stepLabel'+i);
        if(i<n){l.style.background='var(--hijau-pale)';l.style.color='var(--hijau)';l.classList.remove('active')}
        else if(i===n){l.style.background='var(--hijau)';l.style.color='white';l.classList.add('active')}
        else{l.style.background='var(--abu)';l.style.color='var(--text3)';l.classList.remove('active')}
        if(i>1)document.getElementById('stepLine'+i).style.background=i<=n?'var(--hijau)':'var(--abu2)';
    }
    window.scrollTo({top:0,behavior:'smooth'});
}

// ========== ANGGOTA KELUARGA TOGGLE ==========
function toggleAddAnggota() {
    const f = document.getElementById('addAnggotaForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
// Data semua anggota (untuk modal edit bersama desktop + mobile)
const agData = {
    @foreach($kk->anggota as $ag)
    {{ $ag->id }}: { nama: @json($ag->nama), nik: @json($ag->nik), jk: @json($ag->jenisKelamin), status: @json($ag->statusKeluarga), kerja: @json($ag->pekerjaan), bpjs: @json($ag->statusBPJS), nobpjs: @json($ag->noBPJS), tgl: @json($ag->tanggalLahir ? \Carbon\Carbon::parse($ag->tanggalLahir)->format('Y-m-d') : ''), pend: @json($ag->pendidikan), kawin: @json($ag->statusPerkawinan), agama: @json($ag->agama), skerja: @json($ag->statusPekerjaan), phasil: @json($ag->penghasilan) },
    @endforeach
};
const agUpdateBase = "{{ url('warga/'.$kk->id.'/anggota') }}";

// Buka modal edit anggota · dipakai oleh tombol Edit di tabel desktop MAUPUN kartu mobile
function toggleEditAnggota(id) {
    const d = agData[id];
    if (!d) return;
    document.getElementById('agEditForm').action = agUpdateBase + '/' + id;
    document.getElementById('agm_nama').value = d.nama || '';
    document.getElementById('agm_nik').value = d.nik || '';
    document.getElementById('agm_jk').value = d.jk || 'L';
    document.getElementById('agm_status').value = d.status || 'Anak';
    document.getElementById('agm_kerja').value = d.kerja || '';
    document.getElementById('agm_bpjs').value = d.bpjs || '';
    document.getElementById('agm_nobpjs').value = d.nobpjs || '';
    document.getElementById('agm_tgl').value = d.tgl || '';
    document.getElementById('agm_pend').value = d.pend || '';
    document.getElementById('agm_kawin').value = d.kawin || '';
    document.getElementById('agm_agama').value = d.agama || '';
    document.getElementById('agm_skerja').value = d.skerja || '';
    document.getElementById('agm_phasil').value = d.phasil || '';
    document.getElementById('agEditModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeEditAnggota() {
    document.getElementById('agEditModal').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('agEditModal').addEventListener('click', function (e) {
    if (e.target.id === 'agEditModal') closeEditAnggota();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeEditAnggota();
});
</script>
@endsection
