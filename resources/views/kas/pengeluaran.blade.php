@extends('layouts.app')
@section('title', 'Pengeluaran Kas')
@section('page-title', 'Pengeluaran Kas')
@section('page-subtitle', 'Catatan pengeluaran kas')

@section('content')
@php
    $totalValid = $transaksi->where('voided', false)->sum('jumlah');
    $totalVoid = $transaksi->where('voided', true)->sum('jumlah');
    $perKas = $transaksi->where('voided', false)->groupBy('kas')->map(fn($g) => $g->sum('jumlah'));
@endphp

<!-- Summary -->
<div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card" style="border-left:4px solid var(--merah);"><div class="stat-accent" style="background:var(--merah);"></div><div class="stat-icon-box" style="background:var(--merah-pale); color:var(--merah);"><i class="fas fa-arrow-down"></i></div><div class="stat-label">TOTAL PENGELUARAN</div><div class="stat-value td-mono" style="color:var(--merah); font-size:18px;">{{ number_format($totalValid,0,',','.') }}</div><div class="stat-sub">{{ $transaksi->where('voided', false)->count() }} transaksi</div></div>
    <div class="stat-card green"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-wallet"></i></div><div class="stat-label">KAS UMUM</div><div class="stat-value td-mono" style="font-size:18px;">{{ number_format($perKas->get('umum', 0),0,',','.') }}</div><div class="stat-sub">Pengeluaran</div></div>
    <div class="stat-card gold"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--emas-muda); color:var(--emas);"><i class="fas fa-trash-alt"></i></div><div class="stat-label">KAS SAMPAH</div><div class="stat-value td-mono" style="font-size:18px;">{{ number_format($perKas->get('sampah', 0),0,',','.') }}</div><div class="stat-sub">Pengeluaran</div></div>
    <div class="stat-card blue"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--biru-pale); color:var(--biru);"><i class="fas fa-hand-holding-heart"></i></div><div class="stat-label">KAS PADARINGAN</div><div class="stat-value td-mono" style="font-size:18px;">{{ number_format($perKas->get('padaringan', 0),0,',','.') }}</div><div class="stat-sub">Pengeluaran</div></div>
</div>

<!-- Toolbar -->
<div class="toolbar" style="flex-wrap:wrap;">
    <div class="toolbar-left" style="flex-wrap:wrap; gap:8px;">
        <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <select name="tahun" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                @for($i = date('Y')+1; $i >= 2024; $i--)
                    <option value="{{ $i }}" {{ request('tahun', date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
            </select>
            <select name="bulan" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua Bulan</option>
                @foreach(['1'=>'Januari','2'=>'Februari','3'=>'Maret','4'=>'April','5'=>'Mei','6'=>'Juni','7'=>'Juli','8'=>'Agustus','9'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'] as $num => $nama)
                    <option value="{{ $num }}" {{ request('bulan') == $num ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
            <select name="kas" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua Kas</option>
                <option value="umum" {{ request('kas')=='umum' ? 'selected' : '' }}>Kas Umum</option>
                <option value="sampah" {{ request('kas')=='sampah' ? 'selected' : '' }}>Kas Sampah</option>
                <option value="padaringan" {{ request('kas')=='padaringan' ? 'selected' : '' }}>Kas Padaringan</option>
            </select>
        </form>
    </div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" style="background:var(--merah); border-color:var(--merah);" onclick="document.getElementById('addModal').style.display='flex'">
            <i class="fas fa-minus-circle"></i> Catat Pengeluaran
        </button>
    </div>
</div>

@if(count($transaksi) > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Kas</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Operator</th>
                    <th style="text-align:right;">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaksi as $idx => $t)
                <tr style="{{ $t->voided ? 'opacity:0.4; text-decoration:line-through;' : '' }}">
                    <td>{{ $idx + 1 }}</td>
                    <td style="white-space:nowrap;">{{ date('d/m/Y', strtotime($t->tanggal)) }}</td>
                    <td>
                        @if($t->kas == 'umum')<span class="badge" style="background:var(--hijau-pale); color:var(--hijau); padding:4px 10px; border-radius:6px; font-size:11px;">Umum</span>
                        @elseif($t->kas == 'sampah')<span class="badge" style="background:var(--emas-muda); color:var(--emas); padding:4px 10px; border-radius:6px; font-size:11px;">Sampah</span>
                        @else<span class="badge" style="background:var(--biru-pale); color:var(--biru); padding:4px 10px; border-radius:6px; font-size:11px;">Padaringan</span>@endif
                    </td>
                    <td style="font-size:12px;">{{ $t->kategori ?? '-' }}</td>
                    <td>{{ $t->keterangan }}</td>
                    <td style="font-size:12px; color:var(--text3);">{{ $t->operator ?? '-' }}</td>
                    <td class="td-mono" style="text-align:right; color:var(--merah); font-weight:700;">-{{ number_format($t->jumlah, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:var(--abu); font-weight:700;">
                    <td colspan="6" style="text-align:right;">Total Pengeluaran</td>
                    <td class="td-mono" style="text-align:right; color:var(--merah); font-size:15px;">-{{ number_format($totalValid, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-file-invoice-dollar" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2); margin-bottom:8px;">Belum Ada Catatan Pengeluaran</h3>
    <p style="color:var(--text3); font-size:13px;">Gunakan tombol di atas untuk mencatat pengeluaran pertama.</p>
</div>
@endif

<!-- Modal Tambah -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:440px; margin:0;">
        <div class="card-header"><div class="card-title" style="color:var(--merah);"><i class="fas fa-minus-circle"></i> Catat Pengeluaran Baru</div></div>
        <form method="POST" action="{{ route('kas.pengeluaran.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Sumber Kas *</label>
                    <select name="kas" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option value="">-- Pilih --</option>
                        <option value="umum">Kas Umum</option>
                        <option value="sampah">Kas Sampah</option>
                        <option value="padaringan">Kas Padaringan</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Tanggal *</label>
                    <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Kategori</label>
                <select name="kategori" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="">Pilih Kategori</option>
                    <option value="Operasional">Operasional</option>
                    <option value="Infrastruktur">Infrastruktur</option>
                    <option value="Kegiatan">Kegiatan</option>
                    <option value="Sosial">Sosial</option>
                    <option value="ATK">ATK & Perlengkapan</option>
                    <option value="Transport">Transport</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Keterangan *</label>
                <textarea name="keterangan" rows="2" required placeholder="Contoh: Pembelian tong sampah 20L" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical;"></textarea>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Jumlah (Rp) *</label>
                <input type="number" name="jumlah" required min="1" placeholder="50000" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;">
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1; background:var(--merah); border-color:var(--merah);"><i class="fas fa-check"></i> Catat Keluar</button>
            </div>
        </form>
    </div>
</div>
<script>document.getElementById('addModal').addEventListener('click', function(e) { if (e.target === this) this.style.display='none'; });</script>
@endsection
