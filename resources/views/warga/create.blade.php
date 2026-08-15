@extends('layouts.app')
@section('title', 'Tambah Warga')
@section('page-title', 'Tambah Data Keluarga')
@section('page-subtitle', 'Form pendataan keluarga baru · 5 tahap')

@section('content')

<style>
/* ─── Modern Form Design ─── */
.f-label { display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px; }
.f-label span.req { color:var(--merah); margin-left:2px; }
.f-input {
    width:100%; padding:10px 12px; border:1.5px solid var(--abu2);
    border-radius:var(--radius-sm); font-size:14px; background:white;
    font-family:inherit; color:var(--text); transition:border-color 0.2s; box-sizing:border-box;
}
.f-input:focus { border-color:var(--hijau); outline:none; box-shadow:0 0 0 3px rgba(46,125,50,0.1); }
.f-input.error { border-color:var(--merah) !important; background:#fff5f5; }
.f-input.valid { border-color:var(--hijau); }
.f-hint { font-size:11px; color:var(--text3); margin-top:4px; display:flex; align-items:center; gap:4px; }
.f-hint.error-msg { color:var(--merah); display:none; }


.f-group { display:flex; flex-direction:column; }
.f-check {
    display:flex; align-items:center; gap:8px; padding:10px 12px;
    border:1.5px solid var(--abu2); border-radius:10px; font-size:12px;
    font-weight:600; cursor:pointer; transition:all 0.2s; background:white;
}
.f-check:hover { border-color:var(--hijau); background:var(--hijau-pale); }
.f-check input { accent-color:var(--hijau); width:15px; height:15px; flex-shrink:0; }
.f-check.checked { border-color:var(--hijau); background:var(--hijau-pale); }

/* Step bar */
.step-bar { display:flex; align-items:center; margin-bottom:24px; overflow-x:auto; padding:4px 0; }
.step-item {
    display:flex; align-items:center; gap:7px; padding:8px 14px;
    border-radius:20px; font-size:12px; font-weight:600; cursor:pointer;
    transition:all 0.3s; white-space:nowrap; flex-shrink:0; font-family:inherit;
}
.step-item.active { background:var(--hijau); color:white; box-shadow:0 3px 10px rgba(46,125,50,0.25); }
.step-item.done   { background:var(--hijau-pale); color:var(--hijau); }
.step-item.locked { background:var(--abu); color:var(--text3); cursor:default; }
.step-item .step-opt { font-weight:400; opacity:0.6; }
.step-line { flex:1; min-width:20px; height:2px; background:var(--abu2); margin:0 4px; transition:background 0.3s; flex-shrink:0; }
.step-line.done { background:var(--hijau); }

/* Card section */
.form-section { background:white; border-radius:var(--radius); border:1.5px solid var(--abu2); padding:22px; }
.form-section-header {
    display:flex; align-items:center; gap:12px; margin-bottom:20px;
    padding-bottom:14px; border-bottom:1.5px solid var(--abu2);
}
.form-section-icon {
    width:40px; height:40px; border-radius:10px;
    display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;
}

/* Digit input (noKK, NIK) */
.digit-input-wrap { position:relative; }
.digit-input-wrap .digit-counter {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    font-size:11px; font-weight:700; color:var(--text3);
    background:var(--abu); padding:2px 7px; border-radius:6px;
}
.digit-input-wrap .digit-counter.full { color:var(--hijau); background:var(--hijau-pale); }
.digit-input-wrap .digit-counter.over { color:var(--merah); background:var(--merah-pale); }
.f-input.digit-f { padding-right:60px; letter-spacing:1.5px; font-variant-numeric:tabular-nums; }

.step-panel { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:translateY(0);} }
</style>

{{-- Back + Step bar --}}
<div class="toolbar"><div class="toolbar-left"><a href="{{ route('warga.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div></div>

