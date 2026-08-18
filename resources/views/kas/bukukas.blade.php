@extends('layouts.app')
@section('title', 'Buku Kas')
@section('page-title', 'Buku Kas')
@section('page-subtitle', 'Buku besar keuangan')

@section('content')
@php $saldo = $totalMasuk - $totalKeluar; $canVoid = bolehkah('transaksi.void'); @endphp

<!-- Summary Stats -->
<div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card green"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-arrow-up"></i></div><div class="stat-label">TOTAL PEMASUKAN</div><div class="stat-value td-mono" style="font-size:18px; color:var(--hijau);">{{ number_format($totalMasuk, 0, ',', '.') }}</div><div class="stat-sub">{{ $transaksi->where('jenis','masuk')->count() }} transaksi</div></div>
    <div class="stat-card" style="border-left:4px solid var(--merah);"><div class="stat-accent" style="background:var(--merah);"></div><div class="stat-icon-box" style="background:var(--merah-pale); color:var(--merah);"><i class="fas fa-arrow-down"></i></div><div class="stat-label">TOTAL PENGELUARAN</div><div class="stat-value td-mono" style="font-size:18px; color:var(--merah);">{{ number_format($totalKeluar, 0, ',', '.') }}</div><div class="stat-sub">{{ $transaksi->where('jenis','keluar')->count() }} transaksi</div></div>
    <div class="stat-card blue"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--biru-pale); color:var(--biru);"><i class="fas fa-balance-scale"></i></div><div class="stat-label">SALDO AKHIR</div><div class="stat-value td-mono" style="font-size:18px; color:{{ $saldo >= 0 ? 'var(--hijau)' : 'var(--merah)' }};">{{ number_format($saldo, 0, ',', '.') }}</div><div class="stat-sub">Per periode filter</div></div>
    <div class="stat-card gold"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--emas-muda); color:var(--emas);"><i class="fas fa-exchange-alt"></i></div><div class="stat-label">TOTAL TRANSAKSI</div><div class="stat-value">{{ count($transaksi) }}</div><div class="stat-sub">Berdasarkan filter</div></div>
</div>

