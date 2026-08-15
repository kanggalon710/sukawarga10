@extends('layouts.app')
@section('title', 'Profil Saya')
@section('page-title', 'Profil Data Keluarga')
@section('page-subtitle'){{ $kk->nama }} · RT {{ $kk->rt }}@endsection

@section('content')

{{-- Completion Progress --}}
<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="font-weight:700; font-size:14px;"><i class="fas fa-chart-pie" style="color:var(--hijau);"></i> Kelengkapan Data</div>
            <span style="font-size:20px; font-weight:900; color:{{ $completion >= 80 ? 'var(--hijau)' : ($completion >= 50 ? '#b45309' : 'var(--merah)') }};">{{ $completion }}%</span>
        </div>
        <div style="background:var(--abu2); border-radius:10px; height:10px; overflow:hidden;">
            <div style="background:{{ $completion >= 80 ? 'var(--hijau)' : ($completion >= 50 ? '#f59e0b' : 'var(--merah)') }}; height:100%; width:{{ $completion }}%; border-radius:10px; transition:width 0.5s;"></div>
        </div>
        <div style="font-size:11px; color:var(--text3); margin-top:6px;">
            @if($completion < 50) ⚠️ Data masih kurang lengkap. Mohon lengkapi data Anda.
            @elseif($completion < 75) 📝 Tinggal sedikit lagi! Lengkapi hingga 75% untuk mendapat notifikasi apresiasi.
            @elseif($completion < 100) 🎉 Hebat! Tinggal sedikit lagi menuju 100% · data lengkap = pelayanan optimal!
            @else 🏆 Data Anda sudah 100% lengkap. Terima kasih, warga teladan! ⭐
            @endif
        </div>
    </div>
</div>

<form action="{{ route('profil.update') }}" method="POST" enctype="multipart/form-data">
@csrf @method('PUT')
<div style="max-width:640px;">