<div class="step-bar" id="stepBar">
    @php $steps = [1=>'Identitas', 2=>'Rumah', 3=>'Ekonomi', 4=>'Program', 5=>'Dokumen']; @endphp
    @foreach($steps as $n => $label)
        @if($n > 1)<div class="step-line" id="stepLine{{ $n }}"></div>@endif
        <div class="step-item {{ $n===1 ? 'active' : 'locked' }}" id="stepLabel{{ $n }}" onclick="goStepDirect({{ $n }})">
            <span id="stepNum{{ $n }}">{{ $n }}</span>
            <span>{{ $label }}@if($n > 1) <span class="step-opt">(Opsional)</span>@endif</span>
        </div>
    @endforeach
</div>

<form action="{{ route('warga.store') }}" method="POST" id="wargaForm" novalidate enctype="multipart/form-data">
@csrf
<div style="max-width:640px;">

{{-- ══════════════════════════════ STEP 1: IDENTITAS ══════════════════════════════ --}}
<div id="step1" class="step-panel">
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon" style="background:var(--hijau-pale);">🪪</div>
            <div>
                <div style="font-weight:800; font-size:16px;">Identitas Kepala Keluarga</div>
                <div style="font-size:12px; color:var(--text3); margin-top:2px;">Data wajib: nama, RT, dan alamat</div>
            </div>
        </div>

        <div style="display:grid; gap:16px;">
            {{-- Nama --}}
            <div class="f-group">
                <label class="f-label">Nama Kepala Keluarga <span class="req">*</span></label>
                <input type="text" name="nama" id="fNama" required class="f-input"
                    placeholder="Nama lengkap sesuai KTP/KK"
                    oninput="validateNama()" autocomplete="name">
                <span class="f-hint error-msg" id="errNama">⚠ Nama tidak boleh kosong</span>
            </div>

            {{-- RT + Jiwa --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group">
                    <label class="f-label">RT <span class="req">*</span></label>
                    <select name="rt" id="fRT" required class="f-input">
                        <option value="">- Pilih RT -</option>
                        @for($i=1;$i<=6;$i++)
                        <option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}">RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="f-group">
                    <label class="f-label">Jumlah Jiwa <span class="req">*</span></label>
                    <input type="number" name="jumlahAnggota" value="1" min="1" max="30" required class="f-input" placeholder="1">
                </div>
            </div>

            {{-- No KK + NIK --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group">
                    <label class="f-label">NIK Kartu Keluarga</label>
                    <div class="digit-input-wrap">
                        <input type="text" name="noKK" id="fNoKK" class="f-input digit-f"
                            placeholder="16 digit" maxlength="16"
                            inputmode="numeric" pattern="\d{16}"
                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,16); updateDigit(this,'ctrKK',16)"
                            autocomplete="off">
                        <span class="digit-counter" id="ctrKK">0/16</span>
                    </div>
                    <span class="f-hint">ℹ Kosongkan jika belum ada</span>
                    <span class="f-hint error-msg" id="errKK">⚠ NIK Kartu Keluarga harus tepat 16 digit angka</span>
                </div>
                <div class="f-group">
                    <label class="f-label">NIK KTP</label>
                    <div class="digit-input-wrap">
                        <input type="text" name="nik" id="fNIK" class="f-input digit-f"
                            placeholder="16 digit" maxlength="16"
                            inputmode="numeric" pattern="\d{16}"
                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,16); updateDigit(this,'ctrNIK',16)"
                            autocomplete="off">
                        <span class="digit-counter" id="ctrNIK">0/16</span>
                    </div>
                    <span class="f-hint">ℹ Sesuai KTP kepala keluarga</span>
                    <span class="f-hint error-msg" id="errNIK">⚠ NIK KTP harus tepat 16 digit angka</span>
                </div>
            </div>

            {{-- No HP --}}
            <div class="f-group">
                <label class="f-label">No. HP / WhatsApp</label>
                <input type="tel" name="noHP" id="fNoHP" class="f-input"
                    placeholder="08xxxx · nomor aktif untuk notifikasi WA"
                    inputmode="tel" autocomplete="tel"
                    oninput="this.value=this.value.replace(/[^0-9+\-\s]/g,'')">
                <span class="f-hint">ℹ Untuk notifikasi WhatsApp. Format bebas (08xx / 62xx / +62xx) · otomatis dirapikan ke 62xxxx</span>
            </div>

            {{-- Alamat --}}
            <div class="f-group">
                <label class="f-label">Alamat Lengkap <span class="req">*</span></label>
                <textarea name="alamat" id="fAlamat" rows="2" required class="f-input"
                    style="resize:vertical; font-family:inherit;" placeholder="Jl. / Kp. / Blok / RT / RW..."></textarea>
            </div>

            {{-- Tanggal Lahir + Jenis Kelamin --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group">
                    <label class="f-label">Tanggal Lahir KK</label>
                    <input type="date" name="tanggalLahirKK" class="f-input">
                </div>
                <div class="f-group">
                    <label class="f-label">Jenis Kelamin KK</label>
                    <select name="jenisKelaminKK" class="f-input">
                        <option value="">- Pilih -</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ANGGOTA KELUARGA INLINE --}}
        <div style="border-top:2px solid var(--abu2); margin-top:20px; padding-top:18px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="font-weight:800; font-size:14px;"><i class="fas fa-users" style="color:var(--biru);"></i> Anggota Keluarga</div>
                <button type="button" class="btn btn-primary btn-sm" onclick="addAnggotaRow()" style="font-size:12px;"><i class="fas fa-plus"></i> Tambah</button>
            </div>
            <div style="font-size:11px; color:var(--text3); margin-bottom:12px;">Tambahkan istri, anak, atau anggota keluarga lainnya. Kepala keluarga sudah termasuk otomatis.</div>
            <div id="anggotaContainer"></div>
            <div id="anggotaEmpty" style="text-align:center; padding:20px; color:var(--text3); background:var(--abu); border-radius:var(--radius-sm);">
                <i class="fas fa-users" style="font-size:24px; opacity:0.3; margin-bottom:6px;"></i>
                <div style="font-size:12px;">Belum ada anggota. Klik <strong>"+ Tambah"</strong> untuk menambahkan.</div>
            </div>
        </div>

        <button type="button" class="btn btn-primary" style="width:100%; margin-top:24px; padding:13px; font-size:14px;" onclick="goStep(2)">
            Lanjut ke Rumah & Sanitasi <i class="fas fa-arrow-right"></i>
        </button>
    </div>
</div>

{{-- ══════════════════════════════ STEP 2: RUMAH ══════════════════════════════ --}}
<div id="step2" class="step-panel" style="display:none;">
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon" style="background:var(--biru-pale);">🏠</div>
            <div>
                <div style="font-weight:800; font-size:16px;">Kondisi Rumah & Sanitasi</div>
                <div style="font-size:12px; color:var(--text3); margin-top:2px;">Opsional · untuk data sosial ekonomi</div>
            </div>
        </div>
        <div style="display:grid; gap:14px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Status Rumah</label>
                    <select name="statusRumah" class="f-input"><option value="">-</option><option>Milik Sendiri</option><option>Sewa/Kontrak</option><option>Menumpang</option><option>Dinas</option></select></div>
                <div class="f-group"><label class="f-label">Tipe Bangunan</label>
                    <select name="tipeBangunan" class="f-input"><option value="">-</option><option>Permanen</option><option>Semi Permanen</option><option>Kayu/Bilik</option></select></div>
            </div>
            <div class="f-group">
                <label class="f-label">📜 Jenis Sertifikat Tanah</label>
                <select name="jenisSertifikat" class="f-input">
                    <option value="">- Pilih Jenis Sertifikat -</option>
                    <option value="SHM">SHM (Sertifikat Hak Milik)</option>
                    <option value="SHGB">SHGB (Sertifikat Hak Guna Bangunan)</option>
                    <option value="AJB">AJB (Akta Jual Beli)</option>
                    <option value="Girik">Girik / Letter C</option>
                    <option value="Petok D">Petok D</option>
                    <option value="Belum Bersertifikat">Belum Bersertifikat</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Luas Lantai (m²)</label>
                    <input type="number" name="luasLantai" class="f-input" placeholder="36" min="0"></div>
                <div class="f-group"><label class="f-label">Jml Kamar Tidur</label>
                    <input type="number" name="jmlKamarTidur" min="0" class="f-input" placeholder="2"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Bahan Lantai</label>
                    <select name="bahanLantai" class="f-input"><option value="">-</option><option>Keramik</option><option>Ubin</option><option>Semen</option><option>Tanah</option></select></div>
                <div class="f-group"><label class="f-label">Bahan Dinding</label>
                    <select name="bahanDinding" class="f-input"><option value="">-</option><option>Tembok</option><option>Kayu</option><option>Bambu</option><option>GRC</option></select></div>
                <div class="f-group"><label class="f-label">Bahan Atap</label>
                    <select name="bahanAtap" class="f-input"><option value="">-</option><option>Genteng</option><option>Seng</option><option>Asbes</option><option>Daun/Jerami</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Sumber Air Minum</label>
                    <select name="sumberAirMinum" class="f-input"><option value="">-</option><option>PDAM</option><option>Sumur Bor</option><option>Sumur Gali</option><option>Mata Air</option><option>Air Hujan</option></select></div>
                <div class="f-group"><label class="f-label">Sumber Air Mandi</label>
                    <select name="sumberAirMandi" class="f-input"><option value="">-</option><option>PDAM</option><option>Sumur Bor</option><option>Sumur Gali</option><option>Mata Air</option><option>Sungai</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Sumber Masak</label>
                    <select name="sumberMasak" class="f-input"><option value="">-</option><option>Gas LPG</option><option>Kayu Bakar</option><option>Minyak Tanah</option><option>Listrik</option></select></div>
                <div class="f-group"><label class="f-label">Sumber Listrik</label>
                    <select name="sumberListrik" class="f-input"><option value="">-</option><option>PLN</option><option>PLN + Solar</option><option>Non-PLN</option><option>Tidak Ada</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Daya Listrik</label>
                    <select name="dayaListrik" class="f-input"><option value="">-</option><option>450 VA</option><option>900 VA</option><option>1300 VA</option><option>2200 VA+</option><option>Non-meteran</option></select></div>
                <div class="f-group"><label class="f-label">Akses Internet</label>
                    <select name="aksesInternet" class="f-input"><option value="">-</option><option>Tidak Ada</option><option>Seluler/HP</option><option>WiFi/Fixed</option></select></div>
                <div class="f-group"><label class="f-label">Rawan Bencana</label>
                    <select name="rawanBencana" class="f-input"><option value="">-</option><option>Tidak</option><option>Banjir</option><option>Longsor</option><option>Kebakaran</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Kepemilikan Jamban</label>
                    <select name="kepemilikanJamban" class="f-input"><option value="">-</option><option>Sendiri</option><option>Bersama</option><option>Umum</option><option>Tidak Ada</option></select></div>
                <div class="f-group"><label class="f-label">Pembuangan Tinja</label>
                    <select name="pembuanganTinja" class="f-input"><option value="">-</option><option>Septic Tank</option><option>Cemplung</option><option>Sungai</option><option>Lainnya</option></select></div>
                <div class="f-group"><label class="f-label">Buang Sampah</label>
                    <select name="caraBuangSampah" class="f-input"><option value="">-</option><option>Diangkut Petugas</option><option>Dibakar</option><option>Dibuang Sembarangan</option><option>Ditimbun</option></select></div>
            </div>
        </div>
        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(1)"><i class="fas fa-arrow-left"></i> Kembali</button>
            <button type="button" class="btn btn-outline" style="flex:0.7; font-size:13px;" onclick="goStep(3)">Lewati →</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="goStep(3)">Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════ STEP 3: EKONOMI ══════════════════════════════ --}}
