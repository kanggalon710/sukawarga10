@extends('layouts.app')
@section('title', 'Laporan')
@section('page-title', 'Laporan')
@section('page-subtitle', 'Analisis keuangan & demografi · Tahun ' . $tahun)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/bi-report.css') }}">
@endpush

@section('content')
@php
    /* ===== Helper komponen BI (presentation-only, tanpa ubah data) ===== */
    if (!function_exists('biSemClass')) {
        function biSemClass($label) {
            $l = ' ' . strtolower(trim($label)) . ' ';
            $warn = ['non permanen', 'sungai', 'dibakar', 'sembarang', 'tidak punya', 'tidak ada', 'tidak aktif', 'belum', 'tanah', 'bambu', 'kebun'];
            $ok   = ['semi permanen', 'bersama', 'sumur', 'galon', 'tps', 'sebagian', 'numpang', 'kontrak', 'asbes', 'seng', 'semen', 'papan', 'tembok tdk', 'lainnya', 'cukup'];
            $good = ['permanen', 'sendiri', 'septic', 'diangkut petugas', 'pdam', 'bank sampah', 'semua punya', 'milik', 'keramik', 'tembok diplester', 'lpg', 'genteng', 'baja ringan', 'coran', 'rooftop', 'pbi', 'mandiri', 'aktif'];
            foreach ($warn as $w) if (str_contains($l, $w)) return 'warn';
            foreach ($ok as $w) if (str_contains($l, $w)) return 'ok';
            foreach ($good as $w) if (str_contains($l, $w)) return 'good';
            return 'ok';
        }
    }
    if (!function_exists('biRanked')) {
        function biRanked($items, $total, $opts = []) {
            $items = array_filter((array) $items, fn($v, $k) => $k !== '' && $v !== null, ARRAY_FILTER_USE_BOTH);
            if (empty($items)) return '<div class="bi-empty t-meta">Belum ada data tercatat.</div>';
            $mode = $opts['mode'] ?? 'rank';
            $meta = $opts['meta'] ?? [];
            if ($mode === 'sequential' && !empty($opts['order'])) {
                $ordered = [];
                foreach ($opts['order'] as $k) if (array_key_exists($k, $items)) $ordered[$k] = $items[$k];
                foreach ($items as $k => $v) if (!array_key_exists($k, $ordered)) $ordered[$k] = $v;
                $items = $ordered;
            } elseif ($mode !== 'single') {
                arsort($items);
            }
            $max = max($items) ?: 1;
            $i = 0; $html = '<div class="bi-ranked">';
            foreach ($items as $name => $count) {
                $w = round($count / $max * 100, 1);
                $pct = $total > 0 ? round($count / $total * 100) : 0;
                if ($mode === 'semantic')       $fill = 'bi-fill--' . biSemClass($name);
                elseif ($mode === 'sequential') { $seq = ['warn', 'ok', 'rank2', 'rank1']; $fill = 'bi-fill--' . ($seq[$i] ?? 'rank1'); }
                elseif ($mode === 'single')     $fill = $opts['fill'] ?? 'bi-fill--rank2';
                else { $rk = ['rank1', 'rank2', 'rank3']; $fill = 'bi-fill--' . ($rk[$i] ?? 'rest'); }
                $sub = isset($meta[$name]) && $meta[$name] !== '' ? '<span class="bi-row__sub">' . e($meta[$name]) . '</span>' : '';
                $html .= '<div class="bi-row">'
                    . '<div class="bi-row__label"><span class="bi-row__name" title="' . e($name) . '">' . e($name ?: 'Lainnya') . '</span>' . $sub . '</div>'
                    . '<div class="bi-row__track"><div class="bi-row__fill ' . $fill . '" style="width:' . $w . '%"></div></div>'
                    . '<div class="bi-row__val">' . $count . '<small>' . $pct . '%</small></div>'
                    . '</div>';
                $i++;
            }
            return $html . '</div>';
        }
    }
    if (!function_exists('biKpi')) {
        function biKpi($value, $label, $context = '', $tone = 'green', $icon = null, $barPct = null) {
            $h = '<div class="bi-kpi bi-kpi--' . $tone . '">';
            if ($icon) $h .= '<i class="fas ' . $icon . ' bi-kpi__icon" aria-hidden="true"></i>';
            $h .= '<div class="bi-kpi__value t-num">' . $value . '</div>';
            $h .= '<div class="bi-kpi__label">' . e($label) . '</div>';
            if ($context !== '') $h .= '<div class="bi-kpi__context">' . e($context) . '</div>';
            if ($barPct !== null) $h .= '<div class="bi-kpi__bar"><span style="width:' . min(100, $barPct) . '%"></span></div>';
            return $h . '</div>';
        }
    }
    if (!function_exists('biRT')) {
        function biRT($rt) { return 'RT ' . trim(preg_replace('/^\s*RT\s*/i', '', (string) $rt)); }
    }
    if (!function_exists('biHead')) {
        // Kepala kartu bernomor · satu pola untuk semua section
        function biHead($no, $icon, $title, $desc = '') {
            return '<div class="bi-head">'
                . '<span class="bi-head__no">' . $no . '</span>'
                . '<div class="bi-head__body">'
                . '<h2 class="t-section"><i class="fas ' . $icon . ' bi-ico" aria-hidden="true"></i> ' . e($title) . '</h2>'
                . ($desc !== '' ? '<p class="bi-head__desc t-sm">' . e($desc) . '</p>' : '')
                . '</div></div>';
        }
    }
