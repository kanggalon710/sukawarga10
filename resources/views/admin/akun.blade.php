@extends('layouts.app')
@section('title', 'Manajemen Akun')
@section('page-title', 'Manajemen Akun')
@section('page-subtitle', 'Kelola pengguna sistem — pengurus & warga')

@section('content')
@php
    $levelColors = ['superadmin'=>'merah','ketua_rw'=>'hijau','bendahara'=>'biru','petugas_rt'=>'emas','warga'=>'abu3'];
    $levelIcons = ['superadmin'=>'fa-shield-alt','ketua_rw'=>'fa-user-tie','bendahara'=>'fa-calculator','petugas_rt'=>'fa-id-badge','warga'=>'fa-user'];
    $pengurus = $users->whereIn('level', ['superadmin','ketua_rw','bendahara','petugas_rt']);
    $wargaUsers = $users->where('level', 'warga');
@endphp

{{-- STATS --}}
<div class="stats-row" style="grid-template-columns:repeat(4,1fr); margin-bottom:16px;">
    <div class="stat-card" style="border-left:4px solid var(--biru);">
        <div class="stat-accent" style="background:var(--biru);"></div>
        <div class="stat-icon-box" style="background:var(--biru-pale); color:var(--biru);"><i class="fas fa-users"></i></div>
        <div class="stat-label">TOTAL AKUN</div>
        <div class="stat-value">{{ $users->count() }}</div>
        <div class="stat-sub">Semua pengguna</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--emas);">
        <div class="stat-accent" style="background:var(--emas);"></div>
        <div class="stat-icon-box" style="background:var(--emas-muda); color:var(--emas);"><i class="fas fa-user-tie"></i></div>
        <div class="stat-label">PENGURUS</div>
        <div class="stat-value">{{ $pengurus->count() }}</div>
        <div class="stat-sub">RT/RW/Admin</div>
    </div>
    <div class="stat-card green">
        <div class="stat-accent"></div>
        <div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-user"></i></div>
        <div class="stat-label">WARGA</div>
        <div class="stat-value">{{ $wargaUsers->count() }}</div>
        <div class="stat-sub">Akun warga terdaftar</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--hijau);">
        <div class="stat-accent" style="background:var(--hijau);"></div>
        <div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-check-circle"></i></div>
        <div class="stat-label">AKTIF</div>
        <div class="stat-value">{{ $users->where('status','aktif')->count() }}</div>
        <div class="stat-sub">Terverifikasi</div>
    </div>
</div>

