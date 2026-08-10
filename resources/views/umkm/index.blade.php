@extends('layouts.app')
@section('title', 'UMKM Warga')
@section('page-title', 'UMKM Warga')
@section('page-subtitle', 'Direktori usaha milik warga RW 10')

@section('content')
<!-- Stats -->
<div class="stats-row" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card green"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-store"></i></div><div class="stat-label">TOTAL UMKM</div><div class="stat-value">{{ $umkms->count() }}</div></div>
    <div class="stat-card blue"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--biru-pale); color:var(--biru);"><i class="fas fa-check-circle"></i></div><div class="stat-label">AKTIF</div><div class="stat-value">{{ $umkms->where('status','aktif')->count() }}</div></div>
    <div class="stat-card gold"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--emas-muda); color:var(--emas);"><i class="fas fa-clock"></i></div><div class="stat-label">MUSIMAN</div><div class="stat-value">{{ $umkms->where('status','musiman')->count() }}</div></div>
</div>

<div class="toolbar">
    <div class="toolbar-left"><span style="font-size:13px; font-weight:600;">{{ $umkms->count() }} usaha terdaftar</span></div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> Daftarkan UMKM</button>
    </div>
</div>

@if($umkms->count() > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>Nama Usaha</th><th>Pemilik</th><th>RT</th><th>Jenis</th><th>Kontak</th><th>Status</th><th style="text-align:center;">Aksi</th></tr></thead>
            <tbody>
                @foreach($umkms as $u)
                <tr>
                    <td><div class="list-name">{{ $u->nama_usaha }}</div></td>
                    <td>{{ $u->pemilik }}</td>
                    <td style="text-align:center; font-weight:700;">{{ $u->rt }}</td>
                    <td><span class="badge" style="background:var(--biru-pale); color:var(--biru); padding:4px 10px; border-radius:6px; font-size:11px;">{{ $u->jenis ?? '-' }}</span></td>
                    <td class="td-mono" style="font-size:12px;">{{ $u->kontak ?? '-' }}</td>
                    <td>
                        @if($u->status=='aktif')<span class="badge badge-success">Aktif</span>
                        @elseif($u->status=='musiman')<span class="badge" style="background:var(--emas-muda); color:var(--emas); padding:4px 10px; border-radius:6px; font-size:11px;">Musiman</span>
                        @else<span class="badge" style="background:var(--abu); color:var(--text3); padding:4px 10px; border-radius:6px; font-size:11px;">{{ ucfirst($u->status ?? 'N/A') }}</span>@endif
                    </td>
                    <td style="text-align:center;">
                        <form action="{{ route('umkm.destroy', $u->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus UMKM {{ $u->nama_usaha }}?')">
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
@else
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-store" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2);">Belum Ada UMKM</h3>
    <p style="color:var(--text3); font-size:13px;">Daftarkan usaha melalui tombol di atas.</p>
</div>
@endif

<!-- Add Modal -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:420px; margin:0;">
        <div class="card-header"><div class="card-title">Daftarkan UMKM</div></div>
        <form method="POST" action="{{ route('umkm.store') }}">
            @csrf
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Usaha *</label><input type="text" name="nama_usaha" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Pemilik *</label><input type="text" name="pemilik" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">RT *</label><select name="rt" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">@for($i=1;$i<=6;$i++)<option>{{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>@endfor</select></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Jenis Usaha</label><select name="jenis" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;"><option>Makanan</option><option>Minuman</option><option>Jasa</option><option>Kerajinan</option><option>Perdagangan</option><option>Lainnya</option></select></div>
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Status *</label><select name="status" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;"><option value="aktif">Aktif</option><option value="musiman">Musiman</option></select></div>
            </div>
            <div style="margin-bottom:20px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">No Kontak</label><input type="text" name="kontak" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;" placeholder="08xxx"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Daftarkan</button>
            </div>
        </form>
    </div>
</div>
<script>document.getElementById('addModal').addEventListener('click', function(e){if(e.target===this)this.style.display='none';});</script>
@endsection
