@extends('layouts.app')
@section('title', 'Manajemen Desa')
@section('page-title', 'Manajemen Desa')
@section('page-subtitle', 'Buka desa & RW baru · khusus admin platform')

@push('styles')
<style>
/* Gaya ber-scope halaman: .sak-input global masih polos (bawaan browser),
   sedangkan halaman baru wajib memenuhi target sentuh 44px + font 16px
   (anti zoom iOS). Ditaruh di sini, bukan di primitif, supaya tampilan
   halaman lama tidak ikut berubah tanpa diminta (tercatat di TODO). */
.tenant-form .sak-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; }
.tenant-form .sak-input {
    display: block; width: 100%; max-width: 460px; min-height: 44px;
    font-size: 16px; padding: 10px 12px; border: 1px solid var(--abu2, #d5d9d4);
    border-radius: var(--radius, 8px); background: var(--surface, #fff);
}
.tenant-form .btn { min-height: 44px; }
.tenant-aksi { display: flex; flex-wrap: wrap; gap: 8px; }
.tenant-aksi .btn, .tenant-edit .btn { min-height: 44px; }
.tenant-edit { padding: 12px 16px; border-top: 1px solid var(--abu2, #e3e6e2); }
.tenant-edit summary { cursor: pointer; font-size: 13px; font-weight: 600; min-height: 44px; display: flex; align-items: center; gap: 6px; }
.rw-nomor-form { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.rw-nomor-input {
    width: 64px; min-height: 44px; font-size: 16px; text-align: center;
    padding: 8px; border: 1px solid var(--abu2, #d5d9d4);
    border-radius: var(--radius, 8px); background: var(--surface, #fff);
}
</style>
@endpush

@section('content')

@if(session('success'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--hijau); padding:12px 16px;" role="status">
    <i class="fas fa-check-circle" style="color:var(--hijau);" aria-hidden="true"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--merah); padding:12px 16px;" role="alert">
    <i class="fas fa-triangle-exclamation" style="color:var(--merah);" aria-hidden="true"></i> {{ session('error') }}
</div>
@endif
{{-- Kolom Aksi bisa berada di area scroll tabel pada layar sempit, jadi
     kegagalan ganti nomor juga ditampilkan sebagai banner di atas. --}}
@if($errors->has('nomor'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--merah); padding:12px 16px;" role="alert">
    <i class="fas fa-triangle-exclamation" style="color:var(--merah);" aria-hidden="true"></i> Ganti nomor RW gagal: {{ $errors->first('nomor') }}
</div>
@endif

@if(session('hasilAdminDesa'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--hijau);">
    <div class="card-header"><div class="card-title"><i class="fas fa-user-tie" style="color:var(--hijau);" aria-hidden="true"></i> Akun admin desa · {{ session('hasilAdminDesa')['desa'] }}</div></div>
    @if(session('hasilAdminDesa')['pin'])
        <p style="margin:0; font-size:14px;">
            Username <code>{{ session('hasilAdminDesa')['username'] }}</code> ·
            PIN <code>{{ session('hasilAdminDesa')['pin'] }}</code>
            <strong style="color:var(--merah);">- catat SEKARANG, hanya tampil sekali.</strong>
        </p>
        <p style="margin:8px 0 0; font-size:12.5px; color:var(--text3);">
            Admin desa mengelola akun seluruh RW desanya lewat
            <code>{{ session('hasilAdminDesa')['username'] ? str_replace('-admin', '', session('hasilAdminDesa')['username']) : '' }}.desa.jabnet.id/akun</code>.
        </p>
    @else
        <p style="margin:0; font-size:14px;">Akun <code>{{ session('hasilAdminDesa')['username'] }}</code> sudah ada - PIN tidak diubah.</p>
    @endif
</div>
@endif

@if(session('hasilTenant'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--hijau);">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-check-circle" style="color:var(--hijau);"></i> {{ session('hasilTenant')['desa'] }} berhasil diproses</div>
    </div>
    <p style="margin:0 0 10px; font-size:13px; color:var(--merah); font-weight:700;">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        Catat PIN di bawah SEKARANG - hanya ditampilkan sekali dan tidak bisa dilihat lagi.
    </p>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>RW</th><th>Alamat portal</th><th>Akun admin</th><th>PIN awal</th></tr></thead>
            <tbody>
            @foreach(session('hasilTenant')['baris'] as $b)
                <tr>
                    <td>RW {{ $b['rw'] }}</td>
                    <td><code>{{ $b['hostname'] }}</code></td>
                    <td>{{ $b['username'] ?? '-' }}</td>
                    <td>{{ $b['pin'] ?? 'sudah ada, PIN tidak diubah' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p style="margin:10px 0 0; font-size:12.5px; color:var(--text3);">
        Langkah berikutnya per alamat: cPanel &gt; Domains &gt; Create a New Domain
        (document root = folder <code>public</code> aplikasi) lalu Run AutoSSL - kecuali
        sudah terpasang domain wildcard <code>*.desa.jabnet.id</code> ber-SSL.
    </p>
</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-city" style="color:var(--hijau);"></i> Buka Desa / Tambah RW</div>
    </div>
    <p style="margin:0 0 14px; font-size:13px; color:var(--text3);">
        Mengisi label desa yang sudah ada = menambah RW ke desa itu (yang lama tidak
        tersentuh). Tiap RW mendapat subdomain + akun admin ber-PIN acak.
    </p>
    <form method="POST" action="{{ route('tenant.store') }}" class="tenant-form">
        @csrf
        <div style="margin-bottom:12px;">
            <label class="sak-label" for="tenant-nama">Nama desa *</label>
            <input type="text" id="tenant-nama" name="nama" required maxlength="100" class="sak-input"
                   value="{{ old('nama') }}" placeholder="Desa Cibunar"
                   @error('nama') aria-invalid="true" aria-describedby="err-nama" @enderror>
            @error('nama')<p id="err-nama" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <div style="margin-bottom:12px;">
            <label class="sak-label" for="tenant-label">Label domain * <span style="font-weight:400; color:var(--text3);">(huruf kecil/angka/strip; jadi bagian alamat)</span></label>
            <input type="text" id="tenant-label" name="label" required maxlength="40" class="sak-input"
                   value="{{ old('label') }}" placeholder="cibunar"
                   @error('label') aria-invalid="true" aria-describedby="err-label" @enderror>
            @error('label')<p id="err-label" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <div style="margin-bottom:12px;">
            <label class="sak-label" for="tenant-kecamatan">Kecamatan</label>
            <input type="text" id="tenant-kecamatan" name="kecamatan" maxlength="100" class="sak-input"
                   value="{{ old('kecamatan') }}" placeholder="Tarogong Kidul">
        </div>
        <div style="margin-bottom:16px;">
            <label class="sak-label" for="tenant-rw">Nomor RW * <span style="font-weight:400; color:var(--text3);">(pisahkan koma)</span></label>
            <input type="text" id="tenant-rw" name="rw" required maxlength="100" class="sak-input"
                   value="{{ old('rw') }}" placeholder="01,02,03"
                   @error('rw') aria-invalid="true" aria-describedby="err-rw" @enderror>
            @error('rw')<p id="err-rw" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus" aria-hidden="true"></i> Buka Tenant</button>
    </form>
</div>

@forelse($desas as $desa)
<div class="card" style="padding:0; overflow:hidden; margin-bottom:16px;">
    <div class="card-header" style="padding:14px 16px; margin:0;">
        <div class="card-title">
            <i class="fas fa-map-location-dot" style="color:var(--hijau);" aria-hidden="true"></i>
            {{ $desa->name }}
            <span style="font-weight:400; font-size:12px; color:var(--text3);">· label <code>{{ $desa->slug }}</code> · {{ $desa->children->count() }} RW</span>
        </div>
    </div>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>RW</th><th>Alamat portal</th><th>Admin</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @foreach($desa->children as $rw)
                <tr>
                    <td>{{ $rw->name }}</td>
                    <td>
                        @forelse($rw->domains as $d)
                            <a href="https://{{ $d->hostname }}" target="_blank" rel="noopener">{{ $d->hostname }}</a>@if(!$loop->last), @endif
                        @empty
                            <span style="color:var(--text3);">belum ada</span>
                        @endforelse
                    </td>
                    <td>{{ ($adminPerOrg[$rw->id] ?? collect())->pluck('username')->implode(', ') ?: '-' }}</td>
                    <td>{{ $rw->status ?? 'aktif' }}</td>
                    <td>
                        <div class="tenant-aksi">
                            @php $galat = $errors->has('nomor') && (string) old('_rw_org') === (string) $rw->id; @endphp
                            <form method="POST" action="{{ route('tenant.rw.update', $rw->id) }}" class="rw-nomor-form"
                                  onsubmit="return confirm('Ganti nomor {{ $rw->name }} {{ $desa->name }}? Alamat portal ikut berganti; alamat lama tetap hidup sebagai alias.')">
                                @csrf @method('PUT')
                                <input type="hidden" name="_rw_org" value="{{ $rw->id }}">
                                <label class="visually-hidden" for="nomor-{{ $rw->id }}">Nomor RW baru</label>
                                <input type="text" inputmode="numeric" pattern="\d{1,2}" maxlength="2" required
                                       id="nomor-{{ $rw->id }}" name="nomor" class="rw-nomor-input"
                                       value="{{ $galat ? old('nomor') : trim(preg_replace('/\D+/', '', $rw->name)) }}"
                                       @if($galat) aria-invalid="true" aria-describedby="err-nomor-{{ $rw->id }}" @endif>
                                <button type="submit" class="btn btn-outline btn-sm">Ganti nomor</button>
                                @if($galat)
                                    <p id="err-nomor-{{ $rw->id }}" role="alert" style="color:var(--merah); font-size:12px; margin:0; flex-basis:100%;">{{ $errors->first('nomor') }}</p>
                                @endif
                            </form>
                            <a href="{{ route('tenant.rw.matriks', $rw->id) }}" class="btn btn-outline btn-sm">
                                Hak akses
                            </a>
                            <form method="POST" action="{{ route('tenant.rw.toggle', $rw->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">
                                    {{ ($rw->status ?? 'aktif') === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('tenant.rw.destroy', $rw->id) }}"
                                  onsubmit="return confirm('Hapus {{ $rw->name }} {{ $desa->name }}? Hanya bisa bila belum berisi data.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--merah);">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <details class="tenant-edit">
        <summary><i class="fas fa-pen" aria-hidden="true"></i> Ubah identitas desa @if($desa->children->isEmpty()) / hapus desa @endif</summary>
        @php
            // Nama tersimpan berformat "Nama (Kecamatan)" - pecah untuk form.
            preg_match('/^(.*?)(?:\s*\((.*)\))?$/', $desa->name, $bagian);
        @endphp
        <form method="POST" action="{{ route('tenant.update', $desa->id) }}" class="tenant-form" style="margin-top:10px;">
            @csrf @method('PUT')
            <div style="margin-bottom:12px;">
                <label class="sak-label" for="nama-{{ $desa->id }}">Nama desa *</label>
                <input type="text" id="nama-{{ $desa->id }}" name="nama" required maxlength="100"
                       class="sak-input" value="{{ old('nama', $bagian[1] ?? $desa->name) }}">
            </div>
            <div style="margin-bottom:12px;">
                <label class="sak-label" for="kecamatan-{{ $desa->id }}">Kecamatan</label>
                <input type="text" id="kecamatan-{{ $desa->id }}" name="kecamatan" maxlength="100"
                       class="sak-input" value="{{ old('kecamatan', $bagian[2] ?? '') }}">
            </div>
            <div style="margin-bottom:12px;">
                <label class="sak-label" for="kabupaten-{{ $desa->id }}">Kabupaten/Kota</label>
                <input type="text" id="kabupaten-{{ $desa->id }}" name="kabupaten" maxlength="100"
                       class="sak-input" value="{{ old('kabupaten', $kabupatenPerDesa[$desa->id] ?? '') }}"
                       placeholder="mis. Kota Sukabumi">
            </div>
            <div class="tenant-aksi">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save" aria-hidden="true"></i> Simpan identitas</button>
            </div>
        </form>
        <form method="POST" action="{{ route('tenant.adminDesa', $desa->id) }}" style="margin-top:10px;">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm" style="min-height:44px;">
                <i class="fas fa-user-tie" aria-hidden="true"></i> Buat akun admin desa
            </button>
        </form>
        @if($desa->children->isEmpty())
        <form method="POST" action="{{ route('tenant.destroy', $desa->id) }}" style="margin-top:10px;"
              onsubmit="return confirm('Hapus {{ $desa->name }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--merah); min-height:44px;">
                <i class="fas fa-trash" aria-hidden="true"></i> Hapus desa (tanpa RW)
            </button>
        </form>
        @endif
    </details>
</div>
@empty
<div class="card" style="text-align:center; padding:40px 20px;">Belum ada desa terdaftar.</div>
@endforelse

@endsection