<div id="step3" class="step-panel" style="display:none;">
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon" style="background:var(--emas-muda);">💼</div>
            <div>
                <div style="font-weight:800; font-size:16px;">Ekonomi & Aset</div>
                <div style="font-size:12px; color:var(--text3); margin-top:2px;">Opsional · profil ekonomi keluarga</div>
            </div>
        </div>
        <div style="display:grid; gap:14px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Pekerjaan</label>
                    <input type="text" name="pekerjaan" class="f-input" placeholder="Wiraswasta / PNS / Buruh..."></div>
                <div class="f-group"><label class="f-label">Sumber Pendapatan</label>
                    <select name="sumberPendapatan" class="f-input"><option value="">-</option><option>Gaji Tetap</option><option>Usaha Sendiri</option><option>Buruh Harian</option><option>Pertanian</option><option>Lainnya</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="f-group"><label class="f-label">Penghasilan / Bulan (Kepala Keluarga)</label>
                    <select name="penghasilan" class="f-input"><option value="">-</option><option value="< 1 Juta">&lt; 1 Juta</option><option value="1 - 2.5 Juta">1 - 2.5 Juta</option><option value="2.5 - 5 Juta">2.5 - 5 Juta</option><option value="> 5 Juta">&gt; 5 Juta</option></select></div>
                <div class="f-group"><label class="f-label">Akses Kredit</label>
                    <select name="aksesKredit" class="f-input"><option value="">-</option><option>Bank</option><option>Koperasi</option><option>Tidak Punya</option></select></div>
            </div>
            <div class="f-group"><label class="f-label">Jumlah Tanggungan</label>
                <input type="number" name="jumlahTanggungan" min="0" class="f-input" placeholder="Jumlah jiwa yang ditanggung kepala keluarga"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="punyaTabungan" value="1"> 💰 Punya Tabungan</label>
                <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="punyaHutangUsaha" value="1"> 📋 Punya Hutang Usaha</label>
            </div>
            <div>
                <label class="f-label">Kepemilikan Aset</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                    @foreach(['motor'=>'🏍️ Motor','mobil'=>'🚗 Mobil','tanah'=>'📐 Tanah','sawah'=>'🌾 Sawah','ternak'=>'🐄 Ternak','komputer'=>'💻 Komputer'] as $k=>$v)
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="aset_{{ $k }}" value="1"> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(2)"><i class="fas fa-arrow-left"></i> Kembali</button>
            <button type="button" class="btn btn-outline" style="flex:0.7; font-size:13px;" onclick="goStep(4)">Lewati →</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="goStep(4)">Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════ STEP 4: PROGRAM ══════════════════════════════ --}}
