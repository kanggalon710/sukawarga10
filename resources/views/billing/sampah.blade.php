@extends('layouts.app')
@section('title', 'Iuran Sampah')
@section('page-title', 'Iuran Sampah')
@section('page-subtitle', 'Pencatatan iuran mingguan tahun ' . $tahun)

@section('content')
@php
    $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
    $bulanNames = ['JAN'=>'Januari','FEB'=>'Februari','MAR'=>'Maret','APR'=>'April','MEI'=>'Mei','JUN'=>'Juni','JUL'=>'Juli','AGU'=>'Agustus','SEP'=>'September','OKT'=>'Oktober','NOV'=>'November','DES'=>'Desember'];
    $bulanIdx = $bulan - 1;
    $bulanKey = $bulanAll[$bulanIdx] ?? 'JAN';
    $bulanNama = $bulanNames[$bulanKey] ?? 'Januari';

    // Hitung jumlah minggu dalam bulan ini (bisa 4 atau 5)
    $firstDayOfMonth = (int) date('N', mktime(0,0,0,$bulan,1,$tahun)); // 1=Mon, 7=Sun
    $daysInMonth     = (int) date('t', mktime(0,0,0,$bulan,1,$tahun));
    $totalMingguBulan = (int) ceil(($firstDayOfMonth + $daysInMonth - 1) / 7);

    // Hitung total per bulan
    $totalTerkumpul = 0;
    foreach($keluargas as $k) {
        $w = isset($iuran[$k->keluarga_id]) ? ($iuran[$k->keluarga_id]->weeks ?? []) : [];
        foreach($w as $key => $val) {
            if($val === 'lunas' && str_starts_with($key, $bulanKey)) $totalTerkumpul += $tarifSampah;
        }
    }
@endphp