@endphp

@php
    $tabs = [
        'ringkasan'  => ['fa-chart-line',         'Keuangan'],
        'demografi'  => ['fa-users',              'Demografi'],
        'ekonomi'    => ['fa-hand-holding-dollar','Ekonomi'],
        'permukiman' => ['fa-house-chimney',      'Permukiman'],
    ];
@endphp
<div class="bi-toolbar">
    <div class="bi-toolbar__year">
        <select class="btn btn-outline btn-sm" onchange="window.location.href='{{ route('laporan.tab', ['tab' => $tab]) }}?tahun='+this.value" aria-label="Pilih tahun laporan">
            @for($i = date('Y')+1; $i >= 2024; $i--)
                <option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>
            @endfor
        </select>
    </div>
    <nav class="bi-tabs bi-toolbar__tabs" aria-label="Bagian laporan">
        @foreach($tabs as $key => [$ic, $lbl])
        <a href="{{ route('laporan.tab', ['tab' => $key, 'tahun' => $tahun]) }}"
           @if($tab == $key) aria-current="page" @endif>
            <i class="fas {{ $ic }}" aria-hidden="true"></i> {{ $lbl }}
        </a>
        @endforeach
    </nav>
</div>

@if($tab === 'ringkasan')
<div class="laporan-banner">
    <h3>Laporan Keuangan {{ $tahun }}</h3>
    <p>Total pemasukan vs pengeluaran RW 10</p>
    <div class="banner-amount">Rp {{ number_format($totalMasuk - $totalKeluar, 0, ',', '.') }}</div>
    <div class="banner-sub">Saldo bersih (Masuk: {{ number_format($totalMasuk, 0, ',', '.') }} · Keluar: {{ number_format($totalKeluar, 0, ',', '.') }})</div>
</div>
@endif

@if($tab === 'ringkasan')
<div class="card">
    {!! biHead('#', 'fa-ranking-star', 'Ranking Pemasukan per RT', 'Berdasarkan total iuran terkumpul tahun ' . $tahun) !!}
    <div class="bi-block">
        @php $maxR = $rankingRT->max('total') ?: 1; @endphp
        <div class="bi-ranked">
            @forelse($rankingRT as $i => $rt)
            <div class="bi-row">
                <div class="bi-row__label"><span class="bi-row__name">#{{ $i+1 }} · {{ biRT($rt->rt) }}</span></div>
                <div class="bi-row__track"><div class="bi-row__fill {{ $i==0?'bi-fill--rank1':($i==1?'bi-fill--rank2':($i==2?'bi-fill--rank3':'bi-fill--rest')) }}" style="width:{{ round($rt->total/$maxR*100,1) }}%"></div></div>
                <div class="bi-row__val">Rp {{ number_format($rt->total, 0, ',', '.') }}</div>
            </div>
            @empty
            <div class="bi-empty t-meta" style="text-align:center;">Belum ada data transaksi.</div>
            @endforelse
        </div>
    </div>
</div>
@endif

@if($tab === 'ringkasan')
<div class="card">
    {!! biHead('#', 'fa-chart-column', 'Tren Bulanan ' . $tahun, 'Pemasukan vs pengeluaran per bulan · arahkan kursor ke batang untuk nilai persis') !!}
    <div class="bi-block">
        @php $maxB = max(collect($bulanData)->max('masuk'), collect($bulanData)->max('keluar'), 1); @endphp
        <div class="bi-barchart">
            @foreach($bulanData as $b)
            <div class="bi-barchart__col">
                <div class="bi-barchart__bars">
                    <div class="bi-barchart__bar bi-barchart__bar--in" style="height:{{ round($b['masuk']/$maxB*150) }}px;" title="{{ $b['bulan'] }} · Masuk: Rp {{ number_format($b['masuk'],0,',','.') }}"></div>
                    <div class="bi-barchart__bar bi-barchart__bar--out" style="height:{{ round($b['keluar']/$maxB*150) }}px;" title="{{ $b['bulan'] }} · Keluar: Rp {{ number_format($b['keluar'],0,',','.') }}"></div>
                </div>
                <div class="bi-barchart__lbl">{{ $b['bulan'] }}</div>
            </div>
            @endforeach
        </div>
        <div class="bi-legend">
            <span><i style="background:#43a047;"></i> Masuk</span>
            <span><i style="background:#d32f2f;"></i> Keluar</span>
        </div>
    </div>
</div>
@endif

@php $tabsData = ['demografi', 'ekonomi', 'permukiman']; @endphp
@if(in_array($tab, $tabsData) && !empty($demografi))

