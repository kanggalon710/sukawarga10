@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', implode(' · ', array_filter([trim(namaRw().' '.namaDesa()), \Carbon\Carbon::now()->isoFormat('dddd, D MMMM Y')])))

@push('styles')
<link rel="stylesheet" href="{{ asset('css/bi-report.css') }}">
@endpush

@section('content')
@php
    /* ===== Helper format (closure, aman dari redeclare) ===== */
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $rpShort = function ($n) {
        $abs = abs($n); $sign = $n < 0 ? '-' : '';
        if ($abs >= 1000000000) return $sign . 'Rp ' . rtrim(rtrim(number_format($abs / 1000000000, 1, ',', '.'), '0'), ',') . ' M';
        if ($abs >= 1000000)    return $sign . 'Rp ' . rtrim(rtrim(number_format($abs / 1000000, 1, ',', '.'), '0'), ',') . ' jt';
        if ($abs >= 1000)       return $sign . 'Rp ' . number_format(round($abs / 1000), 0, ',', '.') . ' rb';
        return $sign . 'Rp ' . number_format($abs, 0, ',', '.');
    };
    $deltaPct = fn($now, $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100) : null;
    $rtTone = fn($p) => $p >= 80 ? 'good' : ($p >= 40 ? 'ok' : 'warn');

    $pemasukanDelta   = $deltaPct($pemasukanBulanIni, $pemasukanBulanLalu);
    $pengeluaranDelta = $deltaPct($pengeluaranBulanIni, $pengeluaranBulanLalu);
    $netBulanIni      = $pemasukanBulanIni - $pengeluaranBulanIni;
    $netSemester      = collect($trenKas)->sum('masuk') - collect($trenKas)->sum('keluar');
    $tunggakanPct     = $totalPesertaUnik > 0 ? round($tunggakanDistinct / $totalPesertaUnik * 100) : 0;
    $namaBulanIni     = $namaBulan[$bulanIni] ?? '';

    // Skala sumbu Y chart, dibulatkan ke angka rapi
    $maxVal  = max(1, collect($trenKas)->max('masuk'), collect($trenKas)->max('keluar'));
    $niceMax = $maxVal;
    if ($maxVal > 0) { $pow = pow(10, floor(log10($maxVal))); $niceMax = ceil($maxVal / $pow) * $pow; }

    /* Kepala panel, satu pola dengan halaman Laporan */
    if (!function_exists('dashHead')) {
        function dashHead($icon, $title, $meta = '', $tone = 'var(--bi-rank1a)') {
            return '<div class="bi-head" style="align-items:center;">'
                . '<div class="bi-head__body"><h2 class="t-section" style="display:flex;align-items:center;gap:9px;margin:0;">'
                . '<i class="fas ' . $icon . ' bi-ico" aria-hidden="true" style="color:' . $tone . ';font-size:var(--fs-md);"></i> ' . e($title)
                . '</h2></div>'
                . ($meta !== '' ? '<span class="t-meta">' . e($meta) . '</span>' : '')
                . '</div>';
        }
    }
@endphp

