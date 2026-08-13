@extends('layouts.app')
@section('title', 'Data Warga')
@section('page-title', 'Data Warga')
@section('page-subtitle', 'Basis data KK warga RW 10')

@section('content')
<!-- Filter Bar -->
<div class="toolbar" style="flex-wrap:wrap;">
    <div class="toolbar-left" style="flex-wrap:wrap;">
        <form method="GET" action="{{ route('warga.index') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text3); font-size:12px;"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama/HP..." style="padding:8px 10px 8px 32px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px; width:180px;">
            </div>
            <select name="rt" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua RT</option>
                @for($i=1;$i<=6;$i++)<option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}" {{ request('rt') == str_pad($i,2,'0',STR_PAD_LEFT) ? 'selected' : '' }}>RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor
            </select>
            <select name="status" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="aktif" {{ request('status')=='aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ request('status')=='nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
            <select name="filter" class="btn btn-outline btn-sm" onchange="this.form.submit()" style="{{ request('filter')=='belum_kk' ? 'border-color:var(--emas); color:var(--emas); font-weight:700;' : '' }}">
                <option value="">Semua Data</option>
                <option value="belum_kk" {{ request('filter')=='belum_kk' ? 'selected' : '' }}>⚠️ Belum ada KK</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="toolbar-right" style="gap:8px; display:flex;">
        <button onclick="document.getElementById('importModal').style.display='flex'" class="btn btn-outline btn-sm"><i class="fas fa-file-import"></i> Import</button>
        <button onclick="document.getElementById('exportModal').style.display='flex'" class="btn btn-outline btn-sm"><i class="fas fa-file-export"></i> Export</button>
        <a href="{{ route('warga.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah KK</a>
    </div>
</div>

@if(count($keluarga) > 0)

{{-- Desktop Table --}}
<div class="card warga-desktop-table" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>No</th><th>Nama KK</th><th>NIK KK</th><th>RT</th><th>Alamat</th><th>No HP</th><th style="text-align:center;">Jiwa</th><th>Status</th><th style="text-align:center;">Aksi</th></tr></thead>
            <tbody>
                @foreach($keluarga as $i => $kk)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><div class="list-name">{{ $kk->nama }}</div></td>
                    <td style="font-family:monospace; font-size:11px;">@if($kk->noKK){{ $kk->noKK }}@else<span style="color:var(--emas); font-family:inherit; font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Belum</span>@endif</td>
                    <td style="text-align:center; font-weight:700;">{{ $kk->rt }}</td>
                    <td style="max-width:200px;">{{ Str::limit($kk->alamat, 30) }}</td>
                    <td class="td-mono" style="font-size:12px;">{{ $kk->noHP ?? '-' }}</td>
                    <td style="text-align:center;"><span style="background:var(--biru-pale); color:var(--biru); padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">{{ 1 + ($kk->anggota_count ?? 0) }}</span></td>
                    <td>
                        <span class="badge" style="background:{{ $kk->status=='aktif' ? 'var(--hijau-pale)' : 'var(--merah-pale)' }}; color:{{ $kk->status=='aktif' ? 'var(--hijau)' : 'var(--merah)' }}; padding:4px 10px; border-radius:6px; font-size:11px;">{{ ucfirst($kk->status) }}</span>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <a href="{{ route('warga.edit', $kk->id) }}" class="btn btn-outline btn-sm" style="padding:4px 8px;" title="Edit"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('warga.destroy', $kk->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus KK {{ $kk->nama }}?\n\nData iuran terkait juga akan terpengaruh.\nTindakan ini tidak dapat dibatalkan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 8px; color:var(--merah); border-color:var(--merah);" title="Hapus"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile Card List --}}
<div class="warga-mobile-cards">
    @foreach($keluarga as $i => $kk)
    <a href="{{ route('warga.edit', $kk->id) }}" class="warga-card-item">
        <div class="warga-card-top">
            <div class="warga-card-avatar" style="background:{{ $kk->status=='aktif' ? 'var(--hijau)' : 'var(--merah)' }};">
                {{ strtoupper(substr($kk->nama, 0, 1)) }}
            </div>
            <div class="warga-card-info">
                <div class="warga-card-name">{{ $kk->nama }}</div>
                <div class="warga-card-meta">
                    <span><i class="fas fa-map-marker-alt"></i> RT {{ $kk->rt }}</span>
                    <span><i class="fas fa-users"></i> {{ 1 + ($kk->anggota_count ?? 0) }} jiwa</span>
                    @if($kk->noHP)<span><i class="fas fa-phone"></i> {{ $kk->noHP }}</span>@endif
                </div>
            </div>
            <div style="margin-left:auto; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                <span class="badge" style="background:{{ $kk->status=='aktif' ? 'var(--hijau-pale)' : 'var(--merah-pale)' }}; color:{{ $kk->status=='aktif' ? 'var(--hijau)' : 'var(--merah)' }}; padding:3px 8px; border-radius:6px; font-size:10px; font-weight:700;">{{ ucfirst($kk->status) }}</span>
                @if(!$kk->noKK)<span style="color:var(--emas); font-size:10px; font-weight:600;"><i class="fas fa-exclamation-triangle"></i> No KK</span>@endif
            </div>
        </div>
        @if($kk->alamat)
        <div class="warga-card-addr"><i class="fas fa-home"></i> {{ Str::limit($kk->alamat, 50) }}</div>
        @endif
    </a>
    @endforeach
</div>

@else
<div class="card" style="text-align:center; padding:40px;">
    <img src="{{ asset('empty-state.png') }}" alt="" aria-hidden="true" class="blank-state__art" width="176" height="176">
    <h3 style="color:var(--text2);">Belum Ada Data Warga</h3>
    <p style="color:var(--text3); font-size:13px;">Tambahkan KK pertama melalui tombol <b>+ Tambah KK</b> di atas.</p>
</div>
@endif

{{-- MODAL EXPORT --}}
<div id="exportModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;" onclick="if(event.target===this)this.style.display='none'">
    <div class="card" style="width:100%; max-width:400px; padding:24px; animation:fadeUp 0.3s ease;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:18px;"><i class="fas fa-file-export" style="color:var(--hijau); margin-right:8px;"></i> Export Data Warga</h3>
            <button class="btn btn-outline btn-sm" style="border:none; color:var(--text3);" onclick="document.getElementById('exportModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:13px; color:var(--text2); margin-bottom:20px;">Silakan pilih data mana yang ingin diunduh dalam bentuk spreadsheet (CSV).</p>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <a href="{{ route('warga.export.keluarga') }}" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-home"></i> Export Data Kepala Keluarga (KK)</a>
            <a href="{{ route('warga.export.anggota') }}" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-users"></i> Export Data Anggota Warga</a>
        </div>
    </div>
</div>

{{-- MODAL IMPORT --}}
<div id="importModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:flex-start; justify-content:center; padding:40px 20px; overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
    <div class="card" style="width:100%; max-width:550px; padding:24px; animation:fadeUp 0.3s ease; margin:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:18px;"><i class="fas fa-file-import" style="color:var(--hijau); margin-right:8px;"></i> Import Data Masal</h3>
            <button class="btn btn-outline btn-sm" style="border:none; color:var(--text3);" onclick="document.getElementById('importModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>

        <div style="background:var(--hijau-pale); color:var(--hijau); padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:24px;">
            <i class="fas fa-info-circle"></i> Gunakan format template CSV baku di bawah ini agar data dapat terbaca oleh sistem.
        </div>

        {{-- Import KK --}}
        <div style="border:1.5px solid var(--abu2); border-radius:12px; padding:16px; margin-bottom:16px;">
            <h4 style="margin:0 0 8px; font-size:15px; color:var(--text);"><i class="fas fa-home" style="color:var(--emas);"></i> 1. Import Induk Keluarga (KK)</h4>
            <p style="font-size:12px; color:var(--text2); margin-bottom:12px;">Data Kepala Keluarga dan kondisi rumah tangga.</p>
            <div style="display:flex; gap:10px; margin-bottom:16px;">
                <a href="{{ route('warga.template', 'keluarga') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Unduh Template KK</a>
            </div>
            <form action="{{ route('warga.import.keluarga') }}" method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center;">
                @csrf
                <input type="file" name="file_keluarga" accept=".csv" required style="font-size:12px; flex:1;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload</button>
            </form>
        </div>

        {{-- Import Anggota --}}
        <div style="border:1.5px solid var(--abu2); border-radius:12px; padding:16px;">
            <h4 style="margin:0 0 8px; font-size:15px; color:var(--text);"><i class="fas fa-users" style="color:var(--biru);"></i> 2. Import Anggota Warga</h4>
            <p style="font-size:12px; color:var(--text2); margin-bottom:12px;">Data anak, istri, dll. Memerlukan referensi "Nama KK" (Bapaknya) yang sudah di-import pada langkah pertama.</p>
            <div style="display:flex; gap:10px; margin-bottom:16px;">
                <a href="{{ route('warga.template', 'anggota') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Unduh Template Anggota</a>
            </div>
            <form action="{{ route('warga.import.anggota') }}" method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center;">
                @csrf
                <input type="file" name="file_anggota" accept=".csv" required style="font-size:12px; flex:1;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload</button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Prevent double-click on import forms
document.querySelectorAll('#importModal form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengimpor...';
        }
    });
});
</script>
@endpush