<!-- Toolbar -->
<div class="toolbar" style="flex-wrap:wrap;">
    <div class="toolbar-left">
        <form method="GET" action="{{ route('bukukas.index') }}" style="display:flex; gap:8px; flex-wrap:wrap;">
            <select name="tahun" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                @for($i = date('Y')+1; $i >= 2024; $i--)<option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>@endfor
            </select>
            <select name="bulan" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua Bulan</option>
                @foreach(['1'=>'Januari','2'=>'Februari','3'=>'Maret','4'=>'April','5'=>'Mei','6'=>'Juni','7'=>'Juli','8'=>'Agustus','9'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'] as $n => $l)
                    <option value="{{ $n }}" {{ $bulan == $n ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            <select name="kas" class="btn btn-outline btn-sm" onchange="this.form.submit()">
                <option value="">Semua Kas</option>
                <option value="umum" {{ $kas == 'umum' ? 'selected' : '' }}>Kas Umum</option>
                <option value="sampah" {{ $kas == 'sampah' ? 'selected' : '' }}>Kas Sampah</option>
                <option value="padaringan" {{ $kas == 'padaringan' ? 'selected' : '' }}>Kas Padaringan</option>
            </select>
        </form>
    </div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> Input Transaksi</button>
    </div>
</div>

<!-- Table: Debit / Kredit / Saldo Berjalan -->
@if(count($transaksi) > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper" style="overflow-x:auto;">
        <table class="data-table" style="min-width:850px;">
            <thead><tr>
                <th>No</th><th>Tanggal</th><th>No. Bukti</th><th>Kas</th><th>Keterangan</th>
                <th style="text-align:right;">Debit (Masuk)</th><th style="text-align:right;">Kredit (Keluar)</th>
                <th style="text-align:right;">Saldo Berjalan</th>
                @if($canVoid)<th style="text-align:center;">Aksi</th>@endif
            </tr></thead>
            <tbody>
                @php $runBal = 0; @endphp
                @foreach($transaksi as $idx => $t)
                @php
                    if($t->voided) {} elseif($t->jenis == 'masuk') $runBal += $t->jumlah; else $runBal -= $t->jumlah;
                @endphp
                <tr style="{{ $t->voided ? 'opacity:0.45; text-decoration:line-through;' : '' }}">
                    <td>{{ $idx + 1 }}</td>
                    <td style="white-space:nowrap; font-size:12px;">{{ date('d/m/Y', strtotime($t->tanggal)) }}</td>
                    <td class="td-mono" style="font-size:11px; color:var(--text3);">{{ $t->transaksi_id }}</td>
                    <td>
                        @if($t->kas == 'umum')<span class="badge" style="background:var(--hijau-pale); color:var(--hijau); padding:3px 8px; border-radius:5px; font-size:10px;">Umum</span>
                        @elseif($t->kas == 'sampah')<span class="badge" style="background:var(--emas-muda); color:var(--emas); padding:3px 8px; border-radius:5px; font-size:10px;">Sampah</span>
                        @else<span class="badge" style="background:var(--biru-pale); color:var(--biru); padding:3px 8px; border-radius:5px; font-size:10px;">Padaringan</span>@endif
                    </td>
                    <td style="font-size:13px;">
                        {{ $t->keterangan }}
                        @if($t->voided)
                            <div style="font-size:10px; color:var(--merah); margin-top:2px;"><i class="fas fa-ban"></i> VOID oleh {{ $t->void_by }} · {{ $t->void_reason }}</div>
                        @endif
                    </td>
                    <td class="td-mono" style="text-align:right; color:var(--hijau); font-weight:600;">{{ !$t->voided && $t->jenis == 'masuk' ? number_format($t->jumlah,0,',','.') : '' }}</td>
                    <td class="td-mono" style="text-align:right; color:var(--merah); font-weight:600;">{{ !$t->voided && $t->jenis == 'keluar' ? number_format($t->jumlah,0,',','.') : '' }}</td>
                    <td class="td-mono" style="text-align:right; font-weight:700; color:{{ $runBal >= 0 ? 'var(--text)' : 'var(--merah)' }};">{{ !$t->voided ? number_format($runBal,0,',','.') : '-' }}</td>
                    @if($canVoid)
                    <td style="text-align:center;">
                        @if(!$t->voided)
                        <button class="btn btn-sm" style="background:var(--merah-pale); color:var(--merah); border:none; font-size:10px; padding:4px 8px;" onclick="openVoid({{ $t->id }}, '{{ $t->transaksi_id }}', '{{ addslashes($t->keterangan) }}', {{ $t->jumlah }})"><i class="fas fa-undo"></i> Void</button>
                        @else
                        <span class="badge badge-danger" style="font-size:9px;">VOID</span>
                        @endif
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:var(--abu); font-weight:700;">
                    <td colspan="5" style="text-align:right;">TOTAL</td>
                    <td class="td-mono" style="text-align:right; color:var(--hijau); font-size:14px;">{{ number_format($totalMasuk, 0, ',', '.') }}</td>
                    <td class="td-mono" style="text-align:right; color:var(--merah); font-size:14px;">{{ number_format($totalKeluar, 0, ',', '.') }}</td>
                    <td class="td-mono" style="text-align:right; color:{{ $saldo >= 0 ? 'var(--hijau)' : 'var(--merah)' }}; font-size:14px;">{{ number_format($saldo, 0, ',', '.') }}</td>
                    @if($canVoid)<td></td>@endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-book" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2); margin-bottom:8px;">Belum Ada Transaksi</h3>
    <p style="color:var(--text3); font-size:13px;">Catatan akan otomatis terisi saat ada pembayaran iuran atau pencatatan pengeluaran.</p>
</div>
@endif

<!-- Void Confirmation Modal -->
@if($canVoid)
<div id="voidModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:440px; margin:0;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <div style="width:40px; height:40px; border-radius:10px; background:var(--merah-pale); display:flex; align-items:center; justify-content:center;"><i class="fas fa-exclamation-triangle" style="color:var(--merah); font-size:18px;"></i></div>
            <div><div style="font-weight:700; font-size:16px; color:var(--merah);">Void Transaksi</div><div style="font-size:11px; color:var(--text3);">Aksi ini akan membatalkan transaksi dan mengembalikan status iuran</div></div>
        </div>
        <div id="voidInfo" style="background:var(--abu); border-radius:var(--radius-sm); padding:12px 14px; margin-bottom:14px;">
            <div style="font-size:12px; color:var(--text3); margin-bottom:4px;">Transaksi:</div>
            <div id="voidDesc" style="font-weight:600; font-size:13px;"></div>
            <div style="margin-top:6px; display:flex; justify-content:space-between;">
                <span class="td-mono" id="voidNoBukti" style="font-size:11px; color:var(--text3);"></span>
                <span class="td-mono" id="voidJumlah" style="font-weight:700; color:var(--merah);"></span>
            </div>
        </div>
        <form id="voidForm" method="POST" action="">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Alasan Pembatalan * <span style="font-weight:400; color:var(--text3);">(min. 5 karakter)</span></label>
                <textarea name="void_reason" required minlength="5" rows="2" placeholder="Contoh: Salah input nominal, duplikasi pembayaran, dll." style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:none;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('voidModal').style.display='none'">Batal</button>
                <button type="submit" class="btn" style="flex:1; background:var(--merah); color:white; border:none;"><i class="fas fa-ban"></i> Konfirmasi Void</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Add Transaction Modal -->
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:440px; margin:0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-plus-circle" style="color:var(--hijau);"></i> Input Transaksi Manual</div></div>
        <form method="POST" action="{{ route('bukukas.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div><label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Jenis *</label>
                    <select name="jenis" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option value="masuk">✅ Pemasukan</option><option value="keluar">🔴 Pengeluaran</option></select></div>
                <div><label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Kas *</label>
                    <select name="kas" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                        <option value="umum">Kas Umum</option><option value="sampah">Kas Sampah</option><option value="padaringan">Kas Padaringan</option></select></div>
            </div>
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Tanggal *</label>
                <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="margin-bottom:14px;"><label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Keterangan *</label>
                <input type="text" name="keterangan" required placeholder="Contoh: Sumbangan warga RT 03" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="margin-bottom:20px;"><label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Jumlah (Rp) *</label>
                <input type="number" name="jumlah" required min="1" placeholder="0" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('addModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('addModal').addEventListener('click', function(e){if(e.target===this)this.style.display='none';});
@if($canVoid)
document.getElementById('voidModal').addEventListener('click', function(e){if(e.target===this)this.style.display='none';});
function openVoid(id, noBukti, desc, jumlah) {
    document.getElementById('voidForm').action = '/transaksi/' + id + '/void';
    document.getElementById('voidDesc').textContent = desc;
    document.getElementById('voidNoBukti').textContent = noBukti;
    document.getElementById('voidJumlah').textContent = 'Rp ' + jumlah.toLocaleString('id-ID');
    document.getElementById('voidModal').style.display = 'flex';
}
@endif
</script>
@endsection