<div id="step4" class="step-panel" style="display:none;">
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon" style="background:var(--merah-pale);">🤝</div>
            <div>
                <div style="font-weight:800; font-size:16px;">Bansos, Kelompok Rentan & Program</div>
                <div style="font-size:12px; color:var(--text3); margin-top:2px;">Opsional · data sosial & pendaftaran iuran</div>
            </div>
        </div>
        <div style="display:grid; gap:18px;">
            <div>
                <label class="f-label">Bantuan Sosial yang Diterima</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                    @foreach(['bpjs'=>'🏥 BPJS/JKN','pkh'=>'PKH','bpnt'=>'BPNT (Sembako)','blt'=>'BLT','rutilahu'=>'Rutilahu','kis'=>'KIS','kip'=>'KIP'] as $k=>$v)
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="bansos_{{ $k }}" value="1"> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="f-label">Kelompok Rentan</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                    @foreach(['lansia'=>'👴 Lansia (≥60th)','balita'=>'👶 Balita','hamil'=>'🤰 Ibu Hamil','disabilitas'=>'♿ Disabilitas','jandaDuda'=>'Janda/Duda','yatimPiatu'=>'Yatim/Piatu'] as $k=>$v)
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="rentan_{{ $k }}" value="1"> {{ $v }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="f-label">Kesehatan Keluarga</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                    @foreach(['TBC','Hipertensi','Diabetes','Jantung','Gangguan Jiwa','Asma'] as $p)
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="penyakitKronis[]" value="{{ $p }}"> {{ $p }}</label>
                    @endforeach
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="ikutKB" value="1"> Ikut KB</label>
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="stunting" value="1"> Ada Balita Stunting</label>
                </div>
            </div>
            <div>
                <label class="f-label">Tag Sosial</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                    @foreach(['kurang_mampu'=>'Kurang Mampu','lansia'=>'Lansia','umkm'=>'UMKM','disabilitas'=>'Disabilitas','janda_duda'=>'Janda/Duda','migran'=>'Migran'] as $k=>$v)
                    <label class="f-check" onclick="this.classList.toggle('checked')"><input type="checkbox" name="tags[]" value="{{ $k }}"> {{ $v }}</label>
                    @endforeach
                </div>
            </div>

            {{-- Program Iuran --}}
            <div style="border-top:1.5px solid var(--abu2); padding-top:16px;">
                <label class="f-label">⚙️ Program Iuran</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <label class="f-check" style="background:var(--hijau-pale); border-color:var(--hijau);" onclick="this.classList.toggle('checked')">
                        <input type="checkbox" name="ikutSampah" value="1" checked> 🗑️ Ikut Iuran Sampah
                    </label>
                    <label class="f-check" style="background:var(--biru-pale); border-color:var(--biru);" onclick="this.classList.toggle('checked')">
                        <input type="checkbox" name="ikutPadaringan" value="1" checked> 🍚 Ikut Iuran Padaringan
                    </label>
                </div>
            </div>

            <div class="f-group">
                <label class="f-label">Catatan Khusus</label>
                <textarea name="catatan" rows="2" class="f-input" style="resize:vertical; font-family:inherit;" placeholder="Catatan tambahan (opsional)"></textarea>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(3)"><i class="fas fa-arrow-left"></i> Kembali</button>
            <button type="button" class="btn btn-primary" style="flex:2; padding:13px; font-size:14px;" onclick="goStep(5)">
                Lanjut ke Dokumen <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════ STEP 5: DOKUMEN ══════════════════════════════ --}}
