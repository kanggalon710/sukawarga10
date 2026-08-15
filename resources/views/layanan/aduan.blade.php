@extends('layouts.app')
@section('title', 'Pengaduan Warga')
@section('page-title', 'Pengaduan Warga')
@section('page-subtitle', $isWarga ? 'Sampaikan keluhan atau aduan Anda' : 'Dasbor pengawasan & tindak lanjut aduan')

@section('content')

@if(session('success'))
<script>
(function(){
    const t = document.createElement('div');
    t.textContent = '✅ {{ session("success") }}';
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a5c38;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);';
    document.addEventListener('DOMContentLoaded',function(){ document.body.appendChild(t); setTimeout(()=>t.remove(),4000); });
})();
</script>
@endif

@php
$totalMasuk        = $aduans->where('status','masuk')->count();
$totalProses       = $aduans->where('status','ditindaklanjuti')->count();
$totalSelesai      = $aduans->where('status','selesai')->count();
$totalTinggi       = $aduans->where('prioritas','tinggi')->count();
@endphp

{{-- STATS (pengurus only) --}}
@if(!$isWarga)
<div class="stats-row" style="grid-template-columns:repeat(4,1fr); margin-bottom:20px;">
    <div class="stat-card" style="border-left:4px solid var(--biru);">
        <div class="stat-accent" style="background:var(--biru);"></div>
        <div class="stat-icon-box" style="background:var(--biru-pale); color:var(--biru);"><i class="fas fa-inbox"></i></div>
        <div class="stat-label">MASUK</div>
        <div class="stat-value">{{ $totalMasuk }}</div>
        <div class="stat-sub">Belum ditangani</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-accent"></div>
        <div class="stat-icon-box" style="background:var(--emas-muda); color:var(--emas);"><i class="fas fa-spinner"></i></div>
        <div class="stat-label">DIPROSES</div>
        <div class="stat-value">{{ $totalProses }}</div>
        <div class="stat-sub">Sedang ditangani</div>
    </div>
    <div class="stat-card green">
        <div class="stat-accent"></div>
        <div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-check-circle"></i></div>
        <div class="stat-label">SELESAI</div>
        <div class="stat-value">{{ $totalSelesai }}</div>
        <div class="stat-sub">Berhasil diselesaikan</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--merah);">
        <div class="stat-accent" style="background:var(--merah);"></div>
        <div class="stat-icon-box" style="background:var(--merah-pale); color:var(--merah);"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-label">PRIORITAS TINGGI</div>
        <div class="stat-value">{{ $totalTinggi }}</div>
        <div class="stat-sub">Perlu segera ditangani</div>
    </div>
</div>
@endif

{{-- TOOLBAR --}}
<div class="toolbar" style="margin-bottom:16px; flex-wrap:wrap;">
    @if(!$isWarga)
    <div class="toolbar-left" style="flex-wrap:wrap; gap:6px;">
        <a href="{{ route('aduan.index') }}" class="btn {{ $status=='' ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas fa-list"></i> Semua ({{ $aduans->count() }})
        </a>
        <a href="{{ route('aduan.index', ['status'=>'masuk']) }}" class="btn {{ $status=='masuk' ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas fa-inbox"></i> Masuk ({{ $totalMasuk }})
        </a>
        <a href="{{ route('aduan.index', ['status'=>'ditindaklanjuti']) }}" class="btn {{ $status=='ditindaklanjuti' ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas fa-spinner"></i> Diproses ({{ $totalProses }})
        </a>
        <a href="{{ route('aduan.index', ['status'=>'selesai']) }}" class="btn {{ $status=='selesai' ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas fa-check-circle"></i> Selesai ({{ $totalSelesai }})
        </a>
    </div>
    @else
    <div class="toolbar-left">
        <span style="font-size:13px; font-weight:600; color:var(--text2);">{{ $aduans->count() }} aduan Anda</span>
    </div>
    @endif
    <div class="toolbar-right">
        @if($isWarga)
        <button class="btn btn-primary btn-sm" onclick="openAdd()">
            <i class="fas fa-plus"></i> Buat Aduan
        </button>
        @endif
    </div>
</div>

