@extends('layouts.app')
@section('title', 'Kegiatan RW')
@section('page-title', 'Kegiatan RW')
@section('page-subtitle', 'Pencatatan kegiatan dan acara RW/RT')

@section('content')
<div class="toolbar">
    <div class="toolbar-left"><span style="font-size:13px; font-weight:600;">{{ count($kegiatans) }} kegiatan tercatat</span></div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> Tambah Kegiatan</button>
    </div>
</div>

@if(count($kegiatans) > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Tempat</th><th>PIC</th><th>Jenis</th><th>Status</th><th style="text-align:center;">Aksi</th></tr></thead>
            <tbody>
                @foreach($kegiatans as $k)
                <tr>
                    <td style="white-space:nowrap;">{{ date('d M Y', strtotime($k->tanggal)) }}</td>
                    <td><div class="list-name">{{ $k->judul }}</div><div class="list-meta">{{ $k->waktu ?? '' }}</div></td>
                    <td>{{ $k->tempat ?? '-' }}</td>
                    <td>{{ $k->pic ?? '-' }}</td>
                    <td><span class="badge" style="background:var(--biru-pale); color:var(--biru); padding:4px 10px; border-radius:6px; font-size:11px;">{{ $k->jenis ?? '-' }}</span></td>
                    <td>
                        @if($k->status == 'selesai')<span class="badge badge-success">Selesai</span>
                        @elseif($k->status == 'dibatalkan')<span class="badge badge-danger">Batal</span>
                        @else<span class="badge" style="background:var(--emas-muda); color:var(--emas); padding:4px 10px; border-radius:6px; font-size:11px;">Direncanakan</span>@endif
                    </td>
                    <td style="text-align:center;">
                        <form action="{{ route('kegiatan.destroy', $k->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus kegiatan {{ $k->judul }}?')">
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
    <i class="fas fa-calendar-alt" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2);">Belum Ada Kegiatan</h3>
    <p style="color:var(--text3); font-size:13px; max-width:400px; margin:8px auto 16px;">Catat kegiatan RW seperti rapat rutin, kerja bakti, acara sosial, atau kegiatan olahraga. Semua kegiatan akan terdokumentasi dengan PIC dan statusnya.</p>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> Tambah Kegiatan Pertama</button>
</div>
@endif

<!-- Add Modal -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:420px; margin:0;">
        <div class="card-header"><div class="card-title">Tambah Kegiatan</div></div>
        <form method="POST" action="{{ route('kegiatan.store') }}">
            @csrf
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Judul Kegiatan *</label><input type="text" name="judul" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tanggal *</label><input type="date" name="tanggal" required value="{{ date('Y-m-d') }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Waktu</label><input type="text" name="waktu" placeholder="09:00" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            </div>
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tempat</label><input type="text" name="tempat" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Jenis</label><select name="jenis" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;"><option>Rapat</option><option>Kerja Bakti</option><option>Sosial</option><option>Olahraga</option><option>Lainnya</option></select></div>
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">PIC</label><input type="text" name="pic" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            </div>
            <div style="margin-bottom:20px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Deskripsi</label><textarea name="deskripsi" rows="2" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical;"></textarea></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>document.getElementById('addModal').addEventListener('click', function(e){if(e.target===this)this.style.display='none';});</script>
@endsection