<div id="step5" class="step-panel" style="display:none;">
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon" style="background:#ede7f6;">📁</div>
            <div>
                <div style="font-weight:800; font-size:16px;">Dokumen & Foto</div>
                <div style="font-size:12px; color:var(--text3); margin-top:2px;">Opsional · upload scan KK, foto rumah, dan dokumen PBB</div>
            </div>
        </div>
        <div style="display:grid; gap:16px;">
            <div class="f-group">
                <label class="f-label">📋 Scan Kartu Keluarga</label>
                <input type="file" name="fotoKK" accept="image/*,.pdf" class="f-input" style="font-size:12px;">
                <span class="f-hint">Format: JPG, PNG, PDF. Maks 2MB</span>
            </div>
            <div class="f-group">
                <label class="f-label">🏠 Foto Rumah</label>
                <input type="file" name="fotoRumah" accept="image/*" class="f-input" style="font-size:12px;">
                <span class="f-hint">Format: JPG, PNG. Maks 2MB</span>
            </div>
            <div class="f-group">
                <label class="f-label">📄 Dokumen PBB</label>
                <input type="file" name="dokumenPBB" accept="image/*,.pdf" class="f-input" style="font-size:12px;">
                <span class="f-hint">Format: JPG, PNG, PDF. Maks 2MB</span>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="goStep(4)"><i class="fas fa-arrow-left"></i> Kembali</button>
            <button type="submit" class="btn btn-primary" style="flex:2; padding:13px; font-size:14px;" id="btnSimpan" onclick="return validateFinal()">
                <i class="fas fa-save"></i> Simpan Data Keluarga
            </button>
        </div>
    </div>
