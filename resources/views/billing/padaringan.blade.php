@extends('layouts.app')
@section('title', 'Iuran Padaringan')
@section('page-title', 'Iuran Padaringan')
@section('page-subtitle', 'Pencatatan iuran bulanan tahun ' . $tahun)

@section('content')
@php
    $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
    $bulanNames = ['JAN'=>'Januari','FEB'=>'Februari','MAR'=>'Maret','APR'=>'April','MEI'=>'Mei','JUN'=>'Juni','JUL'=>'Juli','AGU'=>'Agustus','SEP'=>'September','OKT'=>'Oktober','NOV'=>'November','DES'=>'Desember'];
    $totalTerkumpul = 0;
    foreach($keluargas as $k) {
        $months = isset($iuran[$k->keluarga_id]) ? ($iuran[$k->keluarga_id]->months ?? []) : [];
        $totalTerkumpul += collect($months)->filter(fn($v) => $v)->count() * $tarifPadaringan;
    }
@endphp

<!-- Layout 2 kolom -->
<div class="iuran-layout">

    <!-- KIRI: Form Pencatatan SAK -->
    <div class="card iuran-form-panel">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1.5px solid var(--abu2);">
            <div style="width:36px; height:36px; border-radius:8px; background:var(--biru-pale); display:flex; align-items:center; justify-content:center;"><i class="fas fa-file-invoice-dollar" style="color:var(--biru);"></i></div>
            <div>
                <div style="font-weight:700; font-size:15px; color:var(--text);">Form Pencatatan</div>
                <div style="font-size:11px; color:var(--text3);">Jurnal Penerimaan Kas · Iuran Padaringan</div>
            </div>
        </div>

        <form id="bayarForm" method="POST" action="">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">

            <!-- 1. Tanggal -->
            <div style="margin-bottom:14px;">
                <label class="sak-label">Tanggal Transaksi *</label>
                <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" required class="sak-input">
            </div>

            <!-- 2. Cari & Pilih Warga -->
            <div style="margin-bottom:14px; position:relative;">
                <label class="sak-label">Nama Warga / KK *</label>
                <input type="text" id="wargaSearch" class="sak-input" placeholder="🔍 Ketik nama warga atau RT..."
                    autocomplete="off" oninput="filterWarga(this.value)">
                <input type="hidden" id="wargaSelect" name="_wargaId" required>
                <div id="wargaDropdown"
                    style="display:none; position:absolute; top:100%; left:0; right:0; z-index:999;
                           background:white; border:1.5px solid var(--biru); border-radius:var(--radius-sm);
                           max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,0.12);">
                </div>
                <div id="wargaData" style="display:none;">
                    @foreach($keluargas as $k)
                    <div data-id="{{ $k->keluarga_id }}" data-nama="{{ $k->nama }}" data-rt="{{ $k->rt }}" data-hp="{{ $k->noHP ?? '' }}">{{ $k->nama }} · RT {{ $k->rt }}</div>
                    @endforeach
                </div>
            </div>

            <!-- 3. Info Warga -->
            <div id="wargaInfo" style="display:none; background:var(--abu); border-radius:var(--radius-sm); padding:10px 14px; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; font-size:12px;">
                    <span style="color:var(--text3);">RT</span><span id="infoRT" style="font-weight:700;"></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:12px; margin-top:4px;">
                    <span style="color:var(--text3);">No. HP</span><span id="infoHP" style="font-weight:600;"></span>
                </div>
                <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--abu2);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">Status Tahun {{ $tahun }}:</div>
                    <div id="statusBulan" class="iuran-status-grid"></div>
                </div>
            </div>

            <!-- 4. Periode Bulan -->
            <div style="margin-bottom:14px;">
                <label class="sak-label">Periode Bulan *</label>
                <div id="bulanCheckboxes" class="iuran-month-grid">
                    @foreach($bulanAll as $b)
                    <label class="sak-checkbox-label" id="bLabel{{ $b }}" style="padding:8px 10px;">
                        <input type="checkbox" name="bulans[]" value="{{ $b }}" onchange="hitungTotal()">
                        <span>{{ $b }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <!-- 5. Perhitungan -->
            <div style="background:var(--abu); border-radius:var(--radius-sm); padding:12px 14px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text3); margin-bottom:4px;">
                    <span>Tarif per Bulan</span>
                    <span class="td-mono">Rp {{ number_format($tarifPadaringan,0,',','.') }}</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text3); margin-bottom:8px;">
                    <span>Jumlah Bulan</span>
                    <span id="jmlBulan" class="td-mono">0</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1.5px solid var(--abu2);">
                    <span style="font-weight:700; font-size:13px;">Total Dibayar</span>
                    <span id="totalBayar" class="td-mono" style="font-size:18px; font-weight:800; color:var(--biru);">Rp 0</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;" id="btnSimpan" disabled>
                <i class="fas fa-save"></i> Simpan Transaksi
            </button>
        </form>
    </div>

    <!-- KANAN -->
    <div>
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <select class="btn btn-outline btn-sm" onchange="window.location.href='?tahun='+this.value">
                    @for($i = date('Y')+1; $i >= 2024; $i--)
                        <option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div class="toolbar-right">
                <span style="font-size:12px; color:var(--text3);">Terkumpul tahun ini: <b class="td-mono" style="color:var(--biru);">Rp {{ number_format($totalTerkumpul,0,',','.') }}</b></span>
            </div>
        </div>

        <!-- Status Pembayaran -->
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:14px 18px; background:var(--abu); border-bottom:1.5px solid var(--abu2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="font-weight:700; font-size:14px;"><i class="fas fa-clipboard-check" style="color:var(--biru); margin-right:6px;"></i>Status Pembayaran · Tahun {{ $tahun }}</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="tableSearch" placeholder="🔍 Cari..." oninput="filterTable(this.value)"
                        style="padding:7px 10px; border:1.5px solid var(--abu2); border-radius:8px; font-size:12px; outline:none; width:120px; max-width:40vw;">
                    <span style="font-size:12px; color:var(--text3);">{{ $keluargas->count() }} KK</span>
                </div>
            </div>
            <div class="data-table-wrapper" style="overflow-x:auto; position:relative; background:linear-gradient(to right, transparent 95%, rgba(0,0,0,0.05) 100%);">
                <table class="data-table" id="padTable" style="min-width:900px;">
                    <thead><tr>
                        <th>No</th><th>Nama KK</th><th style="text-align:center;">RT</th>
                        @foreach($bulanAll as $b)
                            <th style="text-align:center; font-size:11px; min-width:34px;">{{ $b }}</th>
                        @endforeach
                        <th style="text-align:center;">Progress</th>
                    </tr></thead>
                    <tbody>
                        @foreach($keluargas as $idx => $k)
                        @php
                            $months = isset($iuran[$k->keluarga_id]) ? ($iuran[$k->keluarga_id]->months ?? []) : [];
                            $paidCount = collect($months)->filter(fn($v) => $v)->count();
                        @endphp
                        <tr class="warga-row" data-nama="{{ strtolower($k->nama) }}" data-rt="{{ $k->rt }}">
                            <td>{{ $idx+1 }}</td>
                            <td style="font-weight:600;">{{ $k->nama }}</td>
                            <td style="text-align:center;">{{ $k->rt }}</td>
                            @foreach($bulanAll as $b)
                                <td style="text-align:center;">
                                    @if(isset($months[$b]) && $months[$b])
                                        <i class="fas fa-check-circle" style="color:var(--hijau); font-size:14px;"></i>
                                    @else
                                        <i class="far fa-circle" style="color:var(--abu3); font-size:12px;"></i>
                                    @endif
                                </td>
                            @endforeach
                            <td style="text-align:center;">
                                @if($paidCount >= 12)
                                    <span class="badge badge-success" style="font-size:10px;">Lunas</span>
                                @else
                                    <span class="td-mono" style="font-size:11px; color:{{ $paidCount > 0 ? 'var(--emas)' : 'var(--merah)' }};">{{ $paidCount }}/12</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Jurnal -->
        @if(count($riwayat) > 0)
        <div class="card" style="padding:0; overflow:hidden; margin-top:16px;">
            <div style="padding:14px 18px; background:var(--abu); border-bottom:1.5px solid var(--abu2);">
                <div style="font-weight:700; font-size:14px;"><i class="fas fa-book" style="color:var(--biru); margin-right:6px;"></i>Jurnal Penerimaan · Tahun {{ $tahun }}</div>
            </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead><tr>
                        <th>Tanggal</th><th>No. Bukti</th><th>Uraian</th><th>Operator</th><th style="text-align:right;">Debit (Rp)</th>
                    </tr></thead>
                    <tbody>
                        @foreach($riwayat as $r)
                        <tr>
                            <td style="white-space:nowrap; font-size:12px;">{{ date('d/m/Y', strtotime($r->tanggal)) }}</td>
                            <td class="td-mono" style="font-size:11px; color:var(--text3);">{{ $r->transaksi_id }}</td>
                            <td style="font-size:13px;">{{ $r->keterangan }}</td>
                            <td style="font-size:12px; color:var(--text3);">{{ $r->operator ?? '-' }}</td>
                            <td class="td-mono" style="text-align:right; color:var(--hijau); font-weight:700;">{{ number_format($r->jumlah,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--abu); font-weight:700;">
                            <td colspan="4" style="text-align:right;">Total Penerimaan</td>
                            <td class="td-mono" style="text-align:right; color:var(--hijau); font-size:14px;">{{ number_format($riwayat->sum('jumlah'),0,',','.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

<style>
.sak-label { display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px; }
.sak-input { width:100%; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white; font-family:inherit; transition:border-color 0.2s; }
.sak-input:focus { border-color:var(--biru); outline:none; box-shadow:0 0 0 3px rgba(33,150,243,0.1); }
.sak-checkbox-label { display:flex; align-items:center; gap:6px; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); cursor:pointer; transition:all 0.2s; font-size:12px; font-weight:700; }
.sak-checkbox-label:hover { border-color:var(--biru); }
.sak-checkbox-label input { accent-color:var(--biru); width:14px; height:14px; }
.sak-checkbox-label.checked { border-color:var(--biru); background:var(--biru-pale); }
.sak-checkbox-label.disabled { opacity:0.4; pointer-events:none; border-color:var(--biru); background:var(--biru-pale); }
</style>

<script>
const tarif = {{ $tarifPadaringan }};
const iuranData = {!! json_encode($iuran->map(fn($i) => $i->months ?? [])) !!};
const bulanAllLocal = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];

// ─── Live-search warga ───
const wargaDataEl = document.getElementById('wargaData');
const wargaItems = [...wargaDataEl.querySelectorAll('[data-id]')].map(el => ({
    id: el.dataset.id, nama: el.dataset.nama, rt: el.dataset.rt, hp: el.dataset.hp
}));

function filterWarga(q) {
    const dd = document.getElementById('wargaDropdown');
    if (!q.trim()) { dd.style.display='none'; document.getElementById('wargaSelect').value=''; return; }
    const ql = q.toLowerCase();
    const hits = wargaItems.filter(w => w.nama.toLowerCase().includes(ql) || w.rt.includes(q));
    if (!hits.length) { dd.innerHTML='<div style="padding:12px;font-size:13px;color:var(--text3);">Tidak ditemukan</div>'; dd.style.display='block'; return; }
    dd.innerHTML = hits.slice(0,50).map(w =>
        `<div onclick="selectWarga('${w.id}','${w.nama.replace(/'/g,"\\'")}',' ${w.rt}','${w.hp}')" style="padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--abu2);" onmouseover="this.style.background='var(--abu)'" onmouseout="this.style.background='white'">
            <span style="font-weight:600;">${w.nama}</span> <span style="font-size:11px;color:var(--text3);">- RT ${w.rt}</span>
        </div>`
    ).join('');
    dd.style.display='block';
}
function selectWarga(id, nama, rt, hp) {
    document.getElementById('wargaSearch').value = nama+' · RT '+rt;
    document.getElementById('wargaSelect').value = id;
    document.getElementById('wargaDropdown').style.display='none';
    loadWargaStatus();
}
document.addEventListener('click', e => {
    if (!document.getElementById('wargaSearch').contains(e.target))
        document.getElementById('wargaDropdown').style.display='none';
});

function loadWargaStatus() {
    const id = document.getElementById('wargaSelect').value;
    const info = document.getElementById('wargaInfo');
    if (!id) { info.style.display='none'; document.getElementById('bayarForm').action=''; return; }

    const w = wargaItems.find(x => x.id === id);
    if (w) {
        document.getElementById('infoRT').textContent = w.rt;
        document.getElementById('infoHP').textContent = w.hp || '-';
    }
    document.getElementById('bayarForm').action = '/padaringan/bayar/' + id;
    info.style.display = 'block';

    const months = iuranData[id] || {};
    const container = document.getElementById('statusBulan');
    container.innerHTML = '';
    bulanAllLocal.forEach(b => {
        const paid = !!months[b];
        container.innerHTML += `<div style="text-align:center;font-size:10px;">` +
            (paid ? '<i class="fas fa-check-circle" style="color:var(--biru);font-size:12px;"></i>' : '<i class="far fa-circle" style="color:var(--abu3);font-size:10px;"></i>') +
            `<div style="color:${paid?'var(--biru)':'var(--text3)'};">${b}</div></div>`;
    });

    // Disable paid months
    document.querySelectorAll('#bulanCheckboxes input[type=checkbox]').forEach(cb => {
        const paid = !!months[cb.value];
        cb.checked = false; cb.disabled = paid;
        const lbl = cb.closest('label');
        lbl.classList.remove('checked','disabled');
        if (paid) lbl.classList.add('disabled');
    });
    hitungTotal();
}

function hitungTotal() {
    const checked = document.querySelectorAll('#bulanCheckboxes input:checked:not(:disabled)');
    document.getElementById('jmlBulan').textContent = checked.length;
    document.getElementById('totalBayar').textContent = 'Rp ' + (checked.length * tarif).toLocaleString('id-ID');
    document.getElementById('btnSimpan').disabled = checked.length === 0 || !document.getElementById('wargaSelect').value;
    document.querySelectorAll('#bulanCheckboxes input[type=checkbox]:not(:disabled)').forEach(cb => {
        cb.closest('label').classList.toggle('checked', cb.checked);
    });
}

function filterTable(q) {
    const ql = q.toLowerCase();
    document.querySelectorAll('#padTable .warga-row').forEach(row => {
        const nama = (row.dataset.nama || '');
        const rt   = (row.dataset.rt || '');
        row.style.display = (!q || nama.includes(ql) || rt.includes(q)) ? '' : 'none';
    });
}
</script>
@endsection