@php
    // Peta section per tab · tiap halaman fokus, bisa di-bookmark lewat URL-nya sendiri
    $jumpMap = [
        'demografi'  => [['sec-1','fa-users','Populasi'], ['sec-6','fa-chart-simple','Piramida Usia'], ['sec-7','fa-graduation-cap','Pendidikan']],
        'ekonomi'    => [['sec-2','fa-briefcase','Rumah Tangga'], ['sec-5','fa-user-tie','Pekerjaan'], ['sec-4','fa-hand-holding-heart','Bansos']],
        'permukiman' => [['sec-3','fa-house-chimney','Rumah & Sanitasi']],
    ];
    $jump = $jumpMap[$tab] ?? [];
@endphp
@if(count($jump) > 1)
<nav class="bi-jump" aria-label="Loncat ke bagian laporan">
    @foreach($jump as [$id, $ic, $lbl])
    <a href="#{{ $id }}"><i class="fas {{ $ic }}" aria-hidden="true"></i> {{ $lbl }}</a>
    @endforeach
</nav>
@endif

{{-- Banner ringkas --}}
<section class="bi-hero">
    <div class="bi-hero__inner">
        <div class="bi-hero__eyebrow t-label">Profil Demografi</div>
        <h1 class="bi-hero__title t-display"><i class="fas fa-chart-pie" aria-hidden="true"></i> RW 10 Sukakarya</h1>
        <p class="bi-hero__desc t-sm">Data kependudukan, kondisi sosial-ekonomi, dan kesejahteraan masyarakat · dasar perencanaan program warga.</p>
        <div class="bi-hero__stats">
            <div class="bi-stat">
                <div class="bi-stat__val t-display t-num">{{ number_format($demografi['totalKK'], 0, ',', '.') }}</div>
                <div class="bi-stat__lbl t-label">Kepala Keluarga</div>
            </div>
            <div class="bi-stat">
                <div class="bi-stat__val t-display t-num">{{ number_format($demografi['totalJiwa'], 0, ',', '.') }}</div>
                <div class="bi-stat__lbl t-label">Total Jiwa</div>
            </div>
            <div class="bi-stat">
                <div class="bi-stat__val t-title t-num"><i class="fas fa-mars" aria-hidden="true"></i>{{ number_format($demografi['totalLaki'], 0, ',', '.') }}</div>
                <div class="bi-stat__lbl t-label">Laki-laki</div>
            </div>
            <div class="bi-stat">
                <div class="bi-stat__val t-title t-num"><i class="fas fa-venus" aria-hidden="true"></i>{{ number_format($demografi['totalPerempuan'], 0, ',', '.') }}</div>
                <div class="bi-stat__lbl t-label">Perempuan</div>
            </div>
        </div>
    </div>
</section>

{{-- 1. POPULASI --}}
@if($tab === 'demografi')
<div class="card" id="sec-1">
    {!! biHead(1, 'fa-users', 'Populasi & Komposisi per RT', 'Sebaran jumlah jiwa per RT; jumlah KK dan komposisi laki-laki/perempuan tertera di bawah label.') !!}
    <div class="bi-block">
        @php
            $popItems = []; $popMeta = [];
            foreach ($demografi['populasiRT'] as $pop) {
                $key = biRT($pop['rt']);
                $popItems[$key] = $pop['jiwa'];
                $popMeta[$key] = $pop['kk'] . ' KK' . (($pop['laki'] + $pop['perempuan']) > 0 ? ' · L ' . $pop['laki'] . ' / P ' . $pop['perempuan'] : '');
            }
        @endphp
        {!! biRanked($popItems, $demografi['totalJiwa'], ['mode' => 'single', 'fill' => 'bi-fill--rank1', 'meta' => $popMeta]) !!}
    </div>
</div>
@endif