</div>

</div>
</form>

<script>
let currentStep = 1;
let maxStepReached = 1;

// ─── Digit counter ───
function updateDigit(el, ctrid, max) {
    const len = el.value.length;
    const ctr = document.getElementById(ctrid);
    ctr.textContent = len + '/' + max;
    ctr.className = 'digit-counter' + (len === max ? ' full' : (len > max ? ' over' : ''));
    el.classList.toggle('valid', len === max);
    // Only show error if user has typed something but wrong length
    const errId = ctrid === 'ctrKK' ? 'errKK' : 'errNIK';
    const errEl = document.getElementById(errId);
    if (errEl) errEl.style.display = (len > 0 && len !== max) ? 'flex' : 'none';
}

// ─── Validate step 1 ───
function validateStep1() {
    const nama   = document.getElementById('fNama');
    const rt     = document.getElementById('fRT');
    const alamat = document.getElementById('fAlamat');
    const noKK   = document.getElementById('fNoKK');
    const nik    = document.getElementById('fNIK');
    let ok = true;

    // Nama
    if (!nama.value.trim()) {
        nama.classList.add('error'); document.getElementById('errNama').style.display='flex'; ok=false;
    } else { nama.classList.remove('error'); document.getElementById('errNama').style.display='none'; }

    // RT
    if (!rt.value) { rt.classList.add('error'); ok=false; } else { rt.classList.remove('error'); }

    // Alamat
    if (!alamat.value.trim()) { alamat.classList.add('error'); ok=false; } else { alamat.classList.remove('error'); }

    // noKK (optional but must be 16 if filled)
    if (noKK.value && noKK.value.length !== 16) {
        noKK.classList.add('error'); document.getElementById('errKK').style.display='flex'; ok=false;
    } else { noKK.classList.remove('error'); if(!noKK.value || noKK.value.length===16) document.getElementById('errKK').style.display='none'; }

    // NIK (optional but must be 16 if filled)
    if (nik.value && nik.value.length !== 16) {
        nik.classList.add('error'); document.getElementById('errNIK').style.display='flex'; ok=false;
    } else { nik.classList.remove('error'); if(!nik.value || nik.value.length===16) document.getElementById('errNIK').style.display='none'; }

    return ok;
}