{{-- ADUAN CARDS --}}
@forelse($aduans as $a)
@php
$statusColor = match($a->status) {
    'masuk'             => ['bg'=>'var(--biru-pale)',  'color'=>'var(--biru)',  'label'=>'Masuk',    'icon'=>'fa-inbox'],
    'ditindaklanjuti'   => ['bg'=>'var(--emas-muda)', 'color'=>'var(--emas)',  'label'=>'Diproses', 'icon'=>'fa-spinner'],
    'selesai'           => ['bg'=>'var(--hijau-pale)', 'color'=>'var(--hijau)','label'=>'Selesai',  'icon'=>'fa-check-circle'],
    default             => ['bg'=>'var(--merah-pale)', 'color'=>'var(--merah)','label'=>'Ditolak',  'icon'=>'fa-times-circle'],
};
$prioColor = match($a->prioritas) {
    'tinggi' => ['bg'=>'var(--merah-pale)', 'color'=>'var(--merah)'],
    'sedang' => ['bg'=>'var(--emas-muda)',  'color'=>'var(--emas)'],
    default  => ['bg'=>'var(--abu)',        'color'=>'var(--text3)'],
};
$katIcon = match($a->kategori) {
    'Keamanan'   => '🛡️',
    'Kebersihan' => '🗑️',
    'Fasilitas'  => '🏗️',
    'Sosial'     => '🤝',
    default      => '📋',
};
@endphp
<div class="card" style="margin-bottom:14px; padding:0; overflow:hidden; border-left:4px solid {{ $a->prioritas=='tinggi' ? 'var(--merah)' : ($a->status=='selesai' ? 'var(--hijau)' : 'var(--biru)') }};">
    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:12px; padding:14px 18px 12px; border-bottom:1px solid var(--abu2);">
        <div style="font-size:26px; flex-shrink:0;">{{ $katIcon }}</div>
        <div style="flex:1; min-width:0;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-weight:700; font-size:14px;">{{ $a->pelapor }}</span>
                <span style="font-size:12px; color:var(--text3);">RT {{ $a->rt ?? '-' }}</span>
                <span style="font-size:11px; padding:3px 8px; border-radius:6px; background:{{ $prioColor['bg'] }}; color:{{ $prioColor['color'] }}; font-weight:600;">
                    {{ ucfirst($a->prioritas ?? 'sedang') }}
                </span>
                <span style="font-size:11px; padding:3px 8px; border-radius:6px; background:{{ $statusColor['bg'] }}; color:{{ $statusColor['color'] }}; font-weight:600;">
                    <i class="fas {{ $statusColor['icon'] }}"></i> {{ $statusColor['label'] }}
                </span>
            </div>
            <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                📅 {{ date('d M Y', strtotime($a->tanggal)) }} &nbsp;|&nbsp; {{ $a->aduan_id }}
                @if($a->kategori)&nbsp;|&nbsp; Kategori: <strong>{{ $a->kategori }}</strong>@endif
                @if($a->recordedBy)&nbsp;|&nbsp; Dicatat oleh: {{ $a->recordedBy }}@endif
            </div>
        </div>
        {{-- Quick actions --}}
        <div style="display:flex; gap:6px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end;">
            @if($a->status === 'masuk')
            <form method="POST" action="{{ route('aduan.updateStatus', $a->id) }}" style="margin:0;">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="ditindaklanjuti">
                <input type="hidden" name="catatanTL" value="Aduan diterima dan sedang ditangani oleh RT/RW.">
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:11px; color:var(--emas); border-color:var(--emas);" title="Tandai Diproses">
                    <i class="fas fa-play"></i> Proses
                </button>
            </form>
            @endif
            @if($a->status !== 'selesai')
            <button class="btn btn-outline btn-sm" style="font-size:11px; color:var(--hijau); border-color:var(--hijau);"
                onclick="openTL({{ $a->id }}, '{{ addslashes($a->pelapor) }}', 'selesai')" title="Selesaikan">
                <i class="fas fa-check"></i> Selesai
            </button>
            @endif
            <button class="btn btn-outline btn-sm" style="font-size:11px;" onclick="toggleDetail('detail-{{ $a->id }}')">
                <i class="fas fa-chevron-down" id="chev-{{ $a->id }}"></i>
            </button>
        </div>
    </div>

    {{-- Isi aduan --}}
    <div style="padding:12px 18px; font-size:13px; color:var(--text2); line-height:1.6; background:var(--abu);">
        {{ $a->isi }}
    </div>

    {{-- Catatan tindak lanjut (if any) --}}
    @if($a->catatanTL)
    <div style="padding:10px 18px; font-size:12px; color:var(--hijau); background:var(--hijau-pale); border-top:1px solid var(--abu2);">
        <i class="fas fa-clipboard-check"></i> <strong>Catatan Tindak Lanjut:</strong> {{ $a->catatanTL }}
    </div>
    @endif

    {{-- Expandable detail + action --}}
    <div id="detail-{{ $a->id }}" style="display:none; border-top:1px solid var(--abu2);">
        <form method="POST" action="{{ route('aduan.updateStatus', $a->id) }}" style="padding:14px 18px;">
            @csrf @method('PUT')
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:var(--text3); margin-bottom:5px;">UBAH STATUS</label>
                    <select name="status" style="width:100%; padding:9px 10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px; background:white;">
                        <option value="masuk"            {{ $a->status=='masuk'           ? 'selected':'' }}>📥 Masuk</option>
                        <option value="ditindaklanjuti"  {{ $a->status=='ditindaklanjuti' ? 'selected':'' }}>⚙️ Sedang Diproses</option>
                        <option value="selesai"          {{ $a->status=='selesai'         ? 'selected':'' }}>✅ Selesai</option>
                        <option value="ditolak"          {{ $a->status=='ditolak'         ? 'selected':'' }}>❌ Ditolak</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:var(--text3); margin-bottom:5px;">CATATAN TINDAK LANJUT</label>
                    <input type="text" name="catatanTL" value="{{ $a->catatanTL }}"
                        placeholder="Isi keterangan penanganan..."
                        style="width:100%; padding:9px 10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Perubahan</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleDetail('detail-{{ $a->id }}')">Tutup</button>
            </div>
        </form>
    </div>