{{-- 2. EKONOMI --}}
@if($tab === 'ekonomi')
<div class="card" id="sec-2">
    {!! biHead(2, 'fa-briefcase', 'Ekonomi & Kesejahteraan', 'Pekerjaan, penghasilan, dan kondisi keuangan keluarga.') !!}
    @php $tk = $demografi['totalKK'] ?: 1; @endphp
    <div class="bi-block">
        <div class="bi-kpi-grid">
            {!! biKpi($demografi['punyaTabungan'], 'Punya Tabungan', round($demografi['punyaTabungan']/$tk*100).'% dari '.$demografi['totalKK'].' KK', 'green', 'fa-piggy-bank', round($demografi['punyaTabungan']/$tk*100)) !!}
            {!! biKpi($demografi['punyaHutang'], 'Punya Hutang Usaha', round($demografi['punyaHutang']/$tk*100).'% dari KK', 'red', 'fa-file-invoice-dollar', round($demografi['punyaHutang']/$tk*100)) !!}
            {!! biKpi($demografi['ikutSampah'], 'Ikut Iuran Sampah', round($demografi['ikutSampah']/$tk*100).'% dari KK', 'blue', 'fa-recycle', round($demografi['ikutSampah']/$tk*100)) !!}
            {!! biKpi($demografi['ikutPadaringan'], 'Ikut Padaringan', round($demografi['ikutPadaringan']/$tk*100).'% dari KK', 'amber', 'fa-utensils', round($demografi['ikutPadaringan']/$tk*100)) !!}
        </div>
    </div>
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-briefcase bi-ico" aria-hidden="true"></i> Mata Pencaharian Kepala Keluarga</h3>
        <p class="bi-block__desc t-meta">Distribusi pekerjaan utama kepala keluarga, urut dari terbanyak.</p>
        {!! biRanked($demografi['pekerjaan']->toArray(), $demografi['totalKK'], ['mode' => 'rank']) !!}
    </div>
    @if($demografi['penghasilan']->count() > 0)
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-wallet bi-ico" aria-hidden="true"></i> Penghasilan per Bulan (Kepala Keluarga)</h3>
        <p class="bi-block__desc t-meta">Rentang penghasilan bulanan kepala keluarga, urut dari terendah ke tertinggi.</p>
        {!! biRanked($demografi['penghasilan']->toArray(), $demografi['totalKK'], ['mode' => 'sequential', 'order' => ['< 1 Juta', '1 - 2.5 Juta', '2.5 - 5 Juta', '> 5 Juta']]) !!}
    </div>
    @endif

    {{-- ── EKONOMI RUMAH TANGGA: seluruh pekerja dalam KK, bukan hanya kepala keluarga ── --}}
    @if(isset($demografi['ekonomiRT']))
    @php $ert = $demografi['ekonomiRT']; @endphp
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-people-roof bi-ico" aria-hidden="true"></i> Ekonomi Rumah Tangga (Seluruh Pekerja dalam KK)</h3>
        <p class="bi-block__desc t-meta">Menghitung semua anggota yang bekerja · bukan hanya kepala keluarga · dari {{ $ert['totalIndividu'] }} jiwa terdata. Estimasi pendapatan memakai titik tengah rentang.</p>
        <div class="bi-kpi-grid" style="margin-bottom:16px;">
            {!! biKpi($ert['totalPekerja'], 'Total Pekerja', 'rata-rata ' . $ert['rataPekerjaPerKK'] . ' pekerja per KK', 'green', 'fa-briefcase') !!}
            {!! biKpi($ert['kkTanpaPekerja'], 'KK Tanpa Pekerja', 'prioritas bantuan & pemberdayaan', 'red', 'fa-user-slash', $demografi['totalKK'] > 0 ? round($ert['kkTanpaPekerja'] / $demografi['totalKK'] * 100) : 0) !!}
            {!! biKpi($ert['bawahGaris'], 'KK di Bawah Garis', 'per kapita < Rp ' . number_format($ert['garis'], 0, ',', '.') . '/bulan', 'red', 'fa-arrow-trend-down', $demografi['totalKK'] > 0 ? round($ert['bawahGaris'] / $demografi['totalKK'] * 100) : 0) !!}
            {!! biKpi($ert['mencariKerja'], 'Mencari Kerja', 'calon program pelatihan/lapangan kerja', 'amber', 'fa-magnifying-glass') !!}
            {!! biKpi($ert['pekerjaTanpaPenghasilan'], 'Pekerja Belum Terdata Gajinya', 'target pendataan lanjutan via MPWA', 'blue', 'fa-clipboard-question') !!}
        </div>

        <h4 class="t-sub" style="margin:4px 0 3px;"><i class="fas fa-users-gear bi-ico" aria-hidden="true"></i> Status Kerja Seluruh Jiwa</h4>
        <p class="bi-block__desc t-meta">Kepala keluarga + seluruh anggota, diklasifikasikan otomatis dari data pekerjaan.</p>
        {!! biRanked($ert['statusKerja'], $ert['totalIndividu'], ['mode' => 'rank']) !!}

        <h4 class="t-sub" style="margin:18px 0 3px;"><i class="fas fa-layer-group bi-ico" aria-hidden="true"></i> Jumlah Pekerja per KK</h4>
        <p class="bi-block__desc t-meta">KK tanpa pekerja adalah kelompok paling rentan secara ekonomi.</p>
        {!! biRanked($ert['pekerjaPerKK'], $demografi['totalKK'], ['mode' => 'sequential', 'order' => ['Tanpa Pekerja', '1 Pekerja', '2 Pekerja', '3+ Pekerja']]) !!}

        <h4 class="t-sub" style="margin:18px 0 3px;"><i class="fas fa-scale-unbalanced bi-ico" aria-hidden="true"></i> Estimasi Pendapatan per Kapita</h4>
        <p class="bi-block__desc t-meta">Total pendapatan rumah tangga (KK + anggota bekerja) dibagi jumlah jiwa. {{ $ert['kkBelumTerdataIncome'] > 0 ? $ert['kkBelumTerdataIncome'] . ' KK belum terdata penghasilannya (tidak dihitung).' : '' }}</p>
        {!! biRanked($ert['perKapita'], max(1, $demografi['totalKK'] - $ert['kkBelumTerdataIncome']), ['mode' => 'sequential', 'order' => ['< Rp 500 rb (di bawah garis)', 'Rp 500 rb · 1 jt', 'Rp 1 · 2 jt', '> Rp 2 jt']]) !!}
    </div>
    @endif
