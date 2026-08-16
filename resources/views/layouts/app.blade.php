<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Portal Warga') - {{ namaAplikasi() }}</title>

    {{-- Identitas & ikon aplikasi --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo-sukawarga-icon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#0f7a4d">
    <meta name="description" content="Sistem informasi & keuangan komunitas {{ namaAplikasi() }}, {{ lokasiSingkat() }}.">
    <meta property="og:title" content="{{ namaAplikasi() }} · {{ lokasiSingkat() }}">
    <meta property="og:description" content="Data warga, iuran, dan laporan demografi {{ namaAplikasi() }}, {{ lokasiSingkat() }}.">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Ilustrasi permukiman {{ namaAplikasi() }} di kaki pegunungan Garut">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ namaAplikasi() }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @stack('styles')
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="{{ asset('logo-sukawarga-icon.svg') }}" alt="Logo" class="sidebar-logo-icon">
                    <div>
                        <span class="sidebar-logo-title">{{ namaAplikasi() }}</span>
                        <span class="sidebar-logo-subtitle">Billing System</span>
                    </div>
                </div>
            </div>

            <div class="sidebar-rw-badge">
                <div class="rw-label">KELURAHAN SUKAKARYA</div>
                <div class="rw-name">RW 10 &bull; Tarogong Kidul</div>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section-label">MENU UTAMA</div>
                @if(userCan('dashboard'))
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home nav-icon"></i> Dashboard
                </a>
                @endif
                @if(auth()->user()->levelEfektif() === 'warga')
                <a href="{{ route('profil.index') }}" class="nav-item {{ request()->routeIs('profil.*') ? 'active' : '' }}">
                    <i class="fas fa-user-circle nav-icon"></i> Profil Saya
                </a>
                @endif
                @if(userCan('warga'))
                <a href="{{ route('warga.index') }}" class="nav-item {{ request()->routeIs('warga.*') ? 'active' : '' }}">
                    <i class="fas fa-users nav-icon"></i> Data Warga
                </a>
                @endif
                @if(userCan('sampah'))
                <a href="{{ route('billing.sampah') }}" class="nav-item {{ request()->routeIs('billing.sampah') ? 'active' : '' }}">
                    <i class="fas fa-truck-moving nav-icon"></i> Iuran Sampah
                </a>
                @endif
                @if(userCan('padaringan'))
                <a href="{{ route('billing.padaringan') }}" class="nav-item {{ request()->routeIs('billing.padaringan') ? 'active' : '' }}">
                    <i class="fas fa-hand-holding-heart nav-icon"></i> Iuran Padaringan
                </a>
                @endif
                @if(userCan('laporan'))
                <a href="{{ route('laporan.tab', ['tab' => 'ringkasan']) }}" class="nav-item {{ request()->routeIs('laporan.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar nav-icon"></i> Laporan
                </a>
                @endif
                @if(userCan('surat'))
                <a href="{{ route('surat.index') }}" class="nav-item {{ request()->routeIs('surat.*') ? 'active' : '' }}">
                    <i class="fas fa-envelope-open-text nav-icon"></i> Surat Menyurat
                </a>
                @endif
                @if(userCan('pendaftaran'))
                <a href="{{ route('pendaftaran.index') }}" class="nav-item {{ request()->routeIs('pendaftaran.*') ? 'active' : '' }}" style="position:relative;">
                    <i class="fas fa-user-clock nav-icon"></i> Pendaftaran
                    @if(($pendaftaranPending ?? 0) > 0)
                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:var(--merah, #e53e3e); color:white; font-size:10px; font-weight:700; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center;" aria-label="{{ $pendaftaranPending }} pendaftaran menunggu verifikasi">{{ $pendaftaranPending }}</span>
                    @endif
                </a>
                @endif
                @if(userCan('umkm'))
                <a href="{{ route('umkm.index') }}" class="nav-item {{ request()->routeIs('umkm.*') ? 'active' : '' }}">
                    <i class="fas fa-store nav-icon"></i> UMKM Warga
                </a>
                @endif

                @if(userCan('bukukas') || userCan('pengeluaran') || userCan('sumbangan'))
                <div class="nav-section-label">KEUANGAN</div>
                @endif
                @if(userCan('bukukas'))
                <a href="{{ route('bukukas.index') }}" class="nav-item {{ request()->routeIs('bukukas.*') ? 'active' : '' }}">
                    <i class="fas fa-book nav-icon"></i> Buku Kas
                </a>
                @endif
                @if(userCan('pengeluaran'))
                <a href="{{ route('kas.pengeluaran') }}" class="nav-item {{ request()->routeIs('kas.pengeluaran') ? 'active' : '' }}">
                    <i class="fas fa-file-invoice-dollar nav-icon"></i> Pengeluaran
                </a>
                @endif
                @if(userCan('sumbangan'))
                <a href="{{ route('kas.sumbangan') }}" class="nav-item {{ request()->routeIs('kas.sumbangan') ? 'active' : '' }}">
                    <i class="fas fa-gift nav-icon"></i> Sumbangan
                </a>
                @endif

                @if(userCan('setor') || userCan('aduan') || userCan('mpwa') || userCan('kegiatan'))
                <div class="nav-section-label">ADMINISTRASI</div>
                @endif
                @if(userCan('setor'))
                <a href="{{ route('kas.setor') }}" class="nav-item {{ request()->routeIs('kas.setor') ? 'active' : '' }}">
                    <i class="fas fa-recycle nav-icon"></i> Setor Sampah RT
                </a>
                @endif
                @if(userCan('aduan'))
                <a href="{{ route('aduan.index') }}" class="nav-item {{ request()->routeIs('aduan.*') ? 'active' : '' }}">
                    <i class="fas fa-headset nav-icon"></i> Aduan Warga
                </a>
                @endif
                @if(userCan('mpwa'))
                <a href="{{ route('mpwa.index') }}" class="nav-item {{ request()->routeIs('mpwa.*') ? 'active' : '' }}">
                    <i class="fab fa-whatsapp nav-icon"></i> MPWA Broadcast
                </a>
                @endif
                @if(userCan('kegiatan'))
                <a href="{{ route('kegiatan.index') }}" class="nav-item {{ request()->routeIs('kegiatan.*') ? 'active' : '' }}">
                    <i class="fas fa-calendar-alt nav-icon"></i> Kegiatan RW
                </a>
                @endif

                @if(userCan('akun') || userCan('log') || userCan('pengaturan'))
                <div class="nav-section-label">ADMIN</div>
                @endif
                @if(userCan('akun'))
                <a href="{{ route('akun.index') }}" class="nav-item {{ request()->routeIs('akun.*') ? 'active' : '' }}">
                    <i class="fas fa-user-shield nav-icon"></i> Manajemen Akun
                </a>
                @endif
                @if(userCan('log'))
                <a href="{{ route('log.index') }}" class="nav-item {{ request()->routeIs('log.*') ? 'active' : '' }}">
                    <i class="fas fa-history nav-icon"></i> Log Sistem
                </a>
                @endif
                @if(userCan('pengaturan'))
                <a href="{{ route('pengaturan.index') }}" class="nav-item {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}">
                    <i class="fas fa-cog nav-icon"></i> Pengaturan
                </a>
                @endif
            </div>
            <div class="sidebar-scroll-hint"></div>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-avatar">{{ strtoupper(substr(Auth::user()->namaLengkap ?? 'A', 0, 1)) }}</div>
                    <div>
                        <div class="sidebar-user-name">{{ Auth::user()->namaLengkap ?? 'Admin' }}</div>
                        <div class="sidebar-user-role">{{ Auth::user()->level_efektif_label }}</div>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </button>
                </form>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <span class="page-title">@yield('page-title', 'Dashboard')</span>
                        <span class="page-subtitle">@yield('page-subtitle', 'Ringkasan data RW 10')</span>
                    </div>
                </div>
                <div class="header-right">
                    @if(auth()->user()->levelEfektif() !== 'warga')
                    {{-- Pencarian global memuat data seluruh warga, jadi hanya untuk pengurus --}}
                    <div class="header-search">
                        <i class="fas fa-search header-search-icon"></i>
                        <input type="text" id="globalSearch" placeholder="Cari warga..." autocomplete="off">
                        <div class="search-dropdown" id="searchDropdown"></div>
                    </div>
                    @endif
                    <div style="position:relative;">
                        <div class="header-avatar" id="avatarBtn" style="cursor:pointer;" title="{{ Auth::user()->namaLengkap ?? 'Admin' }}">
                            {{ strtoupper(substr(Auth::user()->namaLengkap ?? 'A', 0, 1)) }}
                        </div>
                        <div id="avatarDropdown" style="display:none; position:absolute; right:0; top:44px; background:white; border-radius:var(--radius); box-shadow:var(--shadow-lg); min-width:220px; z-index:999; overflow:hidden; border:1px solid var(--abu2);">
                            <div style="padding:14px 16px; background:var(--abu); border-bottom:1px solid var(--abu2);">
                                <div style="font-weight:700; font-size:14px;">{{ Auth::user()->namaLengkap ?? 'Admin' }}</div>
                                <div style="font-size:11px; color:var(--text3);">{{ Auth::user()->level_efektif_label }}</div>
                            </div>
                            <a href="{{ route('pengaturan.index') }}" style="display:flex; align-items:center; gap:10px; padding:10px 16px; font-size:13px; color:var(--text2); text-decoration:none; transition:background 0.2s;"><i class="fas fa-cog" style="width:16px; color:var(--text3);"></i> Pengaturan</a>
                            <form method="POST" action="{{ route('logout') }}" style="border-top:1px solid var(--abu2);">
                                @csrf
                                <button type="submit" style="display:flex; align-items:center; gap:10px; width:100%; padding:10px 16px; font-size:13px; color:var(--merah); background:none; border:none; cursor:pointer;"><i class="fas fa-sign-out-alt" style="width:16px;"></i> Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                @if(session('success'))
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>


    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');
        const moreBtn = document.getElementById('moreBtn');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }

        menuToggle.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);
        if (moreBtn) moreBtn.addEventListener('click', openSidebar);

        // Close sidebar on nav item click (mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        // Sidebar scroll persistence
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            const savedPos = sessionStorage.getItem('sidebar-scroll');
            if (savedPos) sidebarNav.scrollTop = parseInt(savedPos);
            sidebarNav.addEventListener('scroll', () => {
                sessionStorage.setItem('sidebar-scroll', sidebarNav.scrollTop);
            });
        }
        // Global Search
        const searchInput = document.getElementById('globalSearch');
        const searchDropdown = document.getElementById('searchDropdown');
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const q = this.value.trim();
                if (q.length < 2) { searchDropdown.classList.remove('show'); return; }
                searchTimeout = setTimeout(() => {
                    fetch('/search?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            if (data.length === 0) {
                                searchDropdown.innerHTML = '<div class="search-dropdown-item" style="color:var(--text3);">Tidak ditemukan</div>';
                            } else {
                                searchDropdown.innerHTML = data.map(w =>
                                    `<a href="/warga/${w.id}/edit" class="search-dropdown-item" style="text-decoration:none; color:inherit;"><span><b>${w.nama}</b><br><small style="color:var(--text3);">RT ${w.rt} • ${w.noHP || '-'}</small></span></a>`
                                ).join('');
                            }
                            searchDropdown.classList.add('show');
                        });
                }, 300);
            });
            document.addEventListener('click', e => { if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) searchDropdown.classList.remove('show'); });
        }
        // Avatar Dropdown
        const avatarBtn = document.getElementById('avatarBtn');
        const avatarDD = document.getElementById('avatarDropdown');
        if (avatarBtn) {
            avatarBtn.addEventListener('click', (e) => { e.stopPropagation(); avatarDD.style.display = avatarDD.style.display === 'none' ? 'block' : 'none'; });
            document.addEventListener('click', (e) => { if (!avatarDD.contains(e.target)) avatarDD.style.display = 'none'; });
        }
    </script>
</body>
</html>