{{-- TAB TOOLBAR --}}
<div class="toolbar" style="margin-bottom:16px; flex-wrap:wrap;">
    <div class="toolbar-left" style="gap:6px;">
        <button class="btn btn-primary btn-sm" id="tabPengurus" onclick="showAkunTab('pengurus')">
            <i class="fas fa-user-tie"></i> Pengurus ({{ $pengurus->count() }})
        </button>
        <button class="btn btn-outline btn-sm" id="tabWarga" onclick="showAkunTab('warga')">
            <i class="fas fa-users"></i> Warga ({{ $wargaUsers->count() }})
        </button>
        <button class="btn btn-outline btn-sm" id="tabPermission" onclick="showAkunTab('permission')">
            <i class="fas fa-shield-alt"></i> Hak Akses
        </button>
    </div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'">
            <i class="fas fa-plus"></i> Tambah Akun
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- TAB: PENGURUS --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelPengurus">
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>No</th><th>Pengguna</th><th>Username</th><th>Level</th><th>RT</th><th>WhatsApp</th>
                <th style="text-align:center;">Status</th><th style="text-align:center;">Terakhir Login</th><th style="text-align:center;">Aksi</th>
            </tr></thead>
            <tbody>
                @foreach($pengurus as $idx => $u)
                @php $clr = $levelColors[$u->level] ?? 'abu3'; @endphp
                <tr style="{{ $u->status !== 'aktif' ? 'opacity:0.5;' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:8px; background:var(--{{ $clr }}-pale, var(--abu)); display:flex; align-items:center; justify-content:center;">
                                <i class="fas {{ $levelIcons[$u->level] ?? 'fa-user' }}" style="color:var(--{{ $clr }}, var(--text3)); font-size:14px;"></i>
                            </div>
                            <div>
                                <div style="font-weight:700; font-size:14px;">{{ $u->namaLengkap }}</div>
                                @if($u->isDefault)<span style="font-size:10px; color:var(--emas);">DEFAULT</span>@endif
                            </div>
                        </div>
                    </td>
                    <td class="td-mono" style="font-size:13px;">{{ $u->username }}</td>
                    <td><span class="badge" style="background:var(--{{ $clr }}-pale, var(--abu)); color:var(--{{ $clr }}, var(--text3)); padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700;">{{ $u->level_label }}</span></td>
                    <td>{{ $u->rt ?? '-' }}</td>
                    <td style="font-size:12px;">{{ $u->wa ?? '-' }}</td>
                    <td style="text-align:center;">
                        @if($u->status === 'aktif')
                            <span class="badge badge-success" style="font-size:10px;">Aktif</span>
                        @else
                            <span class="badge badge-danger" style="font-size:10px;">Nonaktif</span>
                        @endif
                    </td>
                    <td style="text-align:center; font-size:11px; color:var(--text3);">{{ $u->last_login_at ? $u->last_login_at->format('d/m/Y H:i') : '-' }}</td>
                    <td style="text-align:center;">
                        <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-outline" style="font-size:10px; padding:4px 8px;" onclick="openEdit({{ json_encode($u) }})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm" style="font-size:10px; padding:4px 8px; background:var(--emas-muda); color:var(--emas); border:none;" onclick="openPin({{ $u->id }}, '{{ $u->username }}')"><i class="fas fa-key"></i></button>
                            @if(!$u->isDefault)
                            <form method="POST" action="{{ route('akun.toggleStatus', $u->id) }}" style="display:inline;">@csrf
                                <button type="submit" class="btn btn-sm" style="font-size:10px; padding:4px 8px; background:{{ $u->status === 'aktif' ? 'var(--merah-pale)' : 'var(--hijau-pale)' }}; color:{{ $u->status === 'aktif' ? 'var(--merah)' : 'var(--hijau)' }}; border:none;">
                                    <i class="fas {{ $u->status === 'aktif' ? 'fa-ban' : 'fa-check' }}"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- TAB: WARGA --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelWarga" style="display:none;">

{{-- Search bar for warga --}}
<div style="margin-bottom:12px;">
    <input type="text" id="wargaSearchInput" placeholder="🔍 Cari nama warga..." oninput="filterWargaAccounts()"
        style="width:100%; max-width:360px; padding:10px 14px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
</div>

