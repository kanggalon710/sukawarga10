@extends('layouts.app')
@section('title', 'Setoran Sampah')
@section('page-title', 'Setor Sampah RT')
@section('page-subtitle', 'Pencatatan setoran hasil pengumpulan iuran sampah per RT')

@section('content')
<div class="toolbar">
    <div class="toolbar-left"><span style="font-size:13px; font-weight:600;">{{ count($setoran) }} catatan setoran</span></div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> Tambah Setoran</button>
    </div>
</div>

@if(count($setoran) > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>RT</th><th>Keterangan</th><th style="text-align:right;">Jumlah</th></tr></thead>
            <tbody>
                @foreach($setoran as $s)
                <tr>
                    <td style="white-space:nowrap;">{{ date('d M Y', strtotime($s->tanggal ?? $s->created_at)) }}</td>
                    <td style="text-align:center; font-weight:700;">RT {{ $s->rt }}</td>
                    <td>{{ $s->keterangan ?? '-' }}</td>
                    <td class="td-mono" style="text-align:right; color:var(--hijau);">{{ number_format($s->jumlah, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-recycle" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2);">Belum Ada Setoran</h3>
    <p style="color:var(--text3); font-size:13px;">Catat setoran pertama melalui tombol di atas.</p>
</div>
@endif

<!-- Add Modal -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:380px; margin:0;">
        <div class="card-header"><div class="card-title">Tambah Setoran</div></div>
        <form method="POST" action="{{ route('kas.setor.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">RT *</label><select name="rt" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">@foreach(\App\Models\Keluarga::where('status','aktif')->distinct()->orderBy('rt')->pluck('rt') as $rt)<option>{{ $rt }}</option>@endforeach</select></div>
                <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tanggal</label><input type="date" name="tanggal" value="{{ date('Y-m-d') }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            </div>
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Jumlah (Rp) *</label><input type="number" name="jumlah" required min="1" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="margin-bottom:20px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Keterangan</label><input type="text" name="keterangan" placeholder="Setoran minggu ke-..." style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>document.getElementById('addModal').addEventListener('click', function(e){if(e.target===this)this.style.display='none';});</script>
@endsection