</div>
@endif

{{-- 3. RUMAH & SANITASI --}}
@if($tab === 'permukiman')
<div class="card" id="sec-3">
    {!! biHead(3, 'fa-house-chimney', 'Kondisi Rumah & Sanitasi', 'Status hunian, material bangunan, dan akses sanitasi dasar. Warna menandai kualitas: hijau layak, amber cukup, merah perlu perhatian.') !!}

    <div class="bi-block">
        <div class="bi-kpi-grid">
            @foreach($demografi['statusRumah'] as $status => $count)
            @php
                $pct = $demografi['totalKK'] > 0 ? round($count / $demografi['totalKK'] * 100) : 0;
                $sl = strtolower($status);
                $tone = str_contains($sl,'milik') ? 'green' : (str_contains($sl,'kontrak') ? 'blue' : (str_contains($sl,'numpang') ? 'amber' : 'rest'));
                $ic = str_contains($sl,'milik') ? 'fa-circle-check' : (str_contains($sl,'kontrak') ? 'fa-key' : (str_contains($sl,'numpang') ? 'fa-hand-holding' : 'fa-house'));
            @endphp
            {!! biKpi($count, $status ?: 'Belum Tercatat', $pct.'% dari total', $tone, $ic, $pct) !!}
            @endforeach
            @if($demografi['avgLuas'] > 0)
            {!! biKpi($demografi['avgLuas'].' <span>m²</span>', 'Rata-rata Luas', 'dari '.$demografi['luasCount'].' KK tercatat', 'purple', 'fa-ruler-combined') !!}
            @endif
        </div>
    </div>

    @if(isset($demografi['sertifikat']) && $demografi['sertifikatTotal'] > 0)
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-file-contract bi-ico" aria-hidden="true"></i> Distribusi Sertifikat Tanah</h3>
        <p class="bi-block__desc t-meta">Jenis sertifikat kepemilikan dari {{ $demografi['sertifikatTotal'] }} KK yang tercatat.</p>
        {!! biRanked($demografi['sertifikat']->toArray(), $demografi['sertifikatTotal'], ['mode' => 'rank']) !!}
    </div>
    @endif

    @php
        $matSections = [
            ['key' => 'tipe_bangunan', 'icon' => 'fa-building', 'title' => 'Tipe Bangunan', 'desc' => 'Jenis konstruksi rumah: permanen, semi permanen, atau non permanen.'],
            ['key' => 'lantai', 'icon' => 'fa-square', 'title' => 'Bahan Lantai', 'desc' => 'Material lantai rumah · keramik, semen, atau tanah.'],
            ['key' => 'dinding', 'icon' => 'fa-border-all', 'title' => 'Bahan Dinding', 'desc' => 'Material dinding · tembok, papan, atau bambu.'],
            ['key' => 'atap', 'icon' => 'fa-house-chimney-window', 'title' => 'Bahan Atap', 'desc' => 'Jenis atap · genteng, seng, asbes, dan lainnya.'],
        ];
    @endphp
    @foreach($matSections as $sec)
    @if(!empty($demografi['sanitasi'][$sec['key']]))
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas {{ $sec['icon'] }} bi-ico" aria-hidden="true"></i> {{ $sec['title'] }}</h3>
        <p class="bi-block__desc t-meta">{{ $sec['desc'] }}</p>
        {!! biRanked($demografi['sanitasi'][$sec['key']], $demografi['totalKK'], ['mode' => 'semantic']) !!}
    </div>
    @endif
    @endforeach

    @php
        $sanSections = [
            ['key' => 'air_minum', 'icon' => 'fa-droplet', 'title' => 'Sumber Air Minum', 'desc' => 'Sumber air utama untuk minum · PDAM, sumur, atau galon.'],
            ['key' => 'air_mandi', 'icon' => 'fa-shower', 'title' => 'Sumber Air Mandi & Cuci', 'desc' => 'Sumber air untuk keperluan mandi dan mencuci.'],
            ['key' => 'masak', 'icon' => 'fa-fire-burner', 'title' => 'Sumber Energi Masak', 'desc' => 'Bahan bakar utama memasak · LPG, kayu, atau listrik.'],
            ['key' => 'jamban', 'icon' => 'fa-toilet', 'title' => 'Kepemilikan Jamban', 'desc' => 'Status fasilitas MCK: sendiri, bersama, atau tidak punya.'],
            ['key' => 'tinja', 'icon' => 'fa-water', 'title' => 'Pembuangan Tinja', 'desc' => 'Sistem pembuangan limbah · septic tank atau sungai.'],
            ['key' => 'sampah', 'icon' => 'fa-trash-can', 'title' => 'Cara Buang Sampah', 'desc' => 'Pengelolaan sampah rumah tangga sehari-hari.'],
        ];
    @endphp
    @foreach($sanSections as $sec)
    @if(!empty($demografi['sanitasi'][$sec['key']]))
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas {{ $sec['icon'] }} bi-ico" aria-hidden="true"></i> {{ $sec['title'] }}</h3>
        <p class="bi-block__desc t-meta">{{ $sec['desc'] }}</p>
        {!! biRanked($demografi['sanitasi'][$sec['key']], $demografi['totalKK'], ['mode' => 'semantic']) !!}
    </div>
    @endif
    @endforeach

    @php $hasAnySanitasi = collect($demografi['sanitasi'])->filter(fn($v) => count($v) > 0)->isNotEmpty(); @endphp
    @if(!$hasAnySanitasi)
    <div class="bi-block" style="text-align:center;">
        <i class="fas fa-circle-info" aria-hidden="true" style="color:var(--emas); font-size:var(--fs-xl);"></i>
        <p class="t-sub" style="margin-top:8px;">Data Kondisi Rumah &amp; Sanitasi Belum Terisi</p>
        <p class="t-meta" style="max-width:46ch; margin:6px auto 0;">Akan muncul otomatis setelah warga didata lewat formulir atau setelah Import Data Keluarga.</p>
    </div>
    @endif
