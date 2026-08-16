<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard Platform - {{ namaAplikasi() }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo-sukawarga-icon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    <style>
        .plat-wrap { max-width: 960px; margin: 0 auto; padding: 20px 16px 64px; }
        .plat-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px;
                    flex-wrap: wrap; padding: 8px 0 16px; }
        .plat-bar .btn { min-height: 44px; }
        .plat-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 12px; margin-bottom: 16px; }
        .plat-kpi .card { padding: 16px; }
        .plat-kpi .angka { font-size: 30px; font-weight: 800; color: var(--hijau, #0f7a4d); }
        .plat-kpi .label { font-size: 12.5px; color: var(--text3, #6b7280); }
        .plat-aksi { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .plat-aksi .btn { min-height: 44px; }
    </style>
</head>
<body style="background:var(--abu, #f4f6f4);">
<main class="plat-wrap">
    <div class="plat-bar">
        <div>
            <h1 style="font-size:22px; margin:0;">{{ namaAplikasi() }} · Dashboard Platform</h1>
            <p style="margin:2px 0 0; font-size:13px; color:var(--text3, #6b7280);">
                {{ auth()->user()->namaLengkap }} · admin platform</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="btn btn-outline">Keluar</button>
        </form>
    </div>

    <section class="plat-kpi" aria-label="Ringkasan platform">
        <div class="card"><div class="angka">{{ $totalDesa }}</div><div class="label">Total desa</div></div>
        <div class="card"><div class="angka">{{ $totalRw }}</div><div class="label">Total RW</div></div>
        <div class="card"><div class="angka">{{ number_format($totalKk, 0, ',', '.') }}</div><div class="label">Total KK aktif</div></div>
        <div class="card"><div class="angka">{{ $totalDomain }}</div><div class="label">Domain terdaftar</div></div>
    </section>

    <div class="plat-aksi">
        <a class="btn btn-primary" href="{{ route('tenant.index') }}"><i class="fas fa-city" aria-hidden="true"></i> Manajemen Desa</a>
        <a class="btn btn-outline" href="{{ route('akun.index') }}"><i class="fas fa-user-shield" aria-hidden="true"></i> Manajemen Akun</a>
        <a class="btn btn-outline" href="{{ route('pembaruan.index') }}"><i class="fas fa-arrows-rotate" aria-hidden="true"></i> Pembaruan Sistem</a>
    </div>

    @forelse($daftarDesa as $desa)
    <section class="card" style="padding:0; overflow:hidden; margin-bottom:14px;" aria-label="{{ $desa->name }}">
        <div class="card-header" style="padding:14px 16px; margin:0;">
            <div class="card-title">{{ $desa->name }}
                <span style="font-weight:400; font-size:12px; color:var(--text3, #6b7280);">
                    · {{ $desa->children->count() }} RW
                    @php($alamatDesa = $desa->domains->sortByDesc('is_primary')->first()?->hostname)
                    @if($alamatDesa) · <a href="https://{{ $alamatDesa }}">{{ $alamatDesa }}</a> @endif
                </span>
            </div>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>RW</th><th>KK aktif</th><th>Alamat portal</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($desa->children as $rw)
                    <tr>
                        <td>{{ $rw->name }}</td>
                        <td>{{ $jumlahKkPerOrg[$rw->id] ?? 0 }}</td>
                        <td>
                            @php($alamat = $rw->domains->sortByDesc('is_primary')->first()?->hostname)
                            @if($alamat)<a href="https://{{ $alamat }}">{{ $alamat }}</a>@else -@endif
                        </td>
                        <td>{{ $rw->status ?? 'aktif' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @empty
    <p class="card" style="text-align:center; padding:32px;">Belum ada desa - buka lewat Manajemen Desa.</p>
    @endforelse
</main>
</body>
</html>
