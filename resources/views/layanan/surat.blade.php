@extends('layouts.app')
@section('title', 'Surat Menyurat')
@section('page-title', 'Surat Menyurat')
@section('page-subtitle', $isWarga ? 'Ajukan surat administrasi warga' : 'Administrasi surat keluar')

@section('content')

@if(session('success'))
<script>
(function(){
    const t = document.createElement('div');
    t.textContent = '✅ {{ session("success") }}';
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a5c38;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);max-width:90vw;';
    document.addEventListener('DOMContentLoaded',function(){ document.body.appendChild(t); setTimeout(()=>t.remove(),4000); });
})();
</script>
@endif

{{-- TOOLBAR --}}
<div class="toolbar" style="flex-wrap:wrap;">
    <div class="toolbar-left">
        <span style="font-size:13px; font-weight:600; color:var(--text2);">
            <i class="fas fa-envelope-open-text" style="color:var(--biru);"></i>
            {{ count($surats) }} surat
        </span>
    </div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="openAdd()">
            <i class="fas fa-plus"></i> {{ $isWarga ? 'Ajukan Surat Baru' : 'Buat Surat Baru' }}
        </button>
    </div>
</div>

{{-- SURAT LIST --}}
@if(count($surats) > 0)
<div class="surat-list">
    @foreach($surats as $s)
    @php
        $step = $s->approval_step ?? 'selesai';
        $stepInfo = match($step) {
            'diajukan'       => ['color'=>'var(--biru)',  'bg'=>'var(--biru-pale)',  'icon'=>'fa-paper-plane', 'label'=>'Diajukan'],
            'ttd_rt'         => ['color'=>'var(--emas)',  'bg'=>'var(--emas-muda)', 'icon'=>'fa-pen-nib',     'label'=>'TTD RT ✅'],
            'ttd_rw'         => ['color'=>'var(--biru)',  'bg'=>'var(--biru-pale)',  'icon'=>'fa-pen-fancy',   'label'=>'TTD RW ✅'],
            'cap_sekretaris' => ['color'=>'var(--emas)',  'bg'=>'var(--emas-muda)', 'icon'=>'fa-stamp',       'label'=>'Cap Sek'],
            'selesai'        => ['color'=>'var(--hijau)', 'bg'=>'var(--hijau-pale)', 'icon'=>'fa-check-circle','label'=>'Selesai'],
            'ditolak'        => ['color'=>'var(--merah)', 'bg'=>'var(--merah-pale)', 'icon'=>'fa-times-circle','label'=>'Ditolak'],
            default          => ['color'=>'var(--abu3)',  'bg'=>'var(--abu)',        'icon'=>'fa-question',    'label'=>$step],
        };
        $needsMyAction = false;
        if ($step === 'diajukan' && in_array($level, ['petugas_rt','superadmin','ketua_rw'])) $needsMyAction = true;
        if ($step === 'ttd_rt' && in_array($level, ['ketua_rw','superadmin'])) $needsMyAction = true;
        if ($step === 'ttd_rw' && in_array($level, ['superadmin'])) $needsMyAction = true;
    @endphp
    <div class="card" style="margin-bottom:10px; padding:0; overflow:hidden; {{ $needsMyAction ? 'border-left:4px solid var(--emas);' : '' }}">
        {{-- Header --}}
        <div style="display:flex; align-items:center; gap:10px; padding:14px 16px 10px; flex-wrap:wrap;">
            <span class="badge" style="background:var(--biru-pale); color:var(--biru); padding:3px 8px; border-radius:4px; font-size:10px; font-weight:700;">{{ $s->kodeSurat }}</span>
            <span style="font-size:12px; color:var(--text3);">{{ $s->tanggal ? date('d M Y', strtotime($s->tanggal)) : '-' }}</span>
            <span class="badge" style="background:{{ $stepInfo['bg'] }}; color:{{ $stepInfo['color'] }}; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:700;">
                <i class="fas {{ $stepInfo['icon'] }}"></i> {{ $stepInfo['label'] }}
            </span>
            @if($needsMyAction)
            <span class="badge" style="background:var(--emas-muda); color:var(--emas); padding:3px 8px; border-radius:4px; font-size:9px; font-weight:700; animation:pulse 2s infinite;">
                ⚡ PERLU TINDAKAN ANDA
            </span>
            @endif
        </div>

        {{-- Body --}}
        <div style="padding:0 16px 10px;">
            <div style="font-weight:700; font-size:14px;">{{ $s->pemohon }}</div>
            @if($s->keperluan)<div style="font-size:12px; color:var(--text3); margin-top:2px;">{{ $s->keperluan }}</div>@endif
            @if($s->nomorSurat)<div class="td-mono" style="font-size:11px; color:var(--text3); margin-top:2px;">{{ $s->nomorSurat }}</div>@endif
        </div>

        {{-- Progress Bar (for gradation surat) --}}
        @if($step !== 'selesai' && $step !== 'ditolak' && $s->user_id)
        <div style="padding:0 16px 12px;">
            <div style="display:flex; align-items:center; gap:4px; font-size:10px;">
                @php
                $steps = ['diajukan','ttd_rt','ttd_rw','selesai'];
                $currentIdx = array_search($step, $steps);
                if ($currentIdx === false) $currentIdx = -1;
                @endphp
                @foreach(['Diajukan','TTD RT','TTD RW','Selesai'] as $idx => $lbl)
                <div style="flex:1; text-align:center;">
                    <div style="height:4px; border-radius:2px; background:{{ $idx <= $currentIdx ? 'var(--hijau)' : 'var(--abu2)' }}; margin-bottom:3px;"></div>
                    <span style="color:{{ $idx <= $currentIdx ? 'var(--hijau)' : 'var(--text3)' }}; font-weight:{{ $idx == $currentIdx ? '700' : '400' }};">{{ $lbl }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Rejection info --}}
        @if($step === 'ditolak')
        <div style="padding:8px 16px; background:var(--merah-pale); font-size:12px; color:var(--merah);">
            <i class="fas fa-times-circle"></i> <strong>Ditolak oleh {{ $s->rejected_by }}</strong>: {{ $s->rejected_reason }}
        </div>
        @endif

        {{-- Signatures --}}
        @if($s->rt_signed_by || $s->rw_signed_by || $s->sek_signed_by)
        <div style="padding:6px 16px 10px; font-size:11px; color:var(--text3); display:flex; gap:12px; flex-wrap:wrap;">
            @if($s->rt_signed_by)<span>✅ RT: <strong>{{ $s->rt_signed_by }}</strong> {{ $s->rt_signed_at ? '(' . date('d/m H:i', strtotime($s->rt_signed_at)) . ')' : '' }}</span>@endif
            @if($s->rw_signed_by)<span>✅ RW: <strong>{{ $s->rw_signed_by }}</strong> {{ $s->rw_signed_at ? '(' . date('d/m H:i', strtotime($s->rw_signed_at)) . ')' : '' }}</span>@endif
            @if($s->sek_signed_by)<span>✅ Cap: <strong>{{ $s->sek_signed_by }}</strong> {{ $s->sek_signed_at ? '(' . date('d/m H:i', strtotime($s->sek_signed_at)) . ')' : '' }}</span>@endif
        </div>
        @endif

        {{-- Actions --}}
        <div style="display:flex; gap:6px; padding:10px 16px; border-top:1px solid var(--abu2); flex-wrap:wrap;">
            @if($needsMyAction)
            {{-- Approve --}}
            <form method="POST" action="{{ route('surat.approve', $s->id) }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" style="font-size:11px; background:var(--hijau); border-color:var(--hijau);">
                    <i class="fas fa-check"></i> Tanda Tangan
                </button>
            </form>
            {{-- Reject --}}
            <button class="btn btn-outline btn-sm" style="font-size:11px; color:var(--merah); border-color:var(--merah);"
                onclick="openReject({{ $s->id }}, '{{ addslashes($s->nomorSurat) }}')">
                <i class="fas fa-times"></i> Tolak
            </button>
            @endif

            @if(!$isWarga)
            <button class="btn btn-outline btn-sm" style="font-size:11px;"
                onclick="openEdit({{ $s->id }}, '{{ addslashes($s->pemohon) }}', '{{ addslashes($s->keperluan) }}', '{{ $s->kodeSurat }}')">
                <i class="fas fa-edit"></i> Edit
            </button>
            @endif

            @if($step === 'selesai')
            <a href="{{ route('surat.cetak', $s->id) }}" class="btn btn-outline btn-sm" style="font-size:11px;" target="_blank">
                <i class="fas fa-print"></i> Cetak
            </a>
            @endif

            @if(!$isWarga)
            <form method="POST" action="{{ route('surat.destroy', $s->id) }}" style="margin:0;"
                  onsubmit="return confirm('Hapus surat {{ $s->nomorSurat }}?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:11px; color:var(--merah); border-color:var(--merah-pale);">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@else
