<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Services\MpwaService;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class WebAuthController extends Controller
{
    public function showLoginForm() {
        $totalKK = \App\Models\Keluarga::where('status', 'aktif')->count();
        // Lewat model, bukan DB::table: query builder polos tidak tersaring
        // scope organisasi, jadi statistik halaman publik ini menghitung
        // anggota tenant lain (aturan AGENTS.md #9).
        $totalA = \App\Models\Anggota::count();
        if ($totalA == 0) $totalA = $totalKK * 3; // Fallback estimate

        // Count real NIK data
        $totalNIK = \App\Models\Keluarga::where('status', 'aktif')
            ->whereNotNull('nik')->where('nik', '!=', '')->count();
        $totalNIK += \App\Models\Anggota::whereNotNull('nik')->where('nik', '!=', '')->count();

        $totalNoKK = \App\Models\Keluarga::where('status', 'aktif')
            ->whereNotNull('noKK')->where('noKK', '!=', '')->count();

        // Tren Kas 6 bulan
        $tahun = date('Y');
        $bulanIni = (int) date('m');
        $namaBulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $semester = $bulanIni <= 6 ? range(1, 6) : range(7, 12);
        $trenKas = [];
        foreach ($semester as $m) {
            $masuk = \App\Models\Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
            $keluar = \App\Models\Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');
            $trenKas[] = ['bulan' => $namaBulan[$m], 'masuk' => (int)$masuk, 'keluar' => (int)$keluar];
        }

        return view('auth.login', compact('totalKK', 'totalA', 'totalNIK', 'totalNoKK', 'trenKas', 'tahun'));
    }

    public function registerWarga(Request $request) {
        $request->validate([
            'nik' => 'required|numeric|digits:16',
            'no_kk' => 'nullable|numeric|digits:16',
            'nama_lengkap' => 'required|string|max:100',
            'rt' => 'required|string|max:3',
            'no_wa' => 'nullable|string|max:20',
        ]);

        // Jawaban SERAGAM untuk pengajuan baru, NIK yang sudah jadi warga, dan
        // NIK yang pengajuannya masih menunggu: halaman ini terbuka tanpa
        // login, dan jawaban yang berbeda dulu bisa dipakai menebak siapa
        // yang terdaftar di RW ini. Hanya pengajuan yang benar-benar baru
        // yang dicatat; tanda terima WA tetap dikirim di semua kasus (setelah
        // respons, lewat defer) supaya WA maupun waktu respons tidak menjadi
        // saluran samping. sudahDipakai() ikut memeriksa tabel anggota.
        $sudahAda = \App\Services\PemeriksaNikWarga::sudahDipakai($request->nik, $request->no_kk)
            || \App\Models\Pendaftaran::where('nik', $request->nik)->where('status', 'pending')->exists();

        if (! $sudahAda) {
            \App\Models\Pendaftaran::create($request->only(['nik', 'no_kk', 'nama_lengkap', 'rt', 'no_wa']));
        }

        $noWa = (string) $request->no_wa;
        if ($noWa !== '') {
            $nama = (string) $request->nama_lengkap;
            $rt = (string) $request->rt;
            \Illuminate\Support\defer(fn () => MpwaService::notifyPendaftaranDiterima($noWa, $nama, $rt));
        }

        return back()->with('success_register',
            'Pengajuan pendaftaran diterima dan akan diperiksa pengurus RW. '
            .'Bila NIK Anda ternyata sudah terdaftar, silakan masuk atau gunakan menu Lupa Username / PIN.');
    }

    public function login(Request $request) {
        $request->validate([
            'username' => 'required',
            'pin' => 'required'
        ]);

        // --- RATE LIMITING: max 3 attempts per 2 minutes per IP ---
        $throttleKey = 'login|' . $request->ip();
        $maxAttempts = 3;
        $decaySeconds = 120; // 2 minutes

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            AuditLogService::log('login_blocked', 'auth', 'Rate limit exceeded from IP: ' . $request->ip());
            return back()
                ->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . $seconds . ' detik.')
                ->with('lockout_seconds', $seconds)
                ->withInput($request->only('username'));
        }

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, $decaySeconds);
            AuditLogService::log('login_failed', 'auth', 'Username tidak ditemukan: ' . $request->username . ' (IP: ' . $request->ip() . ')');
            return back()->with('error', 'Username atau PIN salah')->withInput($request->only('username'));
        }

        // --- ACCOUNT LOCKOUT CHECK ---
        if ($user->locked_until && now()->lt($user->locked_until)) {
            $remainingMinutes = now()->diffInMinutes($user->locked_until) + 1;
            return back()
                ->with('error', 'Akun Anda dikunci karena terlalu banyak percobaan login gagal. Coba lagi dalam ' . $remainingMinutes . ' menit.')
                ->withInput($request->only('username'));
        }

        // Check PIN — only hashed comparison
        $pinValid = Hash::check($request->pin, $user->pin);

        // Auto-migrate legacy plaintext PIN (one-time): if hash fails, check plaintext then hash it
        if (!$pinValid && strlen($user->pin) <= 6 && $user->pin === $request->pin) {
            $user->update(['pin' => Hash::make($request->pin)]);
            $pinValid = true;
        }

        if (!$pinValid) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, $decaySeconds);

            // Increment failed counter & lock if exceeded
            $failCount = ($user->failed_login_count ?? 0) + 1;
            $lockData = ['failed_login_count' => $failCount];
            if ($failCount >= 10) {
                $lockData['locked_until'] = now()->addMinutes(15); // Lock for 15 minutes
                AuditLogService::log('account_locked', 'auth', 'Akun dikunci setelah ' . $failCount . ' percobaan gagal: ' . $user->username . ' (IP: ' . $request->ip() . ')');
            }
            $user->update($lockData);

            AuditLogService::log('login_failed', 'auth', 'PIN salah untuk: ' . $user->username . ' (IP: ' . $request->ip() . ', gagal ke-' . $failCount . ')');
            return back()->with('error', 'Username atau PIN salah')->withInput($request->only('username'));
        }

        if ($user->status !== null && $user->status !== 'aktif') {
            return back()->with('error', 'Akun Anda dinonaktifkan. Hubungi administrator.')->withInput($request->only('username'));
        }

        // --- PENJAGA TENANT (Phase D): warga hanya login di subdomain RW-nya ---
        // Dicek SETELAH PIN valid supaya pesannya boleh menyebut alamat portal
        // yang benar (orang tua yang salah alamat dibimbing, bukan dibiarkan
        // menatap dashboard kosong). Akun tanpa jangkar (keluarga/assignment)
        // dibiarkan lewat: itu akun lama, jangan dikunci dari mana-mana.
        $tolak = $this->tolakLintasTenant($user);
        if ($tolak !== null) {
            AuditLogService::log('login_ditolak_lintas_tenant', 'auth',
                'Login ' . $user->username . ' ditolak di ' . $request->getHost() . ' (IP: ' . $request->ip() . ')');
            return back()->with('error', $tolak)->withInput($request->only('username'));
        }

        // --- LOGIN SUCCESS ---
        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);
        $user->update([
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        AuditLogService::log('login', 'auth', 'Login berhasil: ' . $user->username . ' (IP: ' . $request->ip() . ')');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Pesan penolakan bila user milik tenant LAIN, atau null bila boleh masuk.
     *
     * Boleh masuk bila: punya assignment yang relevan dengan tenant ini
     * (pengurus, warga hasil backfill, super admin platform - platform ada di
     * rantai leluhur semua tenant), ATAU keluarganya terlihat di tenant ini,
     * ATAU tidak punya jangkar sama sekali (akun lama).
     */
    private function tolakLintasTenant(User $user): ?string
    {
        $context = app(\App\Services\TenantContext::class);
        if (!$context->sudahDitetapkan() || $context->organisasi() === null) {
            return null;
        }

        if ($user->levelEfektifUntuk($context->organisasi()) !== null) {
            return null;
        }
        if ($user->keluarga_id
            && \App\Models\Keluarga::where('keluarga_id', $user->keluarga_id)->exists()) {
            return null;
        }

        $rwAsal = $user->rwAsal();
        if ($rwAsal === null || $rwAsal->id === $context->rw()?->id) {
            return null;
        }

        $desaAsal = $rwAsal->leluhur(\App\Models\Organization::TYPE_DESA);
        $tempat = trim($rwAsal->name . ' ' . ($desaAsal->name ?? ''));
        $alamat = $rwAsal->domains()->orderByDesc('is_primary')->value('hostname');

        return $alamat !== null
            ? "Akun Anda terdaftar di {$tempat}. Silakan masuk lewat: {$alamat}"
            : "Akun Anda terdaftar di {$tempat}. Hubungi pengurus RW Anda untuk alamat portalnya.";
    }

    public function logout(Request $request) {
        AuditLogService::log('logout', 'auth', 'Logout: ' . (Auth::user()->username ?? '-'));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /** Jawaban publik tunggal untuk permintaan kode, apa pun keadaan nomornya. */
    private const PESAN_KODE_DIKIRIM = 'Bila nomor tersebut terdaftar, kode pemulihan 6 digit sudah dikirim lewat WhatsApp '
        .'dan berlaku 10 menit. Bila tidak menerima pesan dalam beberapa menit, hubungi pengurus RW.';

    private const PESAN_KODE_SALAH = 'Kode salah atau sudah kedaluwarsa. Periksa kembali, atau minta kode baru.';

    /**
     * Lupa Username / PIN, langkah 1: kirim kode sekali pakai lewat WA.
     *
     * PIN TIDAK diubah di sini. Dulu PIN baru disimpan lebih dulu lalu dikirim;
     * bila WA gagal, pemilik akun terkunci tanpa tahu PIN-nya, dan nomor tak
     * dikenal dijawab "tidak ditemukan" sehingga bisa dipakai menebak akun.
     */
    public function forgotCredentials(Request $request) {
        $request->validate([
            'no_wa' => 'required|string|min:9|max:20',
        ]);

        $nomor = normalizeWa($request->no_wa);
        $user = $nomor ? $this->penggunaDariWa($nomor) : null;

        if ($user) {
            $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pemulihan = \App\Models\PemulihanPin::terbitkan($user, $kode);
            $nama = $user->namaLengkap ?? $user->username;

            // Dikirim SETELAH respons: waktu respons tidak boleh membedakan
            // nomor terdaftar (menunggu gateway) dari yang tidak. Gagal kirim
            // = kode dihanguskan, PIN tetap yang lama.
            \Illuminate\Support\defer(function () use ($pemulihan, $nomor, $nama, $kode, $user) {
                if (! MpwaService::notifyKodePemulihan($nomor, $nama, $kode)) {
                    $pemulihan->forceFill(['dipakai_pada' => now()])->save();
                    \Illuminate\Support\Facades\Log::warning('Kode pemulihan PIN gagal dikirim', [
                        'akun' => $user->id, 'wa' => samarkanWa($nomor),
                    ]);
                }
            });

            AuditLogService::log('pemulihan_pin_diminta', 'auth', 'Kode pemulihan PIN diterbitkan untuk akun #'.$user->id);
        }

        return response()->json([
            'success' => true,
            'langkah' => 'kode',
            'message' => self::PESAN_KODE_DIKIRIM,
        ]);
    }

    /**
     * Lupa Username / PIN, langkah 2: kode benar -> PIN baru disimpan.
     * Username baru ditampilkan di sini, setelah pemilik nomor terbukti.
     */
    public function verifikasiPemulihan(Request $request) {
        $data = $request->validate([
            'no_wa' => 'required|string|min:9|max:20',
            'kode' => 'required|digits:6',
            'pin_baru' => 'required|digits:6|confirmed',
        ], [
            'kode.digits' => 'Kode terdiri dari 6 angka.',
            'pin_baru.digits' => 'PIN baru harus 6 angka.',
            'pin_baru.confirmed' => 'Konfirmasi PIN tidak sama.',
        ]);

        $nomor = normalizeWa($data['no_wa']);
        $user = $nomor ? $this->penggunaDariWa($nomor) : null;

        $berhasil = $user !== null && \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data) {
            $pemulihan = \App\Models\PemulihanPin::where('user_id', $user->id)
                ->whereNull('dipakai_pada')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $pemulihan || $pemulihan->percobaan >= \App\Models\PemulihanPin::PERCOBAAN_MAKS) {
                return false;
            }
            if (! $pemulihan->cocok($user, $data['kode'])) {
                $pemulihan->increment('percobaan');

                return false;
            }

            \App\Models\PemulihanPin::hanguskanMilik($user);
            // forceFill: kolom penguncian tidak semuanya fillable, dan update()
            // diam-diam membuang yang tidak fillable (aturan AGENTS #1-2).
            $user->forceFill([
                'pin' => Hash::make($data['pin_baru']),
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            return true;
        });

        if (! $berhasil) {
            return response()->json(['success' => false, 'message' => self::PESAN_KODE_SALAH], 422);
        }

        AuditLogService::log('pemulihan_pin_berhasil', 'auth', 'PIN akun #'.$user->id.' diganti lewat kode pemulihan WA');

        return response()->json([
            'success' => true,
            'username' => $user->username,
            'message' => 'PIN berhasil diganti. Username Anda: '.$user->username.'. Silakan masuk dengan PIN baru.',
        ]);
    }

    /**
     * Akun aktif pemilik nomor WA. Nomor lama bisa tersimpan dalam beberapa
     * format (08.., 8.., 62..), jadi semua bentuknya dicocokkan; id terkecil
     * menang bila satu nomor dipakai beberapa akun (sama dengan perilaku lama).
     */
    private function penggunaDariWa(string $nomor62): ?User
    {
        $lokal = substr($nomor62, 2);

        return User::whereIn('wa', [$nomor62, '0'.$lokal, $lokal, '+'.$nomor62])
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'aktif'))
            ->orderBy('id')
            ->first();
    }
}
