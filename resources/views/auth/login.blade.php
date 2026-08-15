<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SukaWarga10 · Portal RW 10 Sukakarya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0F7A4D; --primary-dark: #065F38; --accent: #F59E0B; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Source Sans 3', -apple-system, 'Segoe UI', sans-serif;
            min-height: 100vh; min-height: 100dvh;
            background: #071a0f; color: white;
            overflow-x: hidden;
        }

        #bgCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 0; }
        .mountain-bg { position: fixed; bottom: 0; left: 0; right: 0; height: 50vh; z-index: 1; pointer-events: none; }

        /* ── PAGE ── */
        .page { position: relative; z-index: 10; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; }

        /* ── TOP BAR ── */
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 28px;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .topbar-brand img {
            width: 38px; height: 38px; border-radius: 10px;
            box-shadow: 0 0 14px rgba(134,239,172,0.3);
        }
        .topbar-brand .name {
            font-weight: 800; font-size: 18px;
            background: linear-gradient(135deg, #fff, #86efac);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .topbar-actions { display: flex; gap: 8px; }
        .btn-pill {
            border: none; padding: 10px 22px; border-radius: 100px;
            font-family: inherit; font-size: 13px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 7px;
            transition: all 0.3s;
        }
        .btn-pill:hover { transform: translateY(-2px); }
        .btn-masuk {
            background: rgba(255,255,255,0.12); color: white;
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
        }
        .btn-masuk:hover { background: rgba(255,255,255,0.2); box-shadow: 0 6px 20px rgba(255,255,255,0.1); }
        .btn-daftar {
            background: linear-gradient(135deg, var(--accent), #D97706); color: white;
            box-shadow: 0 4px 16px rgba(245,158,11,0.35);
        }
        .btn-daftar:hover { box-shadow: 0 8px 24px rgba(245,158,11,0.5); }

        /* ── HERO CENTER ── */
        .hero {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; padding: 0 24px 50px;
        }
        .hero-logo {
            width: 160px; height: auto;
            margin-bottom: 24px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
        }
        .hero h1 {
            font-size: 42px; font-weight: 800; line-height: 1.1; margin-bottom: 12px;
            background: linear-gradient(135deg, #ffffff 0%, #86efac 50%, #f59e0b 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero .loc-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px); border-radius: 100px;
            padding: 7px 18px; font-size: 13px; color: rgba(255,255,255,0.9);
            font-weight: 600; margin-bottom: 16px;
        }
        .hero .loc-badge i { color: #86efac; }
        .hero .tagline {
            font-size: 15px; color: rgba(255,255,255,0.7); line-height: 1.7;
            max-width: 500px; margin-bottom: 28px;
        }

        /* ── STATS & CHART ── */
        .info-grid {
            display: flex; gap: 14px; width: 100%; max-width: 540px;
            flex-wrap: wrap; justify-content: center;
        }
        .info-stat {
            flex: 1; min-width: 140px;
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(20px); border-radius: 16px; padding: 18px;
            text-align: center; transition: all 0.3s;
        }
        .info-stat:hover { border-color: rgba(134,239,172,0.4); transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
        .info-stat i { font-size: 22px; color: #86efac; margin-bottom: 8px; display: block; }
        .info-stat .val { font-size: 30px; font-weight: 800; }
        .info-stat .lbl { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-top: 2px; }

        .chart-card {
            width: 100%; max-width: 540px; margin-top: 14px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(20px); border-radius: 16px; padding: 18px 20px;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .chart-head .title { font-size: 14px; font-weight: 700; color: rgba(255,255,255,0.9); }
        .chart-head .legend { display: flex; gap: 10px; font-size: 10px; color: rgba(255,255,255,0.5); }
        .chart-totals { display: flex; gap: 20px; font-size: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .chart-totals .in { color: #86efac; font-weight: 700; }
        .chart-totals .out { color: #fca5a5; font-weight: 700; }
        .chart-totals span:first-child { color: rgba(255,255,255,0.45); }

        /* ── MODALS ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
            z-index: 200; align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: rgba(255,255,255,0.96); backdrop-filter: blur(40px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
            padding: 36px 32px; width: 100%; max-width: 420px;
            position: relative;
            animation: modalIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.92) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-close {
            position: absolute; top: 14px; right: 16px;
            background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 10px;
            font-size: 16px; color: #64748b; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; color: #1e293b; }
        .modal-header { text-align: center; margin-bottom: 22px; }
        .modal-header h2 { font-size: 22px; font-weight: 800; color: #1e293b; }
        .modal-header p { font-size: 13px; color: #94a3b8; margin-top: 4px; }

        .input-group { margin-bottom: 14px; }
        .input-group label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; pointer-events: none; }
        .form-control {
            width: 100%; padding: 12px 14px 12px 40px;
            background: #f8fafc; border: 1.5px solid #e2e8f0;
            border-radius: 12px; font-size: 14px; color: #1e293b;
            font-family: inherit; transition: all 0.3s;
        }
        .form-control:focus { background: white; border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(15,122,77,0.1); }
        select.form-control { padding-left: 14px; }

        .btn-action {
            width: 100%; border: none; padding: 13px;
            border-radius: 12px; font-family: inherit; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 4px; position: relative; overflow: hidden;
        }
        .btn-action.green {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white;
            box-shadow: 0 6px 18px rgba(15,122,77,0.3);
        }
        .btn-action:hover { transform: translateY(-2px); }

        .alert {
            padding: 10px 14px; border-radius: 10px; margin-bottom: 16px;
            font-size: 12px; display: flex; align-items: center; gap: 8px; font-weight: 500;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; }

        /* ── RESPONSIVE ── */
        @media(max-width: 600px) {
            .topbar { padding: 14px 16px; }
            .btn-pill { padding: 8px 14px; font-size: 12px; }
            .hero h1 { font-size: 28px; }
            .hero .tagline { font-size: 13px; }
            .hero-logo { width: 120px; height: auto; }
            .info-stat .val { font-size: 24px; }
            .modal-card { padding: 28px 22px; }
        }
    </style>
</head>
<body>
    <canvas id="bgCanvas"></canvas>
    <svg class="mountain-bg" viewBox="0 0 1440 400" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,350 L120,180 L240,280 L380,130 L500,250 L650,100 L780,220 L900,80 L1050,200 L1180,120 L1320,240 L1440,150 L1440,400 L0,400 Z" fill="rgba(15,90,55,0.5)"/>
        <path d="M0,370 L80,240 L200,310 L330,200 L460,290 L580,180 L720,270 L820,160 L960,260 L1080,190 L1200,280 L1360,220 L1440,300 L1440,400 L0,400 Z" fill="rgba(10,70,40,0.6)"/>
        <path d="M0,390 L150,330 L300,360 L480,310 L640,350 L800,320 L950,355 L1100,325 L1280,360 L1440,330 L1440,400 L0,400 Z" fill="rgba(8,50,30,0.8)"/>
        <ellipse cx="300" cy="310" rx="400" ry="60" fill="rgba(255,255,255,0.04)"/>
        <ellipse cx="900" cy="290" rx="350" ry="50" fill="rgba(255,255,255,0.03)"/>
    </svg>

    <div class="page">
        {{-- ── TOP BAR ── --}}
        <div class="topbar">
            <div class="topbar-brand">
                <img src="{{ asset('logo-sukawarga-icon.svg') }}" alt="Logo">
                <span class="name">SukaWarga10</span>
            </div>
            <div class="topbar-actions">
                <button class="btn-pill btn-masuk" onclick="openModal('loginModal')">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
                <button class="btn-pill btn-daftar" onclick="openModal('registerModal')">
                    <i class="fas fa-user-plus"></i> Daftar
                </button>
            </div>
        </div>

        {{-- ── HERO ── --}}
        <div class="hero">
            <img src="{{ asset('logo-sukawarga.svg') }}" alt="Logo RW 10" class="hero-logo" style="width:160px; height:auto; box-shadow:none; background:transparent; border-radius:0; padding:0;">
            <h1>SukaWarga10</h1>
            <div class="loc-badge">
                <i class="fas fa-map-marker-alt"></i>
                RW 10 • Kelurahan Sukakarya • Garut
            </div>
            <p class="tagline">
                Sistem Informasi & Keuangan Komunitas Terpadu.<br>
                Inovasi digital untuk RW 10, kota resik rahayu.<br>
                <em style="color:#86efac; font-style:normal; font-weight:600;">"Swiss van Java"</em>
            </p>

            <div class="info-grid">
                <div class="info-stat">
                    <i class="fas fa-id-card"></i>
                    <div class="val">{{ $totalNoKK ?? 0 }}</div>
                    <div class="lbl">NIK Kartu Keluarga</div>
                </div>
                <div class="info-stat">
                    <i class="fas fa-fingerprint"></i>
                    <div class="val">{{ $totalNIK ?? 0 }}</div>
                    <div class="lbl">NIK KTP</div>
                </div>
            </div>

            {{-- Tren Kas --}}
            @php
                $maxVal = max(1, collect($trenKas)->max('masuk'), collect($trenKas)->max('keluar'));
                $totalMasuk = collect($trenKas)->sum('masuk');
                $totalKeluar = collect($trenKas)->sum('keluar');
            @endphp
            <div class="chart-card">
                <div class="chart-head">
                    <div class="title">📊 Tren Kas {{ $tahun }}</div>
                    <div class="legend">
                        <span><i class="fas fa-circle" style="color:#86efac; font-size:6px;"></i> Masuk</span>
                        <span><i class="fas fa-circle" style="color:#fca5a5; font-size:6px;"></i> Keluar</span>
                    </div>
                </div>
                <div class="chart-totals">
                    <div><span>Pemasukan: </span><span class="in">Rp {{ number_format($totalMasuk,0,',','.') }}</span></div>
                    <div><span>Pengeluaran: </span><span class="out">Rp {{ number_format($totalKeluar,0,',','.') }}</span></div>
                </div>
                <div style="display:flex; align-items:flex-end; justify-content:space-around; height:90px; gap:6px;">
                    @foreach($trenKas as $t)
                    <div style="display:flex; flex-direction:column; align-items:center; gap:4px; flex:1;">
                        <div style="display:flex; align-items:flex-end; gap:3px; height:68px;">
                            <div style="width:14px; background:linear-gradient(to top, #059669, #86efac); border-radius:4px 4px 0 0; height:{{ $maxVal > 0 ? max(4, $t['masuk']/$maxVal*58) : 4 }}px;" title="Masuk: Rp {{ number_format($t['masuk'],0,',','.') }}"></div>
                            <div style="width:14px; background:linear-gradient(to top, #dc2626, #fca5a5); border-radius:4px 4px 0 0; height:{{ $maxVal > 0 ? max(4, $t['keluar']/$maxVal*58) : 4 }}px;" title="Keluar: Rp {{ number_format($t['keluar'],0,',','.') }}"></div>
                        </div>
                        <span style="font-size:10px; color:rgba(255,255,255,0.45); font-weight:600;">{{ $t['bulan'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══ LOGIN MODAL ══ --}}
    <div id="loginModal" class="modal-overlay" onclick="if(event.target===this)closeModal('loginModal')">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal('loginModal')"><i class="fas fa-times"></i></button>
            @if(session('error'))
                <div class="alert alert-error" id="loginError">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    @if(session('lockout_seconds'))
                    <div id="lockoutTimer" style="margin-top:6px; font-size:12px; font-weight:700; color:#b71c1c;">
                        ⏳ Coba lagi dalam <span id="lockoutCount">{{ session('lockout_seconds') }}</span> detik
                    </div>
                    <script>
                        (function() {
                            let s = {{ session('lockout_seconds') }};
                            const el = document.getElementById('lockoutCount');
                            const timer = setInterval(() => {
                                s--;
                                if (s <= 0) { clearInterval(timer); el.parentElement.innerHTML = '✅ Silakan coba login kembali.'; return; }
                                el.textContent = s;
                            }, 1000);
                        })();
                    </script>
                    @endif
                </div>
            @endif
            @if(session('success_register'))
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success_register') }}</div>
            @endif

            <div class="modal-header">
                <h2>Selamat Datang 👋</h2>
                <p>Masuk ke portal RW 10 Sukakarya</p>
            </div>

            <form action="{{ route('login.post') }}" method="POST">
                @csrf
                <div class="input-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required value="{{ old('username') }}">
                    </div>
                </div>
                <div class="input-group">
                    <label>PIN (6 Digit)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="pin" name="pin" class="form-control" placeholder="••••••" required pattern="\d{6}" maxlength="6" inputmode="numeric">
                        <button type="button" onclick="togglePin()" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#94a3b8; cursor:pointer; font-size:14px;"><i class="fas fa-eye" id="eyeIcon"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-action green">
                    <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                </button>
            </form>

            <div style="text-align:center; margin-top:12px; font-size:12.5px;">
                <a href="#" onclick="closeModal('loginModal'); setTimeout(()=>openModal('forgotModal'),200); return false;" style="color:var(--primary); font-weight:700; text-decoration:none;">
                    <i class="fas fa-key"></i> Lupa Username / PIN?
                </a>
            </div>
            <div style="text-align:center; margin-top:10px; font-size:12.5px; color:#94a3b8;">
                Belum punya akun?
                <a href="#" onclick="closeModal('loginModal'); setTimeout(()=>openModal('registerModal'),200); return false;" style="color:var(--primary); font-weight:700; text-decoration:none;">Daftar Warga Baru →</a>
            </div>
        </div>
    </div>

    {{-- ══ REGISTER MODAL ══ --}}
    <div id="registerModal" class="modal-overlay" onclick="if(event.target===this)closeModal('registerModal')">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal('registerModal')"><i class="fas fa-times"></i></button>

            @if(session('error_register'))
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> {{ session('error_register') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Periksa kembali isian form.</div>
            @endif

            <div class="modal-header">
                <h2>Daftar Warga Baru 🏠</h2>
                <p>Isi data untuk pengajuan ke Admin RW</p>
            </div>

            <form action="{{ route('login.register') }}" method="POST" onsubmit="return handleRegisterSubmit(this)">
                @csrf
                <div class="input-group">
                    <label>NIK KTP (16 Digit) *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-fingerprint input-icon"></i>
                        <input type="text" name="nik" class="form-control" placeholder="327201..." required pattern="\d{16}" maxlength="16" value="{{ old('nik') }}">
                    </div>
                </div>
                <div class="input-group">
                    <label>NIK Kartu Keluarga (16 Digit)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="no_kk" class="form-control" placeholder="327201..." pattern="\d{16}" maxlength="16" value="{{ old('no_kk') }}">
                    </div>
                </div>
                <div class="input-group">
                    <label>Nama Lengkap *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Sesuai KTP/KK" required value="{{ old('nama_lengkap') }}">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="input-group" style="flex:1;">
                        <label>RT *</label>
                        <select name="rt" class="form-control" required>
                            <option value="">Pilih</option>
                            @for($i=1; $i<=6; $i++)
                                <option value="{{ str_pad($i,2,'0',STR_PAD_LEFT) }}" {{ old('rt')==str_pad($i,2,'0',STR_PAD_LEFT)?'selected':'' }}>RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="input-group" style="flex:1.6;">
                        <label>No. WhatsApp</label>
                        <div class="input-wrapper">
                            <i class="fab fa-whatsapp input-icon" style="color:#25D366;"></i>
                            <input type="text" name="no_wa" class="form-control" placeholder="08..." value="{{ old('no_wa') }}">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-action green">
                    <i class="fas fa-paper-plane"></i> Ajukan Pendaftaran
                </button>
                <p style="font-size:11px; color:#94a3b8; text-align:center; margin-top:14px; line-height:1.5;">
                    📋 Setelah disetujui admin, <strong>username & PIN</strong> dikirim otomatis via WhatsApp.
                </p>
            </form>

            <div style="text-align:center; margin-top:10px; font-size:12.5px; color:#94a3b8;">
                Sudah punya akun?
                <a href="#" onclick="closeModal('registerModal'); setTimeout(()=>openModal('loginModal'),200); return false;" style="color:var(--primary); font-weight:700; text-decoration:none;">← Masuk</a>
            </div>
        </div>
    </div>

    {{-- ══ FORGOT MODAL ══ --}}
    <div id="forgotModal" class="modal-overlay" onclick="if(event.target===this)closeModal('forgotModal')">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal('forgotModal')"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2>🔑 Lupa Akun</h2>
                <p>Masukkan nomor WhatsApp terdaftar untuk menerima username & PIN baru</p>
            </div>
            <div id="forgotAlert" style="display:none;" class="alert"></div>
            <div class="input-group">
                <label>Nomor WhatsApp Terdaftar</label>
                <div class="input-wrapper">
                    <i class="fab fa-whatsapp input-icon" style="color:#25D366;"></i>
                    <input type="text" id="forgotWa" class="form-control" placeholder="08xxxxx atau 628xxxxx" inputmode="numeric" maxlength="20">
                </div>
            </div>
            <button type="button" class="btn-action green" id="forgotBtn" onclick="handleForgot()">
                <i class="fab fa-whatsapp"></i> Kirim via WhatsApp
            </button>
            <div style="text-align:center; margin-top:14px; font-size:12.5px; color:#94a3b8;">
                <a href="#" onclick="closeModal('forgotModal'); setTimeout(()=>openModal('loginModal'),200); return false;" style="color:var(--primary); font-weight:700; text-decoration:none;">← Kembali ke Login</a>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function togglePin() {
            const p = document.getElementById('pin'), i = document.getElementById('eyeIcon');
            p.type = p.type === 'password' ? 'text' : 'password';
            i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
        }
        function handleRegisterSubmit(form) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn.disabled) return false;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            btn.style.opacity = '0.7';
            return true;
        }

        // Auto-open modals on session feedback
        @if(session('error'))
            openModal('loginModal');
        @endif
        @if($errors->any() || session('error_register'))
            openModal('registerModal');
        @endif
        @if(session('success_register'))
            openModal('loginModal');
        @endif

        async function handleForgot() {
            const wa = document.getElementById('forgotWa').value.trim();
            if (!wa || wa.length < 9) return showForgotAlert(false, 'Masukkan nomor WhatsApp yang valid.');

            const btn = document.getElementById('forgotBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

            try {
                const res = await fetch('{{ route("login.forgot") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({ no_wa: wa })
                });
                const data = await res.json();
                showForgotAlert(data.success, data.message);
                if (data.success) document.getElementById('forgotWa').value = '';
            } catch(e) {
                showForgotAlert(false, 'Gagal terhubung ke server.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> Kirim via WhatsApp';
        }

        function showForgotAlert(success, msg) {
            const el = document.getElementById('forgotAlert');
            el.style.display = 'flex';
            el.className = 'alert ' + (success ? 'alert-success' : 'alert-error');
            el.innerHTML = `<i class="fas fa-${success ? 'check' : 'exclamation'}-circle"></i> ${msg}`;
        }

        // ========== CANVAS AURORA ==========
        const canvas = document.getElementById('bgCanvas');
        const ctx = canvas.getContext('2d');
        let W, H, particles = [], time = 0;
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();

        const layers = [
            { color: '#0F7A4D', alpha: 0.25, freq: 0.005, amp: 120, speed: 0.3, yOff: 0.45 },
            { color: '#14b8a6', alpha: 0.18, freq: 0.007, amp: 90,  speed: 0.5, yOff: 0.55 },
            { color: '#065F38', alpha: 0.30, freq: 0.004, amp: 150, speed: 0.2, yOff: 0.65 },
            { color: '#047857', alpha: 0.22, freq: 0.006, amp: 100, speed: 0.4, yOff: 0.50 },
        ];
        for(let i = 0; i < 50; i++) {
            particles.push({
                x: Math.random()*innerWidth, y: Math.random()*innerHeight,
                r: Math.random()*2.5+0.5, dx: (Math.random()-0.5)*0.3,
                dy: -(Math.random()*0.5+0.1), alpha: Math.random()*0.4+0.1,
                color: Math.random()>0.5 ? '#86efac' : '#fde68a',
            });
        }
        function hexToRgba(hex, a) {
            const r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
            return `rgba(${r},${g},${b},${a})`;
        }
        function animate() {
            time += 0.008; ctx.clearRect(0,0,W,H);
            const sky = ctx.createLinearGradient(0,0,0,H);
            sky.addColorStop(0,'#020d07'); sky.addColorStop(0.4,'#071a0f'); sky.addColorStop(1,'#0a2a16');
            ctx.fillStyle = sky; ctx.fillRect(0,0,W,H);

            // Moon
            const mx=W*0.85, my=H*0.12;
            const grd=ctx.createRadialGradient(mx,my,0,mx,my,40);
            grd.addColorStop(0,'rgba(255,253,230,0.9)'); grd.addColorStop(0.6,'rgba(255,248,200,0.6)'); grd.addColorStop(1,'rgba(255,248,200,0)');
            ctx.beginPath(); ctx.arc(mx,my,25,0,Math.PI*2); ctx.fillStyle=grd; ctx.fill();

            // Stars
            for(let i=0;i<60;i++){
                const x=(i*1847+23)%W, y=(i*937+11)%(H*0.55);
                ctx.beginPath(); ctx.arc(x,y,0.7+(i%3)*0.3,0,Math.PI*2);
                ctx.fillStyle=`rgba(255,255,255,${0.3+0.5*Math.sin(time*2+i)})`; ctx.fill();
            }

            // Aurora
            layers.forEach(l=>{
                ctx.beginPath(); ctx.moveTo(0,H);
                for(let x=0;x<=W;x+=4){
                    const ph=x*l.freq+time*l.speed;
                    ctx.lineTo(x, H*l.yOff - Math.sin(ph)*l.amp - Math.sin(ph*0.7+1.5)*(l.amp*0.5));
                }
                ctx.lineTo(W,H); ctx.closePath();
                ctx.fillStyle=hexToRgba(l.color,l.alpha); ctx.fill();
            });

            // Bokeh
            particles.forEach(p=>{
                p.x+=p.dx+Math.sin(time+p.y*0.01)*0.15; p.y+=p.dy;
                if(p.y<-10){p.y=H+10; p.x=Math.random()*W;}
                if(p.x<0)p.x=W; if(p.x>W)p.x=0;
                ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
                ctx.fillStyle=hexToRgba(p.color,p.alpha*(0.6+0.4*Math.sin(time*2+p.x)));
                ctx.shadowBlur=8; ctx.shadowColor=p.color; ctx.fill(); ctx.shadowBlur=0;
            });

            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>
</html>
