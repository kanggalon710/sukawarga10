@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'RW 10 Sukakarya — ' . \Carbon\Carbon::now()->isoFormat('dddd, D MMMM Y'))

@section('content')
@php
    // === Helper format (closure, aman dari redeclare) ===
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $rpShort = function ($n) {
        $abs = abs($n); $sign = $n < 0 ? '-' : '';
        if ($abs >= 1000000000) return $sign . 'Rp ' . rtrim(rtrim(number_format($abs / 1000000000, 1, ',', '.'), '0'), ',') . ' M';
        if ($abs >= 1000000)    return $sign . 'Rp ' . rtrim(rtrim(number_format($abs / 1000000, 1, ',', '.'), '0'), ',') . ' jt';
        if ($abs >= 1000)       return $sign . 'Rp ' . number_format(round($abs / 1000), 0, ',', '.') . ' rb';
        return $sign . 'Rp ' . number_format($abs, 0, ',', '.');
    };
    $deltaPct = function ($now, $prev) { return $prev > 0 ? round(($now - $prev) / $prev * 100) : null; };
    $rtColor = fn($p) => $p >= 80 ? 'var(--success)' : ($p >= 40 ? 'var(--warning)' : 'var(--danger)');

    $pemasukanDelta   = $deltaPct($pemasukanBulanIni, $pemasukanBulanLalu);
    $pengeluaranDelta = $deltaPct($pengeluaranBulanIni, $pengeluaranBulanLalu);
    $netBulanIni      = $pemasukanBulanIni - $pengeluaranBulanIni;
    $netSemester      = collect($trenKas)->sum('masuk') - collect($trenKas)->sum('keluar');
    $tunggakanPct     = $totalPesertaUnik > 0 ? round($tunggakanDistinct / $totalPesertaUnik * 100) : 0;
    $namaBulanIni     = $namaBulan[$bulanIni] ?? '';

    // Skala sumbu Y chart — dibulatkan ke angka "rapi"
    $maxVal  = max(1, collect($trenKas)->max('masuk'), collect($trenKas)->max('keluar'));
    $niceMax = $maxVal;
    if ($maxVal > 0) { $pow = pow(10, floor(log10($maxVal))); $niceMax = ceil($maxVal / $pow) * $pow; }
@endphp