<div class="bi-page">

    {{-- ═══ Angka pokok. Tanpa kartu & ikon dekoratif: angka yang bicara. ═══ --}}
    <section class="bi-stats" aria-label="Angka pokok">
        <div class="bi-stat-cell">
            <div class="bi-stat-cell__val">{{ number_format($totalKK, 0, ',', '.') }}<small>KK</small></div>
            <div class="bi-stat-cell__lbl">Kepala Keluarga</div>
            <div class="bi-stat-cell__ctx">{{ number_format($totalJiwa, 0, ',', '.') }} jiwa terdata</div>
        </div>
        <div class="bi-stat-cell">
            <div class="bi-stat-cell__val" title="{{ $rp($saldoKumulatif['total']) }}">{{ $rpShort($saldoKumulatif['total']) }}</div>
            <div class="bi-stat-cell__lbl">Saldo Kas</div>
            <div class="bi-stat-cell__ctx">
                @if($runwayBulan)
                    cukup untuk <span class="t-strong">{{ $runwayBulan }} bulan</span> operasional
                @else
                    akumulatif lintas tahun
                @endif
            </div>
        </div>
        <div class="bi-stat-cell">
            <div class="bi-stat-cell__val">{{ $collectionRateSampah }}<small>%</small></div>
            <div class="bi-stat-cell__lbl">Tingkat Penagihan</div>
            <div class="bi-stat-cell__ctx">
                {{ $totalLunasSampah }}/{{ $totalPesertaSampah }} KK lunas {{ $bulanKey }}
                @php $gapTarget = 80 - $collectionRateSampah; @endphp
                @if($gapTarget > 0)
                    <span class="bi-delta bi-delta--down">{{ $gapTarget }} poin di bawah target</span>
                @else
                    <span class="bi-delta bi-delta--up">di atas target 80%</span>
                @endif
            </div>
        </div>
        <div class="bi-stat-cell">
            <div class="bi-stat-cell__val" style="color:{{ $tunggakanPct > 30 ? '#b3261e' : 'var(--bi-ink)' }};">{{ $tunggakanDistinct }}<small>KK</small></div>
            <div class="bi-stat-cell__lbl">Menunggak</div>
            <div class="bi-stat-cell__ctx">{{ $tunggakanPct }}% dari {{ $totalPesertaUnik }} KK peserta iuran</div>
        </div>
    </section>

    {{-- ═══ Perlu Tindakan + Cakupan Layanan ═══
         Masalah (exception) dipisah dari cakupan, diurut keparahan.
         Masalah butuh tindakan; cakupan butuh pembanding target. --}}
    @php
        $tot = max(1, $kesejahteraan['total']);
        $pct = fn($v) => (int) round($v / $tot * 100);

        // Masalah: urut jumlah terbanyak lebih dulu, keparahan menentukan warna
        $masalah = [
            ['nama' => 'KK tanpa pekerja',           'n' => $kesejahteraan['tanpaPekerja'] ?? 0, 'sev' => 'tinggi', 'why' => 'tidak ada satu pun anggota yang bekerja, prioritas bantuan'],
            ['nama' => 'Di bawah garis kemiskinan',  'n' => $kesejahteraan['bawahGaris'] ?? 0,   'sev' => 'tinggi', 'why' => 'pendapatan per kapita di bawah Rp ' . number_format(($kesejahteraan['garis'] ?? 500000) / 1000) . ' rb per bulan'],
            ['nama' => 'Rumah tidak layak huni',     'n' => $kesejahteraan['rtlh'],              'sev' => 'tinggi', 'why' => 'kandidat usulan Rutilahu'],
            ['nama' => 'Sanitasi tidak layak',       'n' => $kesejahteraan['sanitasiTakLayak'],  'sev' => 'sedang', 'why' => 'tinja ke sungai atau tanpa jamban sendiri'],
            ['nama' => 'Rentan ekonomi',             'n' => $kesejahteraan['rentanEkonomi'],     'sev' => 'sedang', 'why' => 'penghasilan KK di bawah 1 juta dan tanpa tabungan'],
        ];
        $sevRank = ['tinggi' => 0, 'sedang' => 1, 'rendah' => 2];
        usort($masalah, fn($a, $b) => [$sevRank[$a['sev']], -$a['n']] <=> [$sevRank[$b['sev']], -$b['n']]);
        $totalTerdampak = array_sum(array_column($masalah, 'n'));

        // Cakupan: setiap indikator punya target eksplisit
        $cakupan = [
            ['nama' => 'Jaminan kesehatan (BPJS/JKN)', 'n' => $kesejahteraan['bpjsCov'],        'target' => 100, 'sub' => 'target cakupan semesta'],
            ['nama' => 'Akses air terlindungi',        'n' => $kesejahteraan['airAman'],        'target' => 100, 'sub' => 'PDAM, sumur pompa, atau sumur gali'],
            ['nama' => 'Inklusi keuangan',             'n' => $kesejahteraan['inklusiKeuangan'],'target' => 50,  'sub' => 'akses kredit formal atau koperasi'],
        ];
    @endphp
    <div class="bi-grid-2">
        <section class="bi-card" aria-label="Kelompok warga yang perlu tindakan">
            {!! dashHead('fa-triangle-exclamation', 'Perlu Tindakan', $totalTerdampak . ' catatan dari ' . $kesejahteraan['total'] . ' KK', '#d64545') !!}
            <div class="bi-block">
                @if($kesejahteraan['total'] == 0)
                    <p class="t-meta">Belum ada data keluarga. Daftar ini terisi setelah pendataan atau import.</p>
                @else
                    @foreach($masalah as $m)
                    <div class="bi-issue">
                        <span class="bi-issue__sev bi-issue__sev--{{ $m['sev'] }}" role="img" aria-label="Keparahan {{ $m['sev'] }}"></span>
                        <span class="bi-issue__body">
                            <span class="bi-issue__name">{{ $m['nama'] }}</span>
                            <span class="bi-issue__why">{{ $m['why'] }}</span>
                        </span>
                        <span class="bi-issue__num">
                            <span class="bi-issue__count">{{ $m['n'] }}</span>
                            <span class="bi-issue__pct">{{ $pct($m['n']) }}% dari KK</span>
                        </span>
                    </div>
                    @endforeach
                    <div class="bi-total">
                        <span class="t-meta">Urut berdasarkan keparahan, lalu jumlah terbanyak</span>
                        <a href="{{ route('laporan.tab', ['tab' => 'ekonomi']) }}" class="t-sm" style="color:var(--bi-rank1a); font-weight:600;">Rincian ekonomi</a>
                    </div>
                @endif
            </div>
        </section>

        <section class="bi-card" aria-label="Cakupan layanan dasar">
            {!! dashHead('fa-shield-heart', 'Cakupan Layanan Dasar', 'terhadap target', '#2e7d50') !!}
            <div class="bi-block">
                @if($kesejahteraan['total'] == 0)
                    <p class="t-meta">Belum ada data keluarga.</p>
                @else
                    @foreach($cakupan as $c)
                    @php $p = $pct($c['n']); $capai = $p >= $c['target']; @endphp
                    <div class="bi-cov">
                        <span>
                            <span class="bi-cov__name">{{ $c['nama'] }}</span>
                            <span class="bi-cov__sub">{{ $c['n'] }} KK · {{ $c['sub'] }}</span>
                        </span>
                        <span class="bi-cov__track" role="img" aria-label="{{ $p }} persen, target {{ $c['target'] }} persen">
                            <span class="bi-cov__fill {{ $capai ? '' : 'bi-cov__fill--low' }}" style="width:{{ min(100, $p) }}%"></span>
                            <span class="bi-cov__target" style="left:{{ min(100, $c['target']) }}%" title="Target {{ $c['target'] }}%"></span>
                        </span>
                        <span class="bi-cov__val" style="color:{{ $capai ? 'var(--bi-good-a)' : 'var(--bi-ok)' }};">{{ $p }}%</span>
                    </div>
                    @endforeach
                    <div class="bi-cov-legend"><i></i> Garis vertikal menandai target tiap indikator</div>
                @endif
            </div>
        </section>
    </div>

    {{-- ═══ Tren Kas + Pembayaran per RT ═══ --}}
    <div class="bi-grid-2">

        <section class="bi-card">
            @php
                $arusCtx = $netBulanIni >= 0 ? '+' : '';
                $arusCtx .= $rpShort($netBulanIni) . ' bulan ' . $namaBulanIni;
            @endphp
            {!! dashHead('fa-chart-column', 'Tren Kas ' . $tahun, $arusCtx) !!}
            <div class="bi-block">
                <div class="bi-legend" style="justify-content:flex-start; margin:0 0 14px;">
                    <span><i style="background:#43a047;"></i> Masuk</span>
                    <span><i style="background:#d32f2f;"></i> Keluar</span>
                </div>
                <div class="bi-chart">
                    <div class="bi-chart__y" aria-hidden="true">
                        <span>{{ $rpShort($niceMax) }}</span>
                        <span>{{ $rpShort($niceMax * 0.75) }}</span>
                        <span>{{ $rpShort($niceMax * 0.5) }}</span>
                        <span>{{ $rpShort($niceMax * 0.25) }}</span>
                        <span>0</span>
                    </div>
                    <div class="bi-chart__main">
                        <div class="bi-chart__area" role="img" aria-label="Grafik batang tren pemasukan dan pengeluaran kas per bulan tahun {{ $tahun }}. Nilai rinci ada pada tabel data di bawah.">
                            <div class="bi-chart__grid" style="top:0;"></div>
                            <div class="bi-chart__grid" style="top:25%;"></div>
                            <div class="bi-chart__grid" style="top:50%;"></div>
                            <div class="bi-chart__grid" style="top:75%;"></div>
                            <div class="bi-chart__bars">
                                @foreach($trenKas as $t)
                                <div class="bi-chart__group">
                                    <div class="bi-chart__bar bi-chart__bar--in" style="height:{{ round($t['masuk'] / $niceMax * 100, 1) }}%;" title="Masuk {{ $t['bulan'] }}: {{ $rp($t['masuk']) }}"></div>
                                    <div class="bi-chart__bar bi-chart__bar--out" style="height:{{ round($t['keluar'] / $niceMax * 100, 1) }}%;" title="Keluar {{ $t['bulan'] }}: {{ $rp($t['keluar']) }}"></div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="bi-chart__x" aria-hidden="true">
                            @foreach($trenKas as $t)<span>{{ $t['bulan'] }}</span>@endforeach
                        </div>
                    </div>
                </div>

                <div class="bi-total">
                    <span class="t-sm t-strong">Net Semester {{ $bulanIni <= 6 ? '1 (Jan-Jun)' : '2 (Jul-Des)' }}</span>
                    <span class="t-num t-strong" style="color:{{ $netSemester >= 0 ? 'var(--bi-good-a)' : '#b3261e' }};">{{ $netSemester >= 0 ? '+' : '' }}{{ $rp($netSemester) }}</span>
                </div>

                {{-- Tabel alternatif untuk pembaca layar (WCAG 1.1.1) --}}
                <details class="bi-data-toggle">
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
            </div>
        </section>

        <section class="bi-card">
            {!! dashHead('fa-location-dot', 'Pembayaran Sampah per RT', $bulanKey . ' ' . $tahun) !!}
            <div class="bi-block">
                <div class="bi-ranked">
                    @forelse($pembayaranRT as $p)
                    <div class="bi-row">
                        <div class="bi-row__label"><span class="bi-row__name">{{ $p['rt'] }}</span><span class="bi-row__sub">{{ $p['lunas'] }} dari {{ $p['total_kk'] }} KK</span></div>
                        <div class="bi-row__track" role="img" aria-label="{{ $p['rt'] }}: {{ $p['persentase'] }} persen lunas, {{ $p['lunas'] }} dari {{ $p['total_kk'] }} KK">
                            <div class="bi-row__fill bi-fill--{{ $rtTone($p['persentase']) }}" style="width:{{ $p['persentase'] }}%"></div>
                        </div>
                        <div class="bi-row__val">{{ $p['persentase'] }}%</div>
                    </div>
                    @empty
                    <div class="bi-empty t-meta" style="text-align:center; padding:26px 0;">
                        <i class="fas fa-clipboard-list" aria-hidden="true" style="font-size:var(--fs-xl); display:block; margin-bottom:8px; opacity:.4;"></i>
                        Belum ada data iuran
                    </div>
                    @endforelse
                </div>
                <div class="bi-total">
                    <span class="t-sm t-strong">Tunggakan</span>
                    <span class="t-sm"><span class="t-num t-strong" style="color:#b3261e;">{{ $tunggakanSampah }}</span> sampah · <span class="t-num t-strong" style="color:#b3261e;">{{ $tunggakanPadaringan }}</span> padaringan</span>
                </div>
            </div>
        </section>
    </div>

    {{-- ═══ Demografi + Profil Sosial + Transaksi ═══ --}}
    <div class="bi-grid-3">

        <section class="bi-card">
            {!! dashHead('fa-map-location-dot', 'Demografi per RT', '', '#1e88e5') !!}
            <div class="bi-block">
                <div class="bi-list">
                    @foreach($demografiRT as $d)
                    <div class="bi-drow">
                        <span class="bi-drow__body"><span class="bi-drow__title">{{ $d['rt'] }}</span></span>
                        <span class="bi-drow__meta" style="display:flex; gap:14px;">
                            <span><i class="fas fa-house bi-ico" aria-hidden="true"></i> {{ $d['kk'] }} KK</span>
                            <span><i class="fas fa-user bi-ico" aria-hidden="true"></i> {{ $d['jiwa'] }} jiwa</span>
                        </span>
                    </div>
                    @endforeach
                </div>
                <div class="bi-total">
                    <span class="t-sm t-strong">Total</span>
                    <span class="t-num t-strong">{{ $totalKK }} KK · {{ $totalJiwa }} jiwa</span>
                </div>
            </div>
        </section>

        <section class="bi-card">
            {!! dashHead('fa-hand-holding-heart', 'Profil Sosial', '', '#e57373') !!}
            <div class="bi-block">
                <div class="bi-list">
                    @foreach([
                        ['icon' => 'fa-hand-holding-dollar',      'c' => '#1e88e5', 'bg' => '#e8f2fd', 'label' => 'Penerima Bansos', 'val' => $profilSosial['bansos']],
                        ['icon' => 'fa-wheelchair',               'c' => '#e57373', 'bg' => '#fdecea', 'label' => 'Kelompok Rentan', 'val' => $profilSosial['rentan']],
                        ['icon' => 'fa-store',                    'c' => '#d97706', 'bg' => '#fef3e2', 'label' => 'Pelaku UMKM',     'val' => $profilSosial['umkm']],
                        ['icon' => 'fa-house-circle-exclamation', 'c' => '#2e7d50', 'bg' => '#e9f6ef', 'label' => 'Kurang Mampu',    'val' => $profilSosial['kurang_mampu']],
                    ] as $s)
                    <div class="bi-drow">
                        <span class="bi-drow__icon" style="background:{{ $s['bg'] }}; color:{{ $s['c'] }};"><i class="fas {{ $s['icon'] }}" aria-hidden="true"></i></span>
                        <span class="bi-drow__body"><span class="bi-drow__title">{{ $s['label'] }}</span></span>
                        <span class="bi-drow__val">{{ $s['val'] }} <span class="t-meta">KK</span></span>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bi-card">
            {!! dashHead('fa-clock-rotate-left', 'Transaksi Terakhir', '', '#1e88e5') !!}
            <div class="bi-block">
                <div class="bi-list">
                    @forelse($recentTrx as $t)
                    <div class="bi-drow">
                        <span class="bi-drow__icon" style="background:{{ $t->jenis == 'masuk' ? '#e9f6ef' : '#fdecea' }}; color:{{ $t->jenis == 'masuk' ? '#2e7d50' : '#b3261e' }};">
                            <i class="fas {{ $t->jenis == 'masuk' ? 'fa-arrow-down' : 'fa-arrow-up' }}" aria-hidden="true"></i>
                        </span>
                        <span class="bi-drow__body">
                            <span class="bi-drow__title">{{ \Illuminate\Support\Str::limit($t->keterangan, 28) }}</span>
                            <span class="bi-drow__meta">{{ date('d/m', strtotime($t->tanggal)) }} · {{ ucfirst($t->kas) }}</span>
                        </span>
                        <span class="bi-drow__val" style="color:{{ $t->jenis == 'masuk' ? '#2e7d50' : '#b3261e' }};">{{ $t->jenis == 'masuk' ? '+' : '-' }}{{ $rpShort($t->jumlah) }}</span>
                    </div>
                    @empty
                    <div class="bi-empty t-meta" style="text-align:center; padding:22px 0;">
                        <i class="fas fa-inbox" aria-hidden="true" style="font-size:var(--fs-lg); display:block; margin-bottom:8px; opacity:.4;"></i>
                        Belum ada transaksi
                    </div>
                    @endforelse
                </div>
                <a href="{{ route('bukukas.index') }}" class="t-sm" style="display:block; text-align:center; color:var(--bi-rank1a); font-weight:600; padding-top:12px;">Lihat semua transaksi</a>
            </div>
        </section>
    </div>

</div>
@endsection
