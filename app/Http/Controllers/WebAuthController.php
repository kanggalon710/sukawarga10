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

        // Check if NIK already registered as warga
        if (\App\Models\Keluarga::where('nik', $request->nik)->exists()) {
            return back()->with('error_register', 'NIK ini sudah terdaftar sebagai warga. Silakan langsung login.')
                         ->withInput();
        }

        // Check if NIK already has a pending registration
        if (\App\Models\Pendaftaran::where('nik', $request->nik)->where('status', 'pending')->exists()) {
            return back()->with('error_register', 'NIK ini sudah memiliki pengajuan yang menunggu verifikasi. Mohon tunggu proses persetujuan.')
                         ->withInput();
        }

        // Check if No KK already registered
        if (\App\Models\Keluarga::where('noKK', $request->no_kk)->exists()) {
            return back()->with('error_register', 'No. KK ini sudah terdaftar dalam data warga.')
                         ->withInput();
        }

        $noWa = $request->no_wa ?? null;
        \App\Models\Pendaftaran::create($request->only(['nik', 'no_kk', 'nama_lengkap', 'rt', 'no_wa']));

        // Send WA acknowledgement (fire-and-forget)
        if ($noWa) {
            MpwaService::notifyPendaftaranDiterima($noWa, $request->nama_lengkap, $request->rt);
        }

        return back()->with('success_register', 'Pendaftaran berhasil dikirim! Menunggu persetujuan Admin RW.' . ($noWa ? ' Notifikasi WA telah dikirim.' : ''));
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

    /**
     * Forgot PIN / Username — find user by WA number, reset PIN, send via WA.
     */
    public function forgotCredentials(Request $request) {
        $request->validate([
            'no_wa' => 'required|string|min:9|max:20',
        ]);

        $noWa = preg_replace('/[^0-9]/', '', $request->no_wa);

        // Find user by WA number
        $user = User::where('wa', $noWa)->orWhere('wa', $request->no_wa)->first();
        if (!$user) {
            // Also try with leading 0 replaced by 62
            $altNumber = '62' . ltrim($noWa, '0');
            $user = User::where('wa', $altNumber)->first();
            if (!$user) {
                $altNumber2 = '0' . substr($noWa, 2);
                $user = User::where('wa', $altNumber2)->first();
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp tidak ditemukan dalam sistem. Pastikan nomor yang Anda masukkan sesuai dengan yang terdaftar.'
            ]);
        }

        // Generate new random PIN
        $newPin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update(['pin' => Hash::make($newPin)]);

        AuditLogService::log('forgot_pin', 'auth', 'Reset PIN untuk user: ' . $user->username . ' via WA: ' . $noWa);

        // Send via WA
        $sent = MpwaService::notifyForgotCredentials(
            $noWa,
            $user->namaLengkap ?? $user->username,
            $user->username,
            $newPin
        );

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => 'Username dan PIN baru telah dikirim ke WhatsApp Anda. Silakan cek pesan masuk.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim via WhatsApp. Hubungi pengurus RW untuk bantuan.'
            ]);
        }
    }
}