</div>
@empty
<div class="card" style="text-align:center; padding:60px 40px;">
    <i class="fas fa-clipboard-check" style="font-size:52px; color:var(--abu3); margin-bottom:16px; display:block;"></i>
    <h3 style="color:var(--text2); margin-bottom:8px;">
        {{ $status ? 'Tidak ada aduan berstatus "' . $status . '"' : 'Belum Ada Pengaduan' }}
    </h3>
    <p style="color:var(--text3); font-size:13px; max-width:400px; margin:0 auto 20px;">
        Catat keluhan warga terkait keamanan, kebersihan, fasilitas, atau masalah sosial. Setiap aduan dilacak hingga selesai ditangani.
    </p>
    <button class="btn btn-primary btn-sm" onclick="openAdd()"><i class="fas fa-plus"></i> Catat Aduan Pertama</button>
</div>
@endforelse

{{-- ═══════════════════════════════════════ --}}
{{-- MODAL: CATAT ADUAN --}}
{{-- ═══════════════════════════════════════ --}}
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="card" style="width:100%; max-width:520px; margin:0; max-height:90vh; overflow-y:auto;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle" style="color:var(--biru);"></i> Catat Aduan Warga</div>
            <button type="button" onclick="closeAdd()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>
        <form method="POST" action="{{ route('aduan.store') }}" style="margin-top:16px;">
            @csrf
            @if($isWarga)
            {{-- Warga: auto-filled pelapor --}}
            <input type="hidden" name="pelapor" value="{{ $user->namaLengkap ?? $user->username }}">
            <input type="hidden" name="rt" value="{{ $user->rt ?? '' }}">
            <div style="margin-bottom:14px; padding:10px 12px; background:var(--abu); border-radius:var(--radius-sm); font-size:13px;">
                <strong>{{ $user->namaLengkap ?? $user->username }}</strong> — RT {{ $user->rt ?? '-' }}
            </div>
            @else
            {{-- Pengurus: search pelapor --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div style="position:relative;">
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Pelapor *</label>
                    <input type="text" id="aduanPelapor" autocomplete="off" required placeholder="🔍 Ketik nama warga..."
                        style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;"
                        oninput="filterAduanWarga(this.value)">
                    <input type="hidden" name="pelapor" id="aduanPelaporVal">
                    <div id="aduanWargaDD"
                        style="display:none; position:absolute; top:100%; left:0; right:0; z-index:10000;
                               background:white; border:1.5px solid var(--biru); border-radius:var(--radius-sm);
                               max-height:180px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,0.12);">
                    </div>
                    <div id="aduanWargaData" style="display:none;">
                        @foreach($keluargas as $k)
                        <div data-nama="{{ $k->nama }}" data-rt="{{ $k->rt }}"></div>
                        @endforeach
                    </div>
                    <div style="font-size:10px; color:var(--text3); margin-top:3px;">Pilih dari daftar, atau ketik langsung</div>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">RT</label>
                    <select name="rt" id="aduanRT" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option value="">— RT —</option>
                        @for($i=1;$i<=6;$i++)<option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}">RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor
                    </select>
                </div>
            </div>
            @endif
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Kategori</label>
                    <select name="kategori" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option>🛡️ Keamanan</option>
                        <option>🗑️ Kebersihan</option>
                        <option>🏗️ Fasilitas</option>
                        <option>🤝 Sosial</option>
                        <option>📋 Lainnya</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Prioritas</label>
                    <select name="prioritas" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option value="rendah">Rendah</option>
                        <option value="sedang" selected>Sedang</option>
                        <option value="tinggi">🔴 Tinggi / Mendesak</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Isi Aduan / Keluhan *</label>
                <textarea name="isi" rows="4" required placeholder="Tulis detail aduan atau keluhan warga di sini..."
                    style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeAdd()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Catat Aduan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal quick-selesai --}}