<div class="dash-page">

    {{-- ======= Baris 1: KPI utama ======= --}}
    <section class="kpi-grid" aria-label="Ringkasan utama">
        <div class="kpi kpi--green">
            <div class="kpi__head">
                <span class="kpi__label">Total KK</span>
                <span class="kpi__icon"><i class="fas fa-house-user" aria-hidden="true"></i></span>
            </div>
            <span class="kpi__value">{{ number_format($totalKK, 0, ',', '.') }}</span>
            <span class="kpi__sub">{{ number_format($totalJiwa, 0, ',', '.') }} jiwa terdaftar</span>
        </div>

        <div class="kpi kpi--blue">
            <div class="kpi__head">
                <span class="kpi__label">Saldo Kas</span>
                <span class="kpi__icon"><i class="fas fa-wallet" aria-hidden="true"></i></span>
            </div>
            <span class="kpi__value is-rp" title="{{ $rp($saldoKumulatif['total']) }}">{{ $rpShort($saldoKumulatif['total']) }}</span>
            <span class="kpi__sub">Akumulatif seluruh kas (lintas tahun)</span>
        </div>

        <div class="kpi kpi--gold">
            <div class="kpi__head">
                <span class="kpi__label">Collection Rate</span>
                <span class="kpi__icon"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
            </div>
            <span class="kpi__value">{{ $collectionRateSampah }}%</span>
            <span class="kpi__sub">{{ $totalLunasSampah }}/{{ $totalPesertaSampah }} KK sampah lunas {{ $bulanKey }}</span>
        </div>

        <div class="kpi kpi--red">
            <div class="kpi__head">
                <span class="kpi__label">Tunggakan</span>
                <span class="kpi__icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
            </div>
            <span class="kpi__value">{{ $tunggakanDistinct }} <span style="font-size:var(--fs-md);color:var(--text-3);font-family:var(--font-body);font-weight:600;">KK</span></span>
            <span class="kpi__sub">{{ $tunggakanPct }}% dari {{ $totalPesertaUnik }} KK peserta iuran</span>
        </div>
    </section>

    {{-- ======= Baris 2: Ringkasan keuangan bulan ini ======= --}}
    <section class="panel" aria-label="Ringkasan keuangan {{ $namaBulanIni }} {{ $tahun }}">
        <div class="panel__head">
            <h2 class="panel__title"><i class="fas fa-money-bill-trend-up" aria-hidden="true" style="color:var(--primary);"></i> Ringkasan Keuangan</h2>
            <span class="panel__meta">{{ $namaBulanIni }} {{ $tahun }}</span>
        </div>
        <div class="finbar">
            <div class="finbar__cell">
                <span class="finbar__label">Pemasukan</span>
                <span class="finbar__val" style="color:var(--success);" title="{{ $rp($pemasukanBulanIni) }}">{{ $rpShort($pemasukanBulanIni) }}</span>
                <span class="finbar__sub">
                    @if($pemasukanDelta === null)
                        <span class="kpi__delta flat">— vs bln lalu</span>
                    @else
                        <span class="kpi__delta {{ $pemasukanDelta >= 0 ? 'up' : 'down' }}"><i class="fas fa-arrow-{{ $pemasukanDelta >= 0 ? 'up' : 'down' }}" aria-hidden="true"></i>{{ abs($pemasukanDelta) }}%</span> vs bln lalu
                    @endif
                </span>
            </div>
            <div class="finbar__cell">
                <span class="finbar__label">Pengeluaran</span>
                <span class="finbar__val" style="color:var(--danger);" title="{{ $rp($pengeluaranBulanIni) }}">{{ $rpShort($pengeluaranBulanIni) }}</span>
                <span class="finbar__sub">
                    @if($pengeluaranDelta === null)
                        <span class="kpi__delta flat">— vs bln lalu</span>
                    @else
                        <span class="kpi__delta flat"><i class="fas fa-arrow-{{ $pengeluaranDelta >= 0 ? 'up' : 'down' }}" aria-hidden="true"></i>{{ abs($pengeluaranDelta) }}%</span> vs bln lalu
                    @endif
                </span>
            </div>
            <div class="finbar__cell">
                <span class="finbar__label">Net Bulan Ini</span>
                <span class="finbar__val" style="color:{{ $netBulanIni >= 0 ? 'var(--success)' : 'var(--danger)' }};" title="{{ $rp($netBulanIni) }}">{{ $netBulanIni >= 0 ? '+' : '' }}{{ $rpShort($netBulanIni) }}</span>
                <span class="finbar__sub">
                    @if($runwayBulan)
                        Runway kas ≈ <b>{{ $runwayBulan }} bulan</b>
                    @else
                        Belum ada data pengeluaran
                    @endif
                </span>
            </div>
        </div>
    </section>

    {{-- ======= Indeks Kesejahteraan & Sosial (untuk keputusan) ======= --}}
    @php
        $tot = max(1, $kesejahteraan['total']);
        $welfareTiles = [
            ['label' => 'KK Tanpa Pekerja',        'icon' => 'fa-user-slash',          'val' => $kesejahteraan['tanpaPekerja'] ?? 0, 'tone' => 'danger', 'desc' => 'tak ada satu pun anggota yang bekerja'],
            ['label' => 'Di Bawah Garis Kemiskinan','icon' => 'fa-arrow-trend-down',   'val' => $kesejahteraan['bawahGaris'] ?? 0,  'tone' => 'danger',  'desc' => 'pendapatan/kapita < Rp ' . number_format(($kesejahteraan['garis'] ?? 500000) / 1000) . ' rb (semua pekerja dihitung)'],
            ['label' => 'Rumah Tidak Layak Huni', 'icon' => 'fa-house-chimney-crack', 'val' => $kesejahteraan['rtlh'],            'tone' => 'danger',  'desc' => 'non-permanen / lantai tanah / dinding tak layak'],
            ['label' => 'Rentan Ekonomi (KK)',     'icon' => 'fa-hand-holding-dollar', 'val' => $kesejahteraan['rentanEkonomi'],   'tone' => 'warning', 'desc' => 'penghasilan KK < 1jt & tanpa tabungan'],
            ['label' => 'Sanitasi Tidak Layak',   'icon' => 'fa-toilet',              'val' => $kesejahteraan['sanitasiTakLayak'],'tone' => 'warning', 'desc' => 'tinja ke sungai / tanpa jamban sendiri'],
            ['label' => 'Akses Air Terlindungi',  'icon' => 'fa-droplet',             'val' => $kesejahteraan['airAman'],         'tone' => 'info',    'desc' => 'PDAM / sumur pompa / sumur gali'],
            ['label' => 'Cakupan BPJS/JKN',        'icon' => 'fa-shield-heart',        'val' => $kesejahteraan['bpjsCov'],         'tone' => 'success', 'desc' => 'KK punya jaminan kesehatan'],
            ['label' => 'Inklusi Keuangan',        'icon' => 'fa-building-columns',    'val' => $kesejahteraan['inklusiKeuangan'], 'tone' => 'success', 'desc' => 'punya akses kredit formal/koperasi'],
        ];
    @endphp
    <section class="panel" aria-label="Indeks kesejahteraan dan sosial">
        <div class="panel__head">
            <h2 class="panel__title"><i class="fas fa-clipboard-check" aria-hidden="true" style="color:var(--primary);"></i> Indeks Kesejahteraan & Sosial</h2>
            <span class="panel__meta">Basis {{ $kesejahteraan['total'] }} KK terdata</span>
        </div>
        <div class="metric-grid">
            @foreach($welfareTiles as $m)
            @php $p = round($m['val'] / $tot * 100); @endphp
            <div class="metric">
                <span class="metric__label"><i class="fas {{ $m['icon'] }}" aria-hidden="true" style="color:var(--{{ $m['tone'] }});"></i> {{ $m['label'] }}</span>
                <div class="metric__val">{{ $m['val'] }} <small>KK · {{ $p }}%</small></div>
                <div class="metric__bar" role="img" aria-label="{{ $m['val'] }} dari {{ $kesejahteraan['total'] }} KK ({{ $p }}%)"><span style="width:{{ $p }}%; background:var(--{{ $m['tone'] }});"></span></div>
                <span class="finbar__sub" style="margin-top:6px; display:block;">{{ $m['desc'] }}</span>
            </div>
            @endforeach
        </div>
        @if($kesejahteraan['total'] == 0)
        <p style="color:var(--text-3); font-size:var(--fs-xs); margin-top:10px;">Belum ada data keluarga. Indeks akan terisi setelah pendataan/import.</p>
        @endif
    </section>

    {{-- ======= Baris 3: Tren Kas (chart bersumbu) + Pembayaran per RT ======= --}}
    <div class="dash-grid-2">

        {{-- Tren Kas --}}
        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title"><i class="fas fa-chart-column" aria-hidden="true" style="color:var(--primary);"></i> Tren Kas {{ $tahun }}</h2>
                <div class="chart__legend">
                    <span><i class="chart__dot" style="background:var(--success);"></i> Masuk</span>
                    <span><i class="chart__dot" style="background:var(--danger);"></i> Keluar</span>
                </div>
            </div>

            <div class="chart">
                <div class="chart__yaxis" aria-hidden="true">
                    <span>{{ $rpShort($niceMax) }}</span>
                    <span>{{ $rpShort($niceMax * 0.75) }}</span>
                    <span>{{ $rpShort($niceMax * 0.5) }}</span>
                    <span>{{ $rpShort($niceMax * 0.25) }}</span>
                    <span>0</span>
                </div>
                <div class="chart__main">
                    <div class="chart__area" role="img" aria-label="Grafik batang tren pemasukan dan pengeluaran kas per bulan tahun {{ $tahun }}. Nilai rinci tersedia pada tabel data di bawah.">
                        <div class="chart__gridline" style="top:0;"></div>
                        <div class="chart__gridline" style="top:25%;"></div>
                        <div class="chart__gridline" style="top:50%;"></div>
                        <div class="chart__gridline" style="top:75%;"></div>
                        <div class="chart__bars">
                            @foreach($trenKas as $t)
                            <div class="chart__group">
                                <div class="chart__bar in" style="height:{{ round($t['masuk'] / $niceMax * 100, 1) }}%;" title="Masuk {{ $t['bulan'] }}: {{ $rp($t['masuk']) }}"></div>
                                <div class="chart__bar out" style="height:{{ round($t['keluar'] / $niceMax * 100, 1) }}%;" title="Keluar {{ $t['bulan'] }}: {{ $rp($t['keluar']) }}"></div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="chart__xaxis" aria-hidden="true">
                        @foreach($trenKas as $t)<span>{{ $t['bulan'] }}</span>@endforeach
                    </div>
                </div>
            </div>

            <div class="dtotal">
                <span>Net Semester {{ $bulanIni <= 6 ? '1 (Jan–Jun)' : '2 (Jul–Des)' }}</span>
                <span class="num" style="color:{{ $netSemester >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $netSemester >= 0 ? '+' : '' }}{{ $rp($netSemester) }}</span>
            </div>

            {{-- Tabel data alternatif (aksesibilitas grafik — WCAG 1.1.1) --}}
            <details class="data-toggle">
                <summary><i class="fas fa-table" aria-hidden="true"></i> Lihat data tabel</summary>
                <table>
                    <thead><tr><th scope="col">Bulan</th><th scope="col">Masuk</th><th scope="col">Keluar</th><th scope="col">Net</th></tr></thead>
                    <tbody>
                        @foreach($trenKas as $t)
                        <tr>
                            <td>{{ $t['bulan'] }}</td>
                            <td>{{ $rp($t['masuk']) }}</td>
                            <td>{{ $rp($t['keluar']) }}</td>
                            <td>{{ $rp($t['masuk'] - $t['keluar']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        {{-- Pembayaran per RT --}}
        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title"><i class="fas fa-location-dot" aria-hidden="true" style="color:var(--primary);"></i> Pembayaran Sampah per RT</h2>
                <span class="panel__meta">{{ $bulanKey }} {{ $tahun }}</span>
            </div>

            @forelse($pembayaranRT as $p)
            <div class="rtbar">
                <span class="rtbar__name">{{ $p['rt'] }}</span>
                <span class="rtbar__track" role="img" aria-label="{{ $p['rt'] }}: {{ $p['persentase'] }} persen lunas, {{ $p['lunas'] }} dari {{ $p['total_kk'] }} KK">
                    <span class="rtbar__fill" style="width:{{ $p['persentase'] }}%; background:{{ $rtColor($p['persentase']) }};"></span>
                </span>
                <span class="rtbar__pct" style="color:{{ $rtColor($p['persentase']) }};">{{ $p['persentase'] }}%</span>
                <span class="rtbar__count">{{ $p['lunas'] }}/{{ $p['total_kk'] }}</span>
            </div>
            @empty
            <div class="dlist" style="text-align:center; padding:30px; color:var(--text-3);">
                <i class="fas fa-clipboard-list" aria-hidden="true" style="font-size:30px; margin-bottom:8px;"></i>
                <p>Belum ada data iuran</p>
            </div>
            @endforelse

            <div class="dtotal">
                <span>Tunggakan</span>
                <span><b style="color:var(--danger);">{{ $tunggakanSampah }}</b> sampah · <b style="color:var(--danger);">{{ $tunggakanPadaringan }}</b> padaringan</span>
            </div>
        </section>
    </div>

    {{-- ======= Baris 4: Demografi + Profil Sosial + Transaksi Terakhir ======= --}}
    <div class="dash-grid-3">

        {{-- Demografi per RT --}}
        <section class="panel">
            <div class="panel__head"><h2 class="panel__title"><i class="fas fa-map-location-dot" aria-hidden="true" style="color:var(--info);"></i> Demografi per RT</h2></div>
            <div class="dlist">
                @foreach($demografiRT as $d)
                <div class="drow">
                    <span class="drow__body"><span class="drow__title">{{ $d['rt'] }}</span></span>
                    <span class="drow__meta" style="display:flex; gap:16px;">
                        <span><i class="fas fa-house" aria-hidden="true"></i> {{ $d['kk'] }} KK</span>
                        <span><i class="fas fa-user" aria-hidden="true"></i> {{ $d['jiwa'] }} jiwa</span>
                    </span>
                </div>
                @endforeach
            </div>
            <div class="dtotal">
                <span>Total</span>
                <span class="num">{{ $totalKK }} KK · {{ $totalJiwa }} jiwa</span>
            </div>
        </section>

        {{-- Profil Sosial --}}
        <section class="panel">
            <div class="panel__head"><h2 class="panel__title"><i class="fas fa-hand-holding-heart" aria-hidden="true" style="color:var(--danger);"></i> Profil Sosial</h2></div>
            <div class="dlist">
                @foreach([
                    ['icon' => 'fa-hand-holding-dollar', 'var' => 'info',    'label' => 'Penerima Bansos',  'val' => $profilSosial['bansos']],
                    ['icon' => 'fa-wheelchair',          'var' => 'danger',  'label' => 'Kelompok Rentan',  'val' => $profilSosial['rentan']],
                    ['icon' => 'fa-store',               'var' => 'warning', 'label' => 'Pelaku UMKM',      'val' => $profilSosial['umkm']],
                    ['icon' => 'fa-house-circle-exclamation', 'var' => 'primary', 'label' => 'Kurang Mampu', 'val' => $profilSosial['kurang_mampu']],
                ] as $s)
                <div class="drow">
                    <span class="drow__icon" style="background:var(--{{ $s['var'] }}-soft, var(--primary-soft)); color:var(--{{ $s['var'] }});">
                        <i class="fas {{ $s['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <span class="drow__body"><span class="drow__title">{{ $s['label'] }}</span></span>
                    <span class="drow__value">{{ $s['val'] }} <span style="font-size:var(--fs-2xs); color:var(--text-3); font-weight:400;">KK</span></span>
                </div>
                @endforeach
            </div>
        </section>

        {{-- Transaksi Terakhir --}}
        <section class="panel">
            <div class="panel__head"><h2 class="panel__title"><i class="fas fa-clock-rotate-left" aria-hidden="true" style="color:var(--info);"></i> Transaksi Terakhir</h2></div>
            <div class="dlist">
                @forelse($recentTrx as $t)
                <div class="drow">
                    <span class="drow__icon" style="background:{{ $t->jenis == 'masuk' ? 'var(--success-soft)' : 'var(--danger-soft)' }}; color:{{ $t->jenis == 'masuk' ? 'var(--success)' : 'var(--danger)' }};">
                        <i class="fas {{ $t->jenis == 'masuk' ? 'fa-arrow-down' : 'fa-arrow-up' }}" aria-hidden="true"></i>
                    </span>
                    <span class="drow__body">
                        <span class="drow__title">{{ \Illuminate\Support\Str::limit($t->keterangan, 28) }}</span>
                        <span class="drow__meta">{{ date('d/m', strtotime($t->tanggal)) }} · {{ ucfirst($t->kas) }}</span>
                    </span>
                    <span class="drow__value" style="color:{{ $t->jenis == 'masuk' ? 'var(--success)' : 'var(--danger)' }};">{{ $t->jenis == 'masuk' ? '+' : '−' }}{{ $rpShort($t->jumlah) }}</span>
                </div>
                @empty
                <div style="text-align:center; padding:24px; color:var(--text-3);">
                    <i class="fas fa-inbox" aria-hidden="true" style="font-size:26px; margin-bottom:8px;"></i>
                    <p style="font-size:var(--fs-xs);">Belum ada transaksi</p>
                </div>
                @endforelse
            </div>
            <a href="{{ route('bukukas.index') }}" style="display:block; text-align:center; font-size:var(--fs-xs); color:var(--primary); font-weight:600; padding-top:12px;">Lihat Semua Transaksi →</a>
        </section>
    </div>

</div>
@endsection
