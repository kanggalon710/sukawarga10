<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $desa->name }} - {{ namaAplikasi() }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo-sukawarga-icon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    <meta name="description" content="Daftar portal RW {{ $desa->name }}.">
    <style>
        .beranda-wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px 64px; }
        .beranda-kop { text-align: center; padding: 24px 0 8px; }
        .beranda-kop img { width: 96px; height: auto; }
        .beranda-kop h1 { font-size: 24px; margin: 12px 0 4px; }
        .beranda-kop p { color: var(--text3, #6b7280); margin: 0; font-size: 14px; }
        .kartu-rw { display: flex; align-items: center; justify-content: space-between; gap: 12px;
                    padding: 16px; margin-top: 12px; flex-wrap: wrap; }
        .kartu-rw .btn { min-height: 44px; }
        .status-nonaktif { color: var(--merah, #b3382e); font-size: 12.5px; font-weight: 700; }
    </style>
</head>
<body style="background:var(--abu, #f4f6f4);">
<main class="beranda-wrap">
    <header class="beranda-kop">
        <img src="{{ asset('logo-sukawarga-icon.svg') }}" alt="">
        <h1>{{ $desa->name }}</h1>
        <p>Warga: buka portal RW masing-masing untuk masuk.</p>
    </header>

    <section aria-label="Daftar portal RW">
        @forelse($daftarRw as $rw)
            @php($alamat = $rw->domains->sortByDesc('is_primary')->first()?->hostname)
            <article class="card kartu-rw">
                <div>
                    <strong style="font-size:16px;">{{ $rw->name }}</strong>
                    <div style="font-size:13px; color:var(--text3, #6b7280);">
                        {{ $jumlahKk[$rw->id] ?? 0 }} KK terdaftar
                        @if(($rw->status ?? 'aktif') !== 'aktif')
                            · <span class="status-nonaktif">nonaktif</span>
                        @endif
                    </div>
                </div>
                @if($alamat && ($rw->status ?? 'aktif') === 'aktif')
                    <a class="btn btn-primary" href="https://{{ $alamat }}">
                        Buka Portal {{ $rw->name }}
                    </a>
                @endif
            </article>
        @empty
            <p class="card" style="text-align:center; padding:32px;">Belum ada RW terdaftar.</p>
        @endforelse
    </section>
</main>
</body>
</html>