<!-- Layout 2 kolom: Form Pencatatan (kiri) + Status Pembayaran (kanan) -->
<div class="iuran-layout">

    <!-- KIRI: Form Pencatatan SAK -->
    <div class="card iuran-form-panel">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1.5px solid var(--abu2);">
            <div style="width:36px; height:36px; border-radius:8px; background:var(--hijau-pale); display:flex; align-items:center; justify-content:center;"><i class="fas fa-file-invoice-dollar" style="color:var(--hijau);"></i></div>
            <div>
                <div style="font-weight:700; font-size:15px; color:var(--text);">Form Pencatatan</div>
                <div style="font-size:11px; color:var(--text3);">Jurnal Penerimaan Kas · Iuran Sampah</div>
            </div>
        </div>

        <form id="bayarForm" method="POST" action="">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <input type="hidden" name="bulan_key" value="{{ $bulanKey }}">

            <!-- 1. Tanggal Bayar -->
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
                           background:white; border:1.5px solid var(--hijau); border-radius:var(--radius-sm);
                           max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,0.12);">
                </div>
                <!-- Hidden data store -->
                <div id="wargaData" style="display:none;">
                    @foreach($keluargas as $k)
                    <div data-id="{{ $k->keluarga_id }}" data-nama="{{ $k->nama }}" data-rt="{{ $k->rt }}" data-hp="{{ $k->noHP ?? '' }}">{{ $k->nama }} · RT {{ $k->rt }}</div>
                    @endforeach
                </div>
            </div>

            <!-- 3. Info Warga (muncul setelah pilih) -->
            <div id="wargaInfo" style="display:none; background:var(--abu); border-radius:var(--radius-sm); padding:10px 14px; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; font-size:12px;">
                    <span style="color:var(--text3);">RT</span>
                    <span id="infoRT" style="font-weight:700;"></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:12px; margin-top:4px;">
                    <span style="color:var(--text3);">No. HP</span>
                    <span id="infoHP" style="font-weight:600;"></span>
                </div>
                <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--abu2);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">Status {{ $bulanNama }}:</div>
                    <div id="statusMinggu" style="display:flex; gap:6px; flex-wrap:wrap;"></div>
                </div>
            </div>

            <!-- 4. Periode Minggu · dihitung otomatis berdasar kalender -->
            <div style="margin-bottom:14px;">
                <label class="sak-label">
                    Periode Minggu * <span style="font-weight:400; color:var(--text3);">( {{ $bulanNama }} {{ $tahun }})</span>
                    <span style="font-size:10px; font-weight:400; color:var(--hijau); margin-left:6px;" id="mingguKet"></span>
                </label>
                <div id="mingguCheckboxes" class="iuran-week-grid">
                    {{-- Rendered dynamically by JS based on actual calendar --}}
                </div>
            </div>

            <!-- 5. Perhitungan -->
            <div style="background:var(--abu); border-radius:var(--radius-sm); padding:12px 14px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text3); margin-bottom:4px;">
                    <span>Tarif per Minggu</span>
                    <span class="td-mono">Rp {{ number_format($tarifSampah,0,',','.') }}</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text3); margin-bottom:8px;">
                    <span>Jumlah Minggu</span>
                    <span id="jmlMinggu" class="td-mono">0</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1.5px solid var(--abu2);">
                    <span style="font-weight:700; font-size:13px;">Total Dibayar</span>
                    <span id="totalBayar" class="td-mono" style="font-size:18px; font-weight:800; color:var(--hijau);">Rp 0</span>
                </div>
            </div>

            <!-- 6. Submit -->
            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;" id="btnSimpan" disabled>
                <i class="fas fa-save"></i> Simpan Transaksi
            </button>
        </form>
    </div>

    <!-- KANAN: Status + Jurnal -->
    <div>
        <!-- Toolbar -->
        <div class="toolbar" style="flex-wrap:wrap;">
            <div class="toolbar-left" style="gap:8px; flex-wrap:wrap;">
                <select class="btn btn-outline btn-sm" onchange="window.location.href='?tahun='+this.value+'&bulan={{ $bulan }}'">
                    @for($i = date('Y')+1; $i >= 2024; $i--)
                        <option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
                <select class="btn btn-outline btn-sm" onchange="window.location.href='?tahun={{ $tahun }}&bulan='+this.value">
                    @foreach($bulanAll as $idx => $b)
                        <option value="{{ $idx+1 }}" {{ $bulan == $idx+1 ? 'selected' : '' }}>{{ $bulanNames[$b] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="toolbar-right">
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:11px; color:var(--text3); white-space:nowrap;">Terkumpul:</span>
                    <span class="badge" style="background:var(--hijau-pale); color:var(--hijau); padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; white-space:nowrap;">Rp {{ number_format($totalTerkumpul,0,',','.') }}</span>
                </div>
            </div>
        </div>

        <!-- Status Pembayaran -->
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:14px 18px; background:var(--abu); border-bottom:1.5px solid var(--abu2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="font-weight:700; font-size:14px;"><i class="fas fa-clipboard-check" style="color:var(--hijau); margin-right:6px;"></i>Status Pembayaran · {{ $bulanNama }} {{ $tahun }}</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="tableSearchInput" placeholder="🔍 Cari..." oninput="filterTable(this.value)"
                        style="padding:7px 10px; border:1.5px solid var(--abu2); border-radius:8px; font-size:12px; outline:none; width:120px; max-width:40vw;">
                    <span style="font-size:12px; color:var(--text3);">{{ $keluargas->count() }} KK</span>
                </div>
            </div>
            <div class="data-table-wrapper">
                <table class="data-table" id="statusTable">
                    <thead><tr>
                        <th>No</th><th>Nama KK</th><th style="text-align:center;">RT</th>
                        <th style="text-align:center;">M-1</th><th style="text-align:center;">M-2</th><th style="text-align:center;">M-3</th><th style="text-align:center;">M-4</th><th style="text-align:center;">M-5</th>
                        <th style="text-align:center;">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach($keluargas as $idx => $k)
                        @php
                            $w = isset($iuran[$k->keluarga_id]) ? ($iuran[$k->keluarga_id]->weeks ?? []) : [];
                            $paid = 0;
                            for($m=1;$m<=5;$m++) { if(isset($w[$bulanKey.'-M'.$m]) && $w[$bulanKey.'-M'.$m] === 'lunas') $paid++; }
                        @endphp
                        <tr class="warga-row" data-nama="{{ strtolower($k->nama) }}" data-rt="{{ $k->rt }}">
                            <td>{{ $idx+1 }}</td>
                            <td style="font-weight:600;">{{ $k->nama }}</td>
                            <td style="text-align:center;">{{ $k->rt }}</td>
                            @for($m=1;$m<=5;$m++)
                                <td style="text-align:center;">
                                    @if(isset($w[$bulanKey.'-M'.$m]) && $w[$bulanKey.'-M'.$m] === 'lunas')
                                        <i class="fas fa-check-circle" style="color:var(--hijau); font-size:15px;" title="Lunas M{{ $m }}"></i>
                                    @else
                                        <i class="far fa-circle" style="color:var(--abu3); font-size:13px;" title="Belum M{{ $m }}"></i>
                                    @endif
                                </td>
                            @endfor
                            <td style="text-align:center;">
                                @if($paid >= $totalMingguBulan)
                                    <span class="badge badge-success" style="font-size:10px;">Lunas</span>
                                @elseif($paid > 0)
                                    <span class="badge" style="background:var(--emas-muda); color:var(--emas); font-size:10px; padding:3px 8px; border-radius:4px;">{{ $paid }}/{{ $totalMingguBulan }}</span>
                                @else
                                    <span class="badge badge-danger" style="font-size:10px;">Belum</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Jurnal Transaksi -->
        @if(count($riwayat) > 0)
        <div class="card" style="padding:0; overflow:hidden; margin-top:16px;">
            <div style="padding:14px 18px; background:var(--abu); border-bottom:1.5px solid var(--abu2);">
                <div style="font-weight:700; font-size:14px;"><i class="fas fa-book" style="color:var(--biru); margin-right:6px;"></i>Jurnal Penerimaan · {{ $bulanNama }} {{ $tahun }}</div>
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
.sak-input:focus { border-color:var(--hijau); outline:none; box-shadow:0 0 0 3px rgba(46,125,50,0.1); }
.sak-checkbox-label { display:flex; align-items:center; gap:8px; padding:10px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); cursor:pointer; transition:all 0.2s; font-size:13px; font-weight:600; }
.sak-checkbox-label:hover { border-color:var(--hijau); }
.sak-checkbox-label input { accent-color:var(--hijau); width:16px; height:16px; }
.sak-checkbox-label.checked { border-color:var(--hijau); background:var(--hijau-pale); }
.sak-checkbox-label.disabled { opacity:0.4; pointer-events:none; border-color:var(--hijau); background:var(--hijau-pale); }
</style>

<script>
const tarif = {{ $tarifSampah }};
const bulanKey = '{{ $bulanKey }}';
const iuranData = {!! json_encode($iuran->map(fn($i) => $i->weeks ?? [])) !!};

// ─── Hitung jumlah minggu dalam bulan (bisa 4 atau 5) ───
function weeksInMonth(year, month) {
    // Count distinct week-of-year occurrences in this month
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDay = new Date(year, month - 1, 1).getDay(); // 0=Sun
    return Math.ceil((firstDay + daysInMonth) / 7);
}

// ─── Inisialisasi checkbox minggu sesuai kalender ───
function buildWeekCheckboxes(paid5) {
    const now = new Date();
    const year = {{ $tahun }};
    const month = {{ $bulan }};
    const numWeeks = weeksInMonth(year, month);
    const container = document.getElementById('mingguCheckboxes');
    container.innerHTML = '';
    document.getElementById('mingguKet').textContent = numWeeks + ' minggu di bulan ini';

    for (let m = 1; m <= numWeeks; m++) {
        const key = bulanKey + '-M' + m;
        const isPaid = (paid5 || {})[key] === 'lunas';
        const lbl = document.createElement('label');
        lbl.className = 'sak-checkbox-label' + (isPaid ? ' disabled' : '');
        lbl.id = 'mLabel' + m;
        lbl.innerHTML = `<input type="checkbox" name="minggu[]" value="${m}" onchange="hitungTotal()" ${isPaid?' disabled checked':''}>` +
            `<span>Minggu ke-${m}${isPaid?' ✓':''}</span>`;
        container.appendChild(lbl);
    }
    hitungTotal();
}

// ─── Live-search warga picker ───
const wargaDataEl = document.getElementById('wargaData');
const wargaItems = [...wargaDataEl.querySelectorAll('[data-id]')].map(el => ({
    id: el.dataset.id, nama: el.dataset.nama, rt: el.dataset.rt, hp: el.dataset.hp,
    label: el.textContent.trim()
}));

function filterWarga(q) {
    const dd = document.getElementById('wargaDropdown');
    if (!q.trim()) { dd.style.display = 'none'; document.getElementById('wargaSelect').value = ''; return; }
    const ql = q.toLowerCase();
    const hits = wargaItems.filter(w => w.nama.toLowerCase().includes(ql) || w.rt.includes(q));
    if (!hits.length) { dd.innerHTML = '<div style="padding:12px; font-size:13px; color:var(--text3);">Tidak ditemukan</div>'; dd.style.display='block'; return; }
    dd.innerHTML = hits.slice(0, 50).map(w =>
        `<div onclick="selectWarga('${w.id}','${w.nama.replace(/'/g,"\\'")}',' ${w.rt}','${w.hp}')" style="padding:10px 14px; cursor:pointer; font-size:13px; border-bottom:1px solid var(--abu2);" onmouseover="this.style.background='var(--abu)'" onmouseout="this.style.background='white'">
            <span style="font-weight:600;">${w.nama}</span> <span style="font-size:11px; color:var(--text3);">- RT ${w.rt}</span>
        </div>`
    ).join('');
    dd.style.display = 'block';
}

function selectWarga(id, nama, rt, hp) {
    document.getElementById('wargaSearch').value = nama + ' · RT ' + rt;
    document.getElementById('wargaSelect').value = id;
    document.getElementById('wargaDropdown').style.display = 'none';
    loadWargaStatus();
}

document.addEventListener('click', e => {
    if (!document.getElementById('wargaSearch').contains(e.target))
        document.getElementById('wargaDropdown').style.display = 'none';
});

function loadWargaStatus() {
    const id = document.getElementById('wargaSelect').value;
    const info = document.getElementById('wargaInfo');
    if (!id) { info.style.display = 'none'; document.getElementById('bayarForm').action = ''; buildWeekCheckboxes({}); return; }

    const w = wargaItems.find(x => x.id === id);
    if (w) {
        document.getElementById('infoRT').textContent = w.rt;
        document.getElementById('infoHP').textContent = w.hp || '-';
    }
    document.getElementById('bayarForm').action = '/sampah/bayar/' + id;
    info.style.display = 'block';

    // Status minggu
    const weeks = iuranData[id] || {};
    const numWeeks = weeksInMonth({{ $tahun }}, {{ $bulan }});
    const container = document.getElementById('statusMinggu');
    container.innerHTML = '';
    for (let m = 1; m <= numWeeks; m++) {
        const key = bulanKey + '-M' + m;
        const paid = weeks[key] === 'lunas';
        container.innerHTML += `<div style="display:flex;align-items:center;gap:3px;font-size:11px;">` +
            (paid ? '<i class="fas fa-check-circle" style="color:var(--hijau);"></i>' : '<i class="far fa-circle" style="color:var(--abu3);"></i>') +
            `<span style="color:${paid ? 'var(--hijau)' : 'var(--text3)'}">M${m}</span></div>`;
    }

    buildWeekCheckboxes(weeks);
}

function hitungTotal() {
    const checked = document.querySelectorAll('#mingguCheckboxes input:checked:not(:disabled)');
    const total = checked.length * tarif;
    document.getElementById('jmlMinggu').textContent = checked.length;
    document.getElementById('totalBayar').textContent = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('btnSimpan').disabled = checked.length === 0 || !document.getElementById('wargaSelect').value;
    document.querySelectorAll('#mingguCheckboxes input[type=checkbox]:not(:disabled)').forEach(cb => {
        cb.closest('label').classList.toggle('checked', cb.checked);
    });
}

// ─── Filter tabel kanan ───
function filterTable(q) {
    const ql = q.toLowerCase();
    document.querySelectorAll('#statusTable .warga-row').forEach(row => {
        const nama = (row.dataset.nama || '');
        const rt = (row.dataset.rt || '');
        row.style.display = (!q || nama.includes(ql) || rt.includes(q)) ? '' : 'none';
    });
}

// Init: build week boxes on load
buildWeekCheckboxes({});
</script>
@endsection