</div>
@endif

{{-- 4. BANSOS --}}
@if($tab === 'ekonomi')
<div class="card" id="sec-4">
    {!! biHead(4, 'fa-hand-holding-heart', 'Cakupan Bantuan Sosial', 'Persentase KK penerima bantuan sosial dari total ' . $demografi['bansosTotal'] . ' KK aktif.') !!}
    @php
        $bansosIcons = ['PKH' => 'fa-hands-helping', 'BPNT' => 'fa-basket-shopping', 'BLT' => 'fa-money-bill-wave', 'Rutilahu' => 'fa-hammer', 'KIS PBI' => 'fa-heart-pulse', 'KIP' => 'fa-graduation-cap'];
        $bansosDesc = ['PKH' => 'Program Keluarga Harapan', 'BPNT' => 'Bantuan Pangan Non-Tunai', 'BLT' => 'Bantuan Langsung Tunai', 'Rutilahu' => 'Rumah Tidak Layak Huni', 'KIS PBI' => 'Jaminan Kesehatan PBI', 'KIP' => 'Kartu Indonesia Pintar'];
    @endphp
    <div class="bi-block">
        <div class="bi-kpi-grid">
            @foreach($demografi['bansos'] as $name => $count)
            @php $pct = $demografi['bansosTotal'] > 0 ? round($count / $demografi['bansosTotal'] * 100) : 0; @endphp
            {!! biKpi($count.' <span>KK</span>', $name, ($bansosDesc[$name] ?? '').' · '.$pct.'%', 'teal', $bansosIcons[$name] ?? 'fa-certificate', $pct) !!}
            @endforeach
        </div>
    </div>
    @if($demografi['bpjsTotal'] > 0)
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-id-card bi-ico" aria-hidden="true"></i> Status BPJS Seluruh Anggota</h3>
        <p class="bi-block__desc t-meta">Distribusi kepesertaan BPJS/JKN dari {{ $demografi['bpjsTotal'] }} jiwa tercatat · hijau aktif, merah perlu perhatian.</p>
        {!! biRanked($demografi['bpjs']->toArray(), $demografi['bpjsTotal'], ['mode' => 'semantic']) !!}
    </div>
    @endif
</div>
@endif

{{-- 5. PEKERJAAN ANGGOTA --}}
@if($tab === 'ekonomi' && $demografi['pekerjaanAnggota']->count() > 0)
<div class="card" id="sec-5">
    {!! biHead(5, 'fa-user-tie', 'Pekerjaan Seluruh Anggota', 'Distribusi pekerjaan seluruh anggota keluarga dari ' . $demografi['bpjsTotal'] . ' jiwa tercatat.') !!}
    <div class="bi-block">
        {!! biRanked($demografi['pekerjaanAnggota']->toArray(), $demografi['bpjsTotal'], ['mode' => 'rank']) !!}
    </div>
</div>
@endif