@if($wargaUsers->count() > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table" id="wargaTable">
            <thead><tr>
                <th>No</th><th>Nama Warga</th><th>Username</th><th>RT</th><th>WhatsApp</th>
                <th style="text-align:center;">Status</th><th style="text-align:center;">Login Terakhir</th><th style="text-align:center;">Aksi</th>
            </tr></thead>
            <tbody>
                @foreach($wargaUsers as $idx => $u)
                <tr class="warga-row" data-nama="{{ strtolower($u->namaLengkap) }}" style="{{ $u->status !== 'aktif' ? 'opacity:0.5;' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:8px; background:var(--abu); display:flex; align-items:center; justify-content:center;">
                                <i class="fas fa-user" style="color:var(--text3); font-size:14px;"></i>
                            </div>
                            <div style="font-weight:600; font-size:14px;">{{ $u->namaLengkap }}</div>
                        </div>
                    </td>
                    <td class="td-mono" style="font-size:13px;">{{ $u->username }}</td>
                    <td>{{ $u->rt ?? '-' }}</td>
                    <td style="font-size:12px;">{{ $u->wa ?? '-' }}</td>
                    <td style="text-align:center;">
                        @if($u->status === 'aktif')
                            <span class="badge badge-success" style="font-size:10px;">Aktif</span>
                        @else
                            <span class="badge badge-danger" style="font-size:10px;">Nonaktif</span>
                        @endif
                    </td>
                    <td style="text-align:center; font-size:11px; color:var(--text3);">{{ $u->last_login_at ? $u->last_login_at->format('d/m H:i') : 'Belum login' }}</td>
                    <td style="text-align:center;">
                        <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-outline" style="font-size:10px; padding:4px 8px;" onclick="openEdit({{ json_encode($u) }})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm" style="font-size:10px; padding:4px 8px; background:var(--emas-muda); color:var(--emas); border:none;" onclick="openPin({{ $u->id }}, '{{ $u->username }}')"><i class="fas fa-key"></i></button>
                            <form method="POST" action="{{ route('akun.toggleStatus', $u->id) }}" style="display:inline;">@csrf
                                <button type="submit" class="btn btn-sm" style="font-size:10px; padding:4px 8px; background:{{ $u->status === 'aktif' ? 'var(--merah-pale)' : 'var(--hijau-pale)' }}; color:{{ $u->status === 'aktif' ? 'var(--merah)' : 'var(--hijau)' }}; border:none;">
                                    <i class="fas {{ $u->status === 'aktif' ? 'fa-ban' : 'fa-check' }}"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center; padding:40px 20px;">
    <i class="fas fa-users" style="font-size:48px; color:var(--abu3); margin-bottom:16px; display:block;"></i>
    <h3 style="color:var(--text2); margin-bottom:8px;">Belum Ada Akun Warga</h3>
    <p style="color:var(--text3); font-size:13px; max-width:400px; margin:0 auto 20px;">
        Akun warga otomatis dibuat saat pendaftaran disetujui, atau bisa ditambahkan manual. Warga bisa mengakses aduan, surat, dan informasi administrasi.
    </p>
</div>
@endif

<div style="margin-top:12px; padding:12px 16px; background:var(--biru-pale); border-left:4px solid var(--biru); border-radius:6px; font-size:12px; color:var(--biru); line-height:1.6;">
    ℹ️ Akun warga <strong>otomatis dibuat</strong> saat pendaftaran disetujui di halaman <a href="{{ route('pendaftaran.index') }}" style="color:var(--hijau); font-weight:600;">Pendaftaran</a>.
    Username dibuat dari nama warga (lowercase tanpa spasi), PIN default: <code>123456</code>.
</div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- TAB: PERMISSION MATRIX --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelPermission" style="display:none;">
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="card-title"><i class="fas fa-shield-alt" style="color:var(--biru);"></i> Matriks Hak Akses</div>
        <button class="btn btn-primary btn-sm" id="savePermBtn" onclick="savePermissions()"><i class="fas fa-save"></i> Simpan Hak Akses</button>
    </div>
    <div class="card-sub">Kustomisasi menu yang dapat diakses setiap level pengguna. Super Admin selalu punya akses penuh.</div>

    @php
        $menuItems = getAllMenuItems();
        $levels = ['superadmin','ketua_rw','bendahara','petugas_rt','warga'];
        $levelLabels = ['superadmin'=>'Super Admin','ketua_rw'=>'Ketua RW','bendahara'=>'Bendahara','petugas_rt'=>'Petugas RT','warga'=>'Warga'];
        $perms = getMenuPermissions();
        $lastSection = '';
    @endphp

    <div style="overflow-x:auto; margin-top:12px;">
        <table class="data-table" style="min-width:700px;">
            <thead>
                <tr>
                    <th style="min-width:160px;">Menu</th>
                    @foreach($levels as $lv)
                    <th style="text-align:center; font-size:11px; min-width:90px;">
                        <span class="badge" style="background:var(--{{ $levelColors[$lv] ?? 'abu3' }}-pale, var(--abu)); color:var(--{{ $levelColors[$lv] ?? 'abu3' }}, var(--text3)); padding:4px 8px; border-radius:6px; font-size:10px; font-weight:700;">{{ $levelLabels[$lv] }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($menuItems as $item)
                @if($lastSection !== $item['section'])
                @php $lastSection = $item['section']; @endphp
                <tr style="background:var(--abu);">
                    <td colspan="{{ count($levels) + 1 }}" style="font-size:11px; font-weight:700; color:var(--text3); letter-spacing:1px; padding:6px 12px;">{{ $item['section'] }}</td>
                </tr>
                @endif
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="{{ str_contains($item['icon'], 'fab') ? $item['icon'] : 'fas '.$item['icon'] }}" style="color:var(--text3); width:16px; text-align:center; font-size:12px;"></i>
                            <span style="font-size:13px;">{{ $item['label'] }}</span>
                        </div>
                    </td>
                    @foreach($levels as $lv)
                    <td style="text-align:center;">
                        @if($lv === 'superadmin')
                        <input type="checkbox" checked disabled style="accent-color:var(--hijau); width:16px; height:16px; cursor:not-allowed;">
                        @else
                        <input type="checkbox"
                            class="perm-cb"
                            data-level="{{ $lv }}"
                            data-menu="{{ $item['key'] }}"
                            {{ in_array($item['key'], $perms[$lv] ?? []) ? 'checked' : '' }}
                            style="accent-color:var(--hijau); width:16px; height:16px; cursor:pointer;">
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div id="permStatus" style="margin-top:12px; display:none; font-size:13px; padding:10px; border-radius:var(--radius-sm);"></div>
</div>
</div>

<!-- Add User Modal -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:440px; margin:0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-user-plus" style="color:var(--hijau);"></i> Tambah Akun Baru</div></div>
        <form method="POST" action="{{ route('akun.store') }}">@csrf
            <div style="margin-bottom:12px;"><label class="sak-label">Nama Lengkap *</label><input type="text" name="namaLengkap" required class="sak-input" placeholder="Nama lengkap pengguna"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div><label class="sak-label">Username *</label><input type="text" name="username" required class="sak-input" placeholder="username"></div>
                <div><label class="sak-label">PIN (6 digit) *</label><input type="password" name="pin" required pattern="\d{6}" maxlength="6" class="sak-input" placeholder="000000"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div><label class="sak-label">Level *</label>
                    <select name="level" required class="sak-input" onchange="this.closest('form').querySelector('[name=rt]').parentElement.style.display = this.value==='petugas_rt'?'block':'none'">
                        <option value="warga">Warga</option><option value="petugas_rt">Petugas RT</option><option value="bendahara">Bendahara</option><option value="ketua_rw">Ketua RW</option><option value="superadmin">Super Admin</option>
                    </select></div>
                <div style="display:none;"><label class="sak-label">RT</label>
                    <select name="rt" class="sak-input"><option value="">-</option>@for($i=1;$i<=6;$i++)<option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}">RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor</select></div>
            </div>
            <div style="margin-bottom:16px;"><label class="sak-label">No. WhatsApp</label><input type="text" name="wa" class="sak-input" placeholder="08xxx"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:440px; margin:0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-user-edit" style="color:var(--biru);"></i> Edit Akun</div></div>
        <form id="editForm" method="POST" action="">@csrf @method('PUT')
            <div style="margin-bottom:12px;"><label class="sak-label">Nama Lengkap *</label><input type="text" name="namaLengkap" id="editNama" required class="sak-input"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div><label class="sak-label">Level *</label>
                    <select name="level" id="editLevel" required class="sak-input">
                        <option value="warga">Warga</option><option value="petugas_rt">Petugas RT</option><option value="bendahara">Bendahara</option><option value="ketua_rw">Ketua RW</option><option value="superadmin">Super Admin</option>
                    </select></div>
                <div><label class="sak-label">RT</label>
                    <select name="rt" id="editRT" class="sak-input"><option value="">-</option>@for($i=1;$i<=6;$i++)<option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}">RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor</select></div>
            </div>
            <div style="margin-bottom:16px;"><label class="sak-label">No. WhatsApp</label><input type="text" name="wa" id="editWA" class="sak-input"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('editModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Change PIN Modal -->
<div id="pinModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:380px; margin:0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-key" style="color:var(--emas);"></i> Ubah PIN</div></div>
        <div style="font-size:13px; color:var(--text3); margin-bottom:12px;">Akun: <b id="pinUser"></b></div>
        <form id="pinForm" method="POST" action="">@csrf
            <div style="margin-bottom:16px;"><label class="sak-label">PIN Baru (6 digit) *</label><input type="password" name="pin" required pattern="\d{6}" maxlength="6" class="sak-input" placeholder="000000"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('pinModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-key"></i> Ubah PIN</button>
            </div>
        </form>
    </div>
</div>

<style>
.sak-label { display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px; }
.sak-input { width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white; font-family:inherit; box-sizing:border-box; }
.sak-input:focus { border-color:var(--hijau); outline:none; box-shadow:0 0 0 3px rgba(46,125,50,0.1); }
</style>

<script>
// Tab navigation
function showAkunTab(tab) {
    ['pengurus','warga','permission'].forEach(t => {
        const panel = document.getElementById('panel' + t.charAt(0).toUpperCase() + t.slice(1));
        const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (panel) panel.style.display = t === tab ? '' : 'none';
        if (btn) btn.className = t === tab ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });
}

// Warga search filter
function filterWargaAccounts() {
    const q = document.getElementById('wargaSearchInput').value.toLowerCase();
    document.querySelectorAll('.warga-row').forEach(row => {
        row.style.display = row.dataset.nama.includes(q) ? '' : 'none';
    });
}

// Modal handlers
['addModal','editModal','pinModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){if(e.target===this)this.style.display='none';});
});
function openEdit(u) {
    document.getElementById('editForm').action = '/akun/' + u.id;
    document.getElementById('editNama').value = u.namaLengkap;
    document.getElementById('editLevel').value = u.level;
    document.getElementById('editRT').value = u.rt || '';
    document.getElementById('editWA').value = u.wa || '';
    document.getElementById('editModal').style.display = 'flex';
}
function openPin(id, username) {
    document.getElementById('pinForm').action = '/akun/' + id + '/pin';
    document.getElementById('pinUser').textContent = username;
    document.getElementById('pinModal').style.display = 'flex';
}

function savePermissions() {
    const btn = document.getElementById('savePermBtn');
    const status = document.getElementById('permStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    const perms = { superadmin: {!! json_encode(getAllMenuItems()) !!}.map(m => m.key) };
    const levels = ['ketua_rw','bendahara','petugas_rt','warga'];
    levels.forEach(lv => { perms[lv] = []; });

    document.querySelectorAll('.perm-cb').forEach(cb => {
        if (cb.checked) perms[cb.dataset.level].push(cb.dataset.menu);
    });

    fetch('/akun/permissions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ permissions: perms })
    })
    .then(r => r.json())
    .then(data => {
        status.style.display = 'block';
        if (data.success) {
            status.style.background = 'var(--hijau-pale)';
            status.style.color = 'var(--hijau)';
            status.innerHTML = '<i class="fas fa-check-circle"></i> Hak akses berhasil disimpan.';
        } else {
            status.style.background = 'var(--merah-pale)';
            status.style.color = 'var(--merah)';
            status.innerHTML = '<i class="fas fa-times-circle"></i> Gagal menyimpan: ' + (data.message || 'Unknown error');
        }
        setTimeout(() => { status.style.display = 'none'; }, 5000);
    })
    .catch(err => {
        status.style.display = 'block';
        status.style.background = 'var(--merah-pale)';
        status.style.color = 'var(--merah)';
        status.innerHTML = '<i class="fas fa-times-circle"></i> Error: ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Simpan Hak Akses';
    });
}
</script>
@endsection