{{-- IDENTITAS --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-id-card" style="color:var(--hijau);"></i> Identitas</div></div>
    <div style="display:grid; gap:14px; padding:16px;">
        <div><label class="f-label">Nama Kepala Keluarga</label><input type="text" value="{{ $kk->nama }}" class="f-input" readonly style="background:#f1f5f9; cursor:not-allowed;" title="Hubungi admin untuk mengubah nama"></div>
        <div class="edit-grid-2">
            <div><label class="f-label">RT</label><input type="text" value="RT {{ $kk->rt }}" class="f-input" readonly style="background:#f1f5f9; cursor:not-allowed;"></div>
            <div><label class="f-label">Status</label><input type="text" value="{{ ucfirst($kk->status) }}" class="f-input" readonly style="background:#f1f5f9; cursor:not-allowed;"></div>
        </div>
        <div class="edit-grid-2">
            <div><label class="f-label">No. Kartu Keluarga</label><input type="text" name="noKK" value="{{ $kk->noKK }}" class="f-input" placeholder="16 digit" maxlength="16"></div>
            <div><label class="f-label">NIK KTP</label><input type="text" name="nik" value="{{ $kk->nik }}" class="f-input" placeholder="16 digit" maxlength="16"></div>
        </div>
        <div><label class="f-label">No. HP / WhatsApp</label><input type="tel" name="noHP" value="{{ $kk->noHP }}" class="f-input"></div>
        <div class="edit-grid-2">
            <div>
                <label class="f-label">Tanggal Lahir KK</label>
                <input type="date" name="tanggalLahirKK" value="{{ $kk->tanggalLahirKK ? $kk->tanggalLahirKK->format('Y-m-d') : '' }}" class="f-input">
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
        <div><label class="f-label">Alamat</label><textarea name="alamat" rows="2" class="f-input" style="font-family:inherit;">{{ $kk->alamat }}</textarea></div>
    </div>
</div>

{{-- RUMAH & SANITASI --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-home" style="color:var(--biru);"></i> Rumah & Sanitasi</div></div>
    <div style="display:grid; gap:14px; padding:16px;">
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
            <div><label class="f-label">Bahan Lantai</label><select name="bahanLantai" class="f-input">@foreach(['' => '-','Keramik','Ubin','Semen','Tanah'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->bahanLantai == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Bahan Dinding</label><select name="bahanDinding" class="f-input">@foreach(['' => '-','Tembok','Kayu','Bambu','GRC'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->bahanDinding == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Bahan Atap</label><select name="bahanAtap" class="f-input">@foreach(['' => '-','Genteng','Seng','Asbes','Daun/Jerami'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->bahanAtap == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
        </div>
        <div class="edit-grid-2">
            <div><label class="f-label">Air Minum</label><select name="sumberAirMinum" class="f-input">@foreach(['' => '-','PDAM','Sumur Bor','Sumur Gali','Mata Air','Air Hujan'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->sumberAirMinum == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Air Mandi</label><select name="sumberAirMandi" class="f-input">@foreach(['' => '-','PDAM','Sumur Bor','Sumur Gali','Mata Air','Sungai'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->sumberAirMandi == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
        </div>
        <div class="edit-grid-2">
            <div><label class="f-label">Sumber Masak</label><select name="sumberMasak" class="f-input">@foreach(['' => '-','Gas LPG','Kayu Bakar','Minyak Tanah','Listrik'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->sumberMasak == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Listrik</label><select name="sumberListrik" class="f-input">@foreach(['' => '-','PLN','PLN + Solar','Non-PLN','Tidak Ada'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->sumberListrik == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
        </div>
        <div class="edit-grid-3">
            <div><label class="f-label">Jamban</label><select name="kepemilikanJamban" class="f-input">@foreach(['' => '-','Sendiri','Bersama','Umum','Tidak Ada'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->kepemilikanJamban == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Tinja</label><select name="pembuanganTinja" class="f-input">@foreach(['' => '-','Septic Tank','Cemplung','Sungai','Lainnya'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->pembuanganTinja == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
            <div><label class="f-label">Sampah</label><select name="caraBuangSampah" class="f-input">@foreach(['' => '-','Diangkut Petugas','Dibakar','Dibuang Sembarangan','Ditimbun'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->caraBuangSampah == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
        </div>
    </div>
</div>

{{-- EKONOMI --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-wallet" style="color:var(--emas);"></i> Ekonomi</div></div>
    <div style="display:grid; gap:14px; padding:16px;">
        <div class="edit-grid-2">
            <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" value="{{ $kk->pekerjaan }}" class="f-input"></div>
            <div><label class="f-label">Sumber Pendapatan</label><select name="sumberPendapatan" class="f-input">@foreach(['' => '-','Gaji Tetap','Usaha Sendiri','Buruh Harian','Pertanian','Lainnya'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->sumberPendapatan == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
        </div>
        <div><label class="f-label">Penghasilan/Bulan</label><select name="penghasilan" class="f-input">@foreach(['' => '-','< 500rb','500rb - 1jt','1jt - 2,5jt','2,5jt - 5jt','> 5jt'] as $v)<option value="{{ is_int($v) ? '' : $v }}" {{ $kk->penghasilan == $v ? 'selected' : '' }}>{{ $v ?: '-' }}</option>@endforeach</select></div>
    </div>
</div>

{{-- DOKUMEN --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-file-alt" style="color:#7e57c2;"></i> Dokumen & Foto</div></div>
    <div style="display:grid; gap:16px; padding:16px;">
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
</div>

<button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:15px; margin-bottom:30px;">
    <i class="fas fa-save"></i> Simpan Perubahan
</button>

</div>
</form>

{{-- ANGGOTA KELUARGA --}}
<div class="card" style="max-width:900px; margin-top:8px; margin-bottom:30px;">
    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <div class="card-title"><i class="fas fa-users" style="color:var(--hijau);"></i> Anggota Keluarga ({{ $kk->anggota->count() }} jiwa)</div>
        <button type="button" class="btn btn-primary btn-sm" onclick="toggleAddAnggota()" id="btnToggleAnggota"><i class="fas fa-plus"></i> Tambah</button>
    </div>

    {{-- ADD ANGGOTA FORM --}}
    <div id="formAddAnggota" style="display:none; padding:16px; border-bottom:2px solid var(--abu2); background:#f0fdf4;">
        <div style="font-weight:700; font-size:13px; margin-bottom:12px;"><i class="fas fa-user-plus" style="color:var(--hijau);"></i> Tambah Anggota Keluarga</div>
        <form action="{{ route('profil.anggota.store') }}" method="POST">
            @csrf
            <div class="edit-grid-2">
                <div><label class="f-label">Nama Lengkap <span style="color:var(--merah);">*</span></label><input type="text" name="nama" class="f-input" required placeholder="Nama lengkap anggota"></div>
                <div><label class="f-label">NIK KTP</label><input type="text" name="nik" class="f-input" placeholder="16 digit" maxlength="16"></div>
            </div>
            <div class="edit-grid-2" style="margin-top:10px;">
                <div><label class="f-label">Jenis Kelamin <span style="color:var(--merah);">*</span></label>
                    <select name="jenisKelamin" class="f-input" required>
                        <option value="">- Pilih -</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div><label class="f-label">Status dalam KK <span style="color:var(--merah);">*</span></label>
                    <select name="statusKeluarga" class="f-input" required>
                        <option value="">- Pilih -</option>
                        <option value="Istri">Istri</option>
                        <option value="Suami">Suami</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                        <option value="Mertua">Mertua</option>
                        <option value="Menantu">Menantu</option>
                        <option value="Cucu">Cucu</option>
                        <option value="Famili Lain">Famili Lain</option>
                    </select>
                </div>
            </div>
            <div class="edit-grid-2" style="margin-top:10px;">
                <div><label class="f-label">Pekerjaan</label><input type="text" name="pekerjaan" class="f-input" placeholder="Opsional"></div>
                <div><label class="f-label">Pendidikan</label>
                    <select name="pendidikan" class="f-input">
                        <option value="">- Pilih -</option>
                        <option value="SD">SD / Sederajat</option>
                        <option value="SMP">SMP / Sederajat</option>
                        <option value="SMA">SMA / Sederajat</option>
                        <option value="D3">Diploma III</option>
                        <option value="S1">Sarjana (S1)</option>
                        <option value="S2">Magister (S2)</option>
                        <option value="Belum Sekolah">Belum Sekolah</option>
                    </select>
                </div>
            </div>
            <div class="edit-grid-2" style="margin-top:10px;">
                <div><label class="f-label">Tempat Lahir</label><input type="text" name="tempatLahir" class="f-input" placeholder="Kota / Kabupaten"></div>
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
            <div style="display:flex; gap:10px; margin-top:14px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddAnggota()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Anggota</button>
            </div>
        </form>
    </div>

    {{-- ANGGOTA LIST --}}
    @if($kk->anggota->count() > 0)
    <div style="padding:0;">
        @foreach($kk->anggota as $i => $ag)
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid var(--abu2); {{ $loop->last ? 'border-bottom:none;' : '' }}">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:36px; height:36px; border-radius:50%; background:{{ $ag->jenisKelamin == 'L' ? 'var(--biru-pale)' : '#fce7f3' }}; color:{{ $ag->jenisKelamin == 'L' ? 'var(--biru)' : '#db2777' }}; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">{{ strtoupper(substr($ag->nama, 0, 1)) }}</div>
                <div>
                    <div style="font-weight:600; font-size:14px;">{{ $ag->nama }}</div>
                    <div style="font-size:11px; color:var(--text3);">
                        {{ $ag->jenisKelamin == 'L' ? '♂' : '♀' }} {{ $ag->statusKeluarga }}
                        @if($ag->nik) · <span style="font-family:monospace;">{{ $ag->nik }}</span>@endif
                        @if($ag->pekerjaan) · {{ $ag->pekerjaan }}@endif
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                @if($ag->statusBPJS == 'PBI')
                    <span style="background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">✅ PBI</span>
                @elseif($ag->statusBPJS == 'Mandiri')
                    <span style="background:var(--biru-pale); color:var(--biru); padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">💳 Mandiri</span>
                @endif
                <form action="{{ route('profil.anggota.destroy', $ag->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus anggota {{ $ag->nama }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--merah); border-color:var(--merah); padding:4px 8px; font-size:11px;"><i class="fas fa-trash"></i></button>
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

<style>
.f-label { display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px; }
.f-input { width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white; outline:none; transition:border-color 0.2s; box-sizing:border-box; }
.f-input:focus { border-color:var(--hijau); box-shadow:0 0 0 3px rgba(46,125,50,0.1); }
.edit-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.edit-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
@media (max-width: 768px) {
    .edit-grid-2 { grid-template-columns:1fr !important; }
    .edit-grid-3 { grid-template-columns:1fr !important; }
}
</style>

<script>
function toggleAddAnggota() {
    const f = document.getElementById('formAddAnggota');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>
@endsection