{{-- 6. PIRAMIDA PENDUDUK --}}
@if($tab === 'demografi' && isset($demografi['piramida']) && $demografi['withBirthDate'] > 0)
<div class="card" id="sec-6">
    {!! biHead(6, 'fa-chart-simple', 'Piramida Penduduk & Struktur Usia', 'Analisis kelompok umur dari ' . $demografi['withBirthDate'] . ' jiwa yang memiliki data tanggal lahir.') !!}

    <div class="bi-block">
        <div class="bi-kpi-grid">
            {!! biKpi($demografi['avgAge'], 'Rata-rata Usia', 'tahun', 'red', 'fa-hourglass-half') !!}
            {!! biKpi($demografi['medianAge'], 'Median Usia', 'tahun', 'purple', 'fa-arrow-down-1-9') !!}
            {!! biKpi($demografi['youngestAge'], 'Termuda', 'tahun', 'teal', 'fa-baby') !!}
            {!! biKpi($demografi['oldestAge'], 'Tertua', 'tahun', 'amber', 'fa-user-clock') !!}
            {!! biKpi($demografi['produktif'], 'Usia Produktif', 'rentang 18-59 tahun', 'blue', 'fa-briefcase') !!}
            {!! biKpi($demografi['dependencyRatio'].'<span>%</span>', 'Rasio Dependensi', 'beban tanggungan', 'green', 'fa-scale-balanced') !!}
        </div>
    </div>

    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-layer-group bi-ico" aria-hidden="true"></i> Piramida Penduduk</h3>
        <p class="bi-block__desc t-meta">Distribusi umur dan jenis kelamin · laki-laki ke kiri, perempuan ke kanan, dari sumbu tengah yang simetris.</p>
        @php $maxPyr = 1; foreach ($demografi['piramida'] as $c) $maxPyr = max($maxPyr, $c['L'], $c['P']); @endphp
        <div class="bi-pyr">
            @foreach(array_reverse($demografi['piramida'], true) as $label => $d)
            <div class="bi-pyr__row">
                <div class="bi-pyr__side bi-pyr__side--l">
                    <span class="bi-pyr__num bi-pyr__num--l">{{ $d['L'] }}</span>
                    <div class="bi-pyr__bar bi-pyr__bar--l" style="width:{{ round($d['L']/$maxPyr*100,1) }}%" role="img" aria-label="Laki-laki {{ $label }}: {{ $d['L'] }} jiwa"></div>
                </div>
                <div class="bi-pyr__label">{{ $label }}</div>
                <div class="bi-pyr__side">
                    <div class="bi-pyr__bar bi-pyr__bar--p" style="width:{{ round($d['P']/$maxPyr*100,1) }}%" role="img" aria-label="Perempuan {{ $label }}: {{ $d['P'] }} jiwa"></div>
                    <span class="bi-pyr__num bi-pyr__num--p">{{ $d['P'] }}</span>
                </div>
            </div>
            @endforeach
            <div class="bi-pyr__legend">
                <div><span><i class="fas fa-mars bi-ico" aria-hidden="true"></i> Laki-laki</span></div>
                <div></div>
                <div><span><i class="fas fa-venus bi-ico" aria-hidden="true"></i> Perempuan</span></div>
            </div>
        </div>
    </div>

    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-chart-bar bi-ico" aria-hidden="true"></i> Distribusi Kelompok Umur</h3>
        <p class="bi-block__desc t-meta">Jumlah jiwa per kelompok usia · dasar perencanaan Posyandu, PAUD, dan program lansia.</p>
        @php
            $catDesc = [
                'Balita (0-4)' => 'Sasaran Posyandu & imunisasi', 'Anak-anak (5-11)' => 'Usia sekolah dasar',
                'Remaja (12-17)' => 'Usia SMP-SMA', 'Dewasa Muda (18-25)' => 'Memasuki dunia kerja',
                'Usia Produktif (26-45)' => 'Tulang punggung ekonomi', 'Pra-Lansia (46-59)' => 'Persiapan pensiun',
                'Lansia (60+)' => 'Sasaran Posyandu Lansia',
            ];
            $umurItems = []; $umurMeta = [];
            foreach ($demografi['piramida'] as $label => $d) {
                $umurItems[$label] = $d['total'];
                $umurMeta[$label] = ($catDesc[$label] ?? '') . ' · L ' . $d['L'] . ' / P ' . $d['P'];
            }
        @endphp
        {!! biRanked($umurItems, $demografi['withBirthDate'], ['mode' => 'single', 'fill' => 'bi-fill--rank2', 'meta' => $umurMeta]) !!}
    </div>

    @if(count($demografi['agePerRT']) > 0)
    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-map-location-dot bi-ico" aria-hidden="true"></i> Komposisi Usia per RT</h3>
        <p class="bi-block__desc t-meta">Jumlah balita, anak, remaja, usia produktif, dan lansia di tiap RT · untuk distribusi program.</p>
        <div class="bi-table-wrap">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th scope="col">RT</th>
                        <th scope="col">Balita</th>
                        <th scope="col">Anak</th>
                        <th scope="col">Remaja</th>
                        <th scope="col">Produktif</th>
                        <th scope="col">Lansia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($demografi['agePerRT'] as $rt => $ages)
                    <tr>
                        <td>{{ biRT($rt) }}</td>
                        <td>{!! $ages['balita'] > 0 ? $ages['balita'] : '<span class="bi-zero">-</span>' !!}</td>
                        <td>{!! $ages['anak'] > 0 ? $ages['anak'] : '<span class="bi-zero">-</span>' !!}</td>
                        <td>{!! $ages['remaja'] > 0 ? $ages['remaja'] : '<span class="bi-zero">-</span>' !!}</td>
                        <td>{!! $ages['produktif'] > 0 ? $ages['produktif'] : '<span class="bi-zero">-</span>' !!}</td>
                        <td>{!! $ages['lansia'] > 0 ? $ages['lansia'] : '<span class="bi-zero">-</span>' !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="bi-block bi-note">
        <h3 class="bi-block__title t-sub"><i class="fas fa-lightbulb bi-ico" aria-hidden="true"></i> Interpretasi Data Usia</h3>
        <ul class="t-sm">
            <li><span class="t-strong">Rasio Dependensi {{ $demografi['dependencyRatio'] }}%</span> · setiap 100 penduduk usia produktif (18-59 tahun) menanggung {{ $demografi['dependencyRatio'] }} orang non-produktif (anak dan lansia).</li>
            @if($demografi['piramida']['Balita (0-4)']['total'] > 0)
            <li><span class="t-strong">{{ $demografi['piramida']['Balita (0-4)']['total'] }} Balita</span> · indikator kebutuhan Posyandu aktif, imunisasi, dan pemantauan gizi.</li>
            @endif
            @if($demografi['piramida']['Lansia (60+)']['total'] > 0)
            <li><span class="t-strong">{{ $demografi['piramida']['Lansia (60+)']['total'] }} Lansia</span> · perlu perhatian Posyandu Lansia dan program jaminan hari tua.</li>
            @endif
            @if($demografi['piramida']['Remaja (12-17)']['total'] > 0)
            <li><span class="t-strong">{{ $demografi['piramida']['Remaja (12-17)']['total'] }} Remaja</span> · potensi sasaran Karang Taruna dan pemberdayaan pemuda.</li>
            @endif
        </ul>
    </div>
</div>
@elseif($tab === 'demografi' && isset($demografi['withBirthDate']) && $demografi['withBirthDate'] == 0)
<div class="card" id="sec-6">
    {!! biHead(6, 'fa-chart-simple', 'Piramida Penduduk & Struktur Usia') !!}
    <div class="bi-block" style="text-align:center;">
        <i class="fas fa-calendar-days" aria-hidden="true" style="color:var(--emas); font-size:var(--fs-2xl);"></i>
        <p class="t-sub" style="margin-top:10px;">Data Tanggal Lahir Belum Tersedia</p>
        <p class="t-meta" style="max-width:48ch; margin:6px auto 0;">Piramida dan analisis usia akan aktif setelah data tanggal lahir anggota diisi lewat form pendataan atau Import CSV Anggota.</p>
    </div>
</div>
@endif

{{-- 7. PENDIDIKAN & KESEHATAN --}}
@if($tab === 'demografi')
<div class="card" id="sec-7">
    {!! biHead(7, 'fa-graduation-cap', 'Pendidikan & Kesehatan', 'Tingkat pendidikan, status perkawinan anggota, dan kondisi kesehatan keluarga.') !!}

    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-user-graduate bi-ico" aria-hidden="true"></i> Tingkat Pendidikan Anggota</h3>
        <p class="bi-block__desc t-meta">Distribusi pendidikan terakhir seluruh anggota, urut dari terbanyak.</p>
        {!! biRanked(($demografi['pendidikanAnggota'] ?? collect())->toArray(), $demografi['bpjsTotal'], ['mode' => 'rank']) !!}
    </div>

    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-ring bi-ico" aria-hidden="true"></i> Status Perkawinan</h3>
        <p class="bi-block__desc t-meta">Distribusi status perkawinan anggota keluarga.</p>
        {!! biRanked(($demografi['statusKawin'] ?? collect())->toArray(), $demografi['bpjsTotal'], ['mode' => 'rank']) !!}
    </div>

    <div class="bi-block">
        <h3 class="bi-block__title t-sub"><i class="fas fa-heart-pulse bi-ico" aria-hidden="true"></i> Kondisi Kesehatan Keluarga</h3>
        <p class="bi-block__desc t-meta">Penyakit kronis dan program kesehatan dari {{ $demografi['totalKK'] }} KK terdata.</p>
        <div class="bi-kpi-grid" style="margin-bottom:16px;">
            {!! biKpi($demografi['ikutKB'] ?? 0, 'Keluarga Ikut KB', ($demografi['totalKK'] > 0 ? round(($demografi['ikutKB'] ?? 0) / $demografi['totalKK'] * 100) : 0) . '% dari KK', 'teal', 'fa-pills', $demografi['totalKK'] > 0 ? round(($demografi['ikutKB'] ?? 0) / $demografi['totalKK'] * 100) : 0) !!}
            {!! biKpi($demografi['stunting'] ?? 0, 'KK dengan Balita Stunting', 'perlu intervensi gizi', 'red', 'fa-child', $demografi['totalKK'] > 0 ? round(($demografi['stunting'] ?? 0) / $demografi['totalKK'] * 100) : 0) !!}
        </div>
        {!! biRanked($demografi['penyakitKronis'] ?? [], $demografi['totalKK'], ['mode' => 'rank']) !!}
    </div>
</div>
@endif

@endif
@endsection