<div id="tlModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="card" style="width:100%; max-width:420px; margin:0;">
        <div class="card-header">
            <div class="card-title" id="tlTitle"><i class="fas fa-check-circle" style="color:var(--hijau);"></i> Selesaikan Aduan</div>
            <button onclick="closeTL()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>
        <form id="tlForm" method="POST" style="margin-top:16px;">
            @csrf @method('PUT')
            <input type="hidden" name="status" id="tlStatus">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Catatan Penyelesaian</label>
                <textarea name="catatanTL" id="tlCatatan" rows="3" placeholder="Apa yang sudah dilakukan untuk menyelesaikan aduan ini..."
                    style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeTL()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1; background:var(--hijau); border-color:var(--hijau);">
                    <i class="fas fa-check"></i> Tandai Selesai
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdd()  { document.getElementById('addModal').style.display = 'flex'; }
function closeAdd() { document.getElementById('addModal').style.display = 'none'; }
function closeTL()  { document.getElementById('tlModal').style.display = 'none'; }

function openTL(id, nama, newStatus) {
    document.getElementById('tlForm').action = '/aduan/' + id + '/status';
    document.getElementById('tlStatus').value = newStatus;
    document.getElementById('tlCatatan').value = '';
    document.getElementById('tlTitle').innerHTML = newStatus === 'selesai'
        ? '<i class="fas fa-check-circle" style="color:var(--hijau);"></i> Selesaikan Aduan — ' + nama
        : '<i class="fas fa-times" style="color:var(--merah);"></i> Tolak Aduan — ' + nama;
    document.getElementById('tlModal').style.display = 'flex';
}

function toggleDetail(id) {
    const el  = document.getElementById(id);
    const num = id.split('-').pop();
    const chev = document.getElementById('chev-' + num);
    const isOpen = el.style.display !== 'none';
    el.style.display = isOpen ? 'none' : 'block';
    if (chev) chev.className = isOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
}

document.getElementById('addModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeAdd(); });
document.getElementById('tlModal').addEventListener('click',  e => { if(e.target===e.currentTarget) closeTL(); });

// ─── Aduan Pelapor Autocomplete ───
const aduanWargaData = [...document.querySelectorAll('#aduanWargaData [data-nama]')].map(el => ({
    nama: el.dataset.nama, rt: el.dataset.rt
}));

function filterAduanWarga(q) {
    const dd = document.getElementById('aduanWargaDD');
    if (!q.trim()) { dd.style.display = 'none'; return; }
    const ql = q.toLowerCase();
    const hits = aduanWargaData.filter(w => w.nama.toLowerCase().includes(ql) || w.rt.includes(q));
    if (!hits.length) {
        dd.innerHTML = '<div style="padding:10px;font-size:12px;color:var(--text3);">Tidak ditemukan — nama akan dicatat langsung</div>';
        dd.style.display = 'block';
        return;
    }
    dd.innerHTML = hits.slice(0, 30).map(w =>
        `<div onclick="selectAduanWarga('${w.nama.replace(/'/g,"\\'")}','${w.rt}')"
             style="padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--abu2);"
             onmouseover="this.style.background='var(--abu)'" onmouseout="this.style.background='white'">
            <span style="font-weight:600;">${w.nama}</span> <span style="font-size:11px;color:var(--text3);">— RT ${w.rt}</span>
        </div>`
    ).join('');
    dd.style.display = 'block';
}

function selectAduanWarga(nama, rt) {
    document.getElementById('aduanPelapor').value = nama;
    document.getElementById('aduanPelaporVal').value = nama;
    document.getElementById('aduanWargaDD').style.display = 'none';
    // Auto-fill RT
    const rtSel = document.getElementById('aduanRT');
    if (rtSel) {
        for (let opt of rtSel.options) {
            if (opt.value === rt) { opt.selected = true; break; }
        }
    }
}

// Close dropdown on outside click
document.addEventListener('click', e => {
    const dd = document.getElementById('aduanWargaDD');
    if (dd && !document.getElementById('aduanPelapor').contains(e.target)) dd.style.display = 'none';
});

// On form submit: ensure hidden pelapor input has value from search field
const aduanForm = document.querySelector('#addModal form');
if (aduanForm) {
    aduanForm.addEventListener('submit', function(e) {
        const search = document.getElementById('aduanPelapor');
        const hidden = document.getElementById('aduanPelaporVal');
        if (!hidden.value && search.value.trim()) hidden.value = search.value.trim();
        if (!hidden.value) { e.preventDefault(); search.focus(); return; }
    });
}
</script>
@endsection