function validateNama() {
    const el = document.getElementById('fNama');
    const err = document.getElementById('errNama');
    if (el.value.trim()) { el.classList.remove('error'); err.style.display='none'; }
}

function validateFinal() {
    if (!validateStep1()) { goStep(1); return false; }
    return true;
}

function goStepDirect(n) { if (n <= maxStepReached) goStep(n); }

function goStep(n) {
    if (n > currentStep && currentStep === 1 && !validateStep1()) return;

    document.getElementById('step' + currentStep).style.display = 'none';
    document.getElementById('step' + n).style.display = 'block';
    currentStep = n;
    if (n > maxStepReached) maxStepReached = n;

    for (let i = 1; i <= 5; i++) {
        const lbl = document.getElementById('stepLabel' + i);
        lbl.className = 'step-item ' + (i === n ? 'active' : (i < n ? 'done' : (i <= maxStepReached ? 'done' : 'locked')));
        lbl.style.cursor = (i <= maxStepReached) ? 'pointer' : 'default';
        if (i > 1) {
            const line = document.getElementById('stepLine' + i);
            if (line) line.className = 'step-line' + (i <= n ? ' done' : '');
        }
        // Step number: show checkmark for done steps
        const numEl = document.getElementById('stepNum' + i);
        if (numEl) numEl.textContent = (i < n) ? '✓' : i;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Anggota Dynamic Rows ───
let anggotaIdx = 0;
function addAnggotaRow() {
    const c = document.getElementById('anggotaContainer');
    document.getElementById('anggotaEmpty').style.display = 'none';
    const idx = anggotaIdx++;
    const div = document.createElement('div');
    div.id = 'anggotaRow' + idx;
    div.style.cssText = 'background:var(--biru-pale); border-radius:var(--radius-sm); padding:14px; margin-bottom:10px; position:relative;';
    div.innerHTML = `
        <button type="button" onclick="removeAnggota(${idx})" style="position:absolute; top:8px; right:8px; background:var(--merah); color:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
        <div style="font-weight:700; font-size:12px; color:var(--biru); margin-bottom:10px;"><i class="fas fa-user"></i> Anggota #${idx + 1}</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div><label class="f-label">Nama <span class="req">*</span></label><input type="text" name="anggota_nama[]" class="f-input" required placeholder="Nama lengkap"></div>
            <div><label class="f-label">NIK KTP</label><input type="text" name="anggota_nik[]" class="f-input" placeholder="16 digit" maxlength="16"></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
            <div><label class="f-label">Jenis Kelamin <span class="req">*</span></label>
                <select name="anggota_jk[]" class="f-input" required>
                    <option value="">- Pilih -</option><option value="L">Laki-laki</option><option value="P">Perempuan</option>
                </select>
            </div>
            <div><label class="f-label">Status dalam KK <span class="req">*</span></label>
                <select name="anggota_status[]" class="f-input" required>
                    <option value="">- Pilih -</option><option>Istri</option><option>Suami</option><option>Anak</option><option>Orang Tua</option><option>Mertua</option><option>Menantu</option><option>Cucu</option><option>Famili Lain</option>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
            <div><label class="f-label">Pekerjaan</label><input type="text" name="anggota_kerja[]" class="f-input" placeholder="Opsional"></div>
            <div><label class="f-label">Status BPJS</label>
                <select name="anggota_bpjs[]" class="f-input">
                    <option value="">Belum Terdaftar</option><option value="PBI">✅ PBI</option><option value="Mandiri">💳 Mandiri</option><option value="Tidak Aktif">❌ Tidak Aktif</option>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
            <div><label class="f-label">Tanggal Lahir</label><input type="date" name="anggota_tgllahir[]" class="f-input"></div>
            <div><label class="f-label">Pendidikan</label><select name="anggota_pendidikan[]" class="f-input"><option value="">-</option><option>Tidak/Belum Sekolah</option><option>SD</option><option>SMP</option><option>SMA/SMK</option><option>D1-D3</option><option>S1</option><option>S2/S3</option></select></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
            <div><label class="f-label">Status Kawin</label><select name="anggota_kawin[]" class="f-input"><option value="">-</option><option>Belum Kawin</option><option>Kawin</option><option>Cerai Hidup</option><option>Cerai Mati</option></select></div>
            <div><label class="f-label">Agama</label><select name="anggota_agama[]" class="f-input"><option value="">-</option><option>Islam</option><option>Kristen</option><option>Katolik</option><option>Hindu</option><option>Buddha</option><option>Konghucu</option></select></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px; padding-top:8px; border-top:1px dashed var(--abu2);">
            <div><label class="f-label">Status Kerja</label>
                <select name="anggota_statuskerja[]" class="f-input">
                    <option value="">- Otomatis dari pekerjaan -</option><option>Bekerja</option><option>Tidak Bekerja</option><option>Mencari Kerja</option><option>Sekolah</option><option>Mengurus Rumah Tangga</option><option>Pensiunan</option>
                </select>
            </div>
            <div><label class="f-label">Penghasilan / Bulan</label>
                <select name="anggota_penghasilan[]" class="f-input">
                    <option value="">- Bila bekerja -</option><option value="< 1 Juta">&lt; 1 Juta</option><option value="1 - 2.5 Juta">1 - 2.5 Juta</option><option value="2.5 - 5 Juta">2.5 - 5 Juta</option><option value="> 5 Juta">&gt; 5 Juta</option>
                </select>
            </div>
        </div>
    `;
    c.appendChild(div);
    updateJiwaCount();
}

function removeAnggota(idx) {
    const row = document.getElementById('anggotaRow' + idx);
    if (row) row.remove();
    const c = document.getElementById('anggotaContainer');
    if (c.children.length === 0) document.getElementById('anggotaEmpty').style.display = 'block';
    updateJiwaCount();
}

function updateJiwaCount() {
    const count = document.getElementById('anggotaContainer').children.length;
    const jiwaInput = document.querySelector('input[name="jumlahAnggota"]');
    if (jiwaInput) jiwaInput.value = 1 + count; // 1 kepala + anggota
}
</script>
@endsection