<div class="card" style="text-align:center; padding:40px 20px;">
    <img src="{{ asset('empty-state.png') }}" alt="" aria-hidden="true" class="blank-state__art" width="176" height="176">
    <h3 style="color:var(--text2); margin-bottom:8px;">{{ $isWarga ? 'Belum Ada Pengajuan Surat' : 'Belum Ada Surat' }}</h3>
    <p style="color:var(--text3); font-size:13px; max-width:400px; margin:0 auto 20px;">
        {{ $isWarga ? 'Ajukan surat administrasi seperti SKD, SKTM, SKP, dan lainnya. Surat akan diproses oleh RT → RW → Sekretaris.' : 'Buat surat administrasi warga.' }}
    </p>
    <button class="btn btn-primary btn-sm" onclick="openAdd()"><i class="fas fa-plus"></i> {{ $isWarga ? 'Ajukan Surat Pertama' : 'Buat Surat' }}</button>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- MODAL: BUAT SURAT BARU --}}
{{-- ═══════════════════════════════════════ --}}
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="card" style="width:100%; max-width:520px; margin:0; max-height:90vh; overflow-y:auto;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-file-alt" style="color:var(--biru);"></i> {{ $isWarga ? 'Ajukan Surat' : 'Buat Surat Baru' }}</div>
            <button type="button" onclick="closeAdd()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>

        @if($isWarga)
        <div style="margin-top:12px; background:var(--biru-pale); border-left:4px solid var(--biru); padding:10px 14px; border-radius:6px; font-size:12px; color:var(--biru); line-height:1.6;">
            ℹ️ Surat akan diproses bertahap: <strong>Anda ajukan</strong> → <strong>RT tanda tangan</strong> → <strong>RW tanda tangan</strong> → <strong>Sekretaris cap</strong> → Selesai
        </div>
        @endif

        <form method="POST" action="{{ route('surat.store') }}" style="margin-top:16px;">
            @csrf

            {{-- Jenis Surat --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Jenis Surat *</label>
                <select name="kodeSurat" id="jenisSurat" required onchange="onJenisChange()"
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="">- Pilih Jenis Surat -</option>
                    <optgroup label="📋 Kependudukan">
                        <option value="SKD">SKD · Surat Keterangan Domisili</option>
                        <option value="SKP">SKP · Surat Keterangan Pindah</option>
                        <option value="SKK">SKK · Surat Keterangan Kematian</option>
                        <option value="SKL">SKL · Surat Keterangan Kelahiran</option>
                        <option value="SKN">SKN · Surat Pengantar Nikah</option>
                    </optgroup>
                    <optgroup label="💼 Sosial & Ekonomi">
                        <option value="SKTM">SKTM · Surat Keterangan Tidak Mampu</option>
                        <option value="SKU">SKU · Surat Keterangan Usaha</option>
                        <option value="SPB">SPB · Surat Pengantar Bantuan (KIP/PKH/BPNT)</option>
                    </optgroup>
                    <optgroup label="🛡️ Administrasi & Keamanan">
                        <option value="SKCK">SKCK · Surat Pengantar SKCK</option>
                        <option value="SKBB">SKBB · Surat Keterangan Berkelakuan Baik</option>
                        <option value="SKI">SKI · Surat Keterangan Izin Keramaian</option>
                        <option value="SKKB">SKKB · Surat Keterangan Kehilangan Barang</option>
                    </optgroup>
                    <option value="LAIN">📝 Lainnya</option>
                </select>
            </div>

            {{-- Petunjuk --}}
            <div id="suratHint" style="display:none; background:var(--biru-pale); border-left:4px solid var(--biru); border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:12px; color:var(--biru); line-height:1.6;"></div>

            {{-- Pemohon --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Pemohon *</label>
                @if($isWarga)
                <input type="hidden" name="pemohon" value="{{ $user->namaLengkap ?? $user->username }}">
                <div style="padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:var(--abu); color:var(--text2);">
                    {{ $user->namaLengkap ?? $user->username }}
                </div>
                @else
                <select name="pemohon" required
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="">- Pilih Warga Terdaftar -</option>
                    @foreach($wargaList as $w)
                    <option value="{{ $w->kepala_keluarga ?? $w->nama }}">
                        {{ $w->kepala_keluarga ?? $w->nama }} · RT {{ $w->rt }}
                    </option>
                    @endforeach
                    <option value="__luar__">⚠️ Warga Luar / Tidak Terdaftar</option>
                </select>
                @endif
            </div>

            @if(!$isWarga)
            {{-- Nama manual untuk warga luar --}}
            <div id="namaLuarBox" style="display:none; margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Pemohon (Manual) *</label>
                <input type="text" name="pemohon_manual" id="namaLuarInput" placeholder="Tulis nama lengkap..."
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
            </div>
            @endif

            {{-- Keperluan --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Keperluan / Keterangan Tambahan</label>
                <input type="text" name="keperluan" id="keperluanInput" placeholder="Contoh: Untuk syarat KIP, Pindah ke Jakarta..."
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
            </div>

            <div style="display:flex; gap:10px; margin-top:4px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeAdd()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-file-alt"></i> {{ $isWarga ? 'Ajukan Surat' : 'Buat & Cetak' }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- MODAL: EDIT SURAT --}}
{{-- ═══════════════════════════════════════ --}}
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="card" style="width:100%; max-width:480px; margin:0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-edit"></i> Edit Surat</div>
            <button type="button" onclick="closeEdit()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>
        <form method="POST" id="editForm" style="margin-top:16px;">
            @csrf @method('PUT')
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Jenis Surat</label>
                <select name="kodeSurat" id="editKode"
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="SKD">SKD · Surat Keterangan Domisili</option>
                    <option value="SKTM">SKTM · Surat Keterangan Tidak Mampu</option>
                    <option value="SKP">SKP · Surat Keterangan Pindah</option>
                    <option value="SKU">SKU · Surat Keterangan Usaha</option>
                    <option value="SKCK">SKCK · Surat Pengantar SKCK</option>
                    <option value="SKK">SKK · Surat Keterangan Kematian</option>
                    <option value="SKL">SKL · Surat Keterangan Kelahiran</option>
                    <option value="SKN">SKN · Surat Pengantar Nikah</option>
                    <option value="SKBB">SKBB · Surat Keterangan Berkelakuan Baik</option>
                    <option value="SPB">SPB · Surat Pengantar Bantuan</option>
                    <option value="SKI">SKI · Surat Izin Keramaian</option>
                    <option value="SKKB">SKKB · Surat Keterangan Kehilangan Barang</option>
                    <option value="LAIN">Lainnya</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Pemohon *</label>
                <input type="text" name="pemohon" id="editPemohon" required
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Keperluan</label>
                <input type="text" name="keperluan" id="editKeperluan"
                    style="width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeEdit()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- MODAL: TOLAK SURAT --}}
{{-- ═══════════════════════════════════════ --}}
<div id="rejectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="card" style="width:100%; max-width:420px; margin:0;">
        <div class="card-header">
            <div class="card-title" style="color:var(--merah);"><i class="fas fa-times-circle"></i> Tolak Surat</div>
            <button type="button" onclick="closeReject()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>
        <div id="rejectInfo" style="font-size:13px; color:var(--text2); margin:12px 0;"></div>
        <form method="POST" id="rejectForm" style="margin-top:8px;">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Alasan Penolakan *</label>
                <textarea name="alasan" required rows="3" placeholder="Tulis alasan penolakan surat..."
                    style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeReject()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1; background:var(--merah); border-color:var(--merah);"><i class="fas fa-times"></i> Tolak Surat</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity:1; }
    50% { opacity:0.5; }
}
</style>

<script>
const HINTS = {
    SKD:  '📋 <strong>SKD</strong>: Surat untuk membuktikan seseorang berdomisili di wilayah ini.',
    SKTM: '💙 <strong>SKTM</strong>: Surat untuk keperluan sosial (sekolah gratis, PKH, dll).',
    SKP:  '🚚 <strong>SKP</strong>: Surat bagi warga yang akan pindah ke daerah lain.',
    SKU:  '🏪 <strong>SKU</strong>: Surat untuk warga yang memiliki usaha di wilayah RW ini.',
    SKCK: '🛡️ <strong>SKCK</strong>: Surat pengantar ke Polsek untuk pembuatan SKCK.',
    SKK:  '🕊️ <strong>SKK</strong>: Surat kematian warga.',
    SKL:  '👶 <strong>SKL</strong>: Surat kelahiran bayi warga.',
    SKN:  '💍 <strong>SKN</strong>: Surat pengantar nikah ke KUA.',
    SKBB: '✅ <strong>SKBB</strong>: Surat keterangan kelakuan baik.',
    SPB:  '🏦 <strong>SPB</strong>: Surat pengantar untuk KIP, PKH, BPNT.',
    SKI:  '🎉 <strong>SKI</strong>: Surat izin mengadakan keramaian.',
    SKKB: '🔍 <strong>SKKB</strong>: Surat keterangan kehilangan barang.',
    LAIN: '📝 Surat kustom / lainnya.',
};

function onJenisChange() {
    const v = document.getElementById('jenisSurat').value;
    const hint = document.getElementById('suratHint');
    if (v && HINTS[v]) { hint.innerHTML = 'ℹ️ ' + HINTS[v]; hint.style.display = 'block'; }
    else { hint.style.display = 'none'; }
    const kep = document.getElementById('keperluanInput');
    const pre = { SKCK:'Keperluan melamar kerja', SKN:'Menikah dengan ...', SKI:'Hajatan / Acara keluarga',
        SKTM:'Beasiswa / Keringanan', SKP:'Pindah ke ...', SKK:'Duka warga', SKL:'Kelahiran bayi' };
    if (!kep.value && pre[v]) kep.value = pre[v];
}

@if(!$isWarga)
// Handle warga luar
const pemohonSelect = document.querySelector('[name="pemohon"]');
if (pemohonSelect && pemohonSelect.tagName === 'SELECT') {
    pemohonSelect.addEventListener('change', function(){
        const luarBox = document.getElementById('namaLuarBox');
        const luarInput = document.getElementById('namaLuarInput');
        if (this.value === '__luar__') { luarBox.style.display = 'block'; luarInput.required = true; }
        else { luarBox.style.display = 'none'; luarInput.required = false; }
    });
}

// Override form submission for manual name
const addForm = document.querySelector('#addModal form');
if (addForm) {
    addForm.addEventListener('submit', function(e) {
        const pemohon = this.querySelector('[name="pemohon"]');
        if (pemohon && pemohon.value === '__luar__') {
            const manual = document.getElementById('namaLuarInput').value.trim();
            if (!manual) { e.preventDefault(); alert('Isi nama pemohon manual terlebih dahulu.'); return; }
            pemohon.value = manual;
        }
    });
}
@endif

function openAdd()  { document.getElementById('addModal').style.display = 'flex'; }
function closeAdd() { document.getElementById('addModal').style.display = 'none'; }
function openEdit(id, pemohon, keperluan, kode) {
    document.getElementById('editForm').action = '/surat/' + id;
    document.getElementById('editPemohon').value = pemohon;
    document.getElementById('editKeperluan').value = keperluan;
    document.getElementById('editKode').value = kode;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }

function openReject(id, nomor) {
    document.getElementById('rejectForm').action = '/surat/' + id + '/reject';
    document.getElementById('rejectInfo').innerHTML = 'Menolak surat <strong>' + nomor + '</strong>';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeReject() { document.getElementById('rejectModal').style.display = 'none'; }

document.getElementById('addModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeAdd(); });
document.getElementById('editModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeEdit(); });
document.getElementById('rejectModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeReject(); });
</script>
@endsection
