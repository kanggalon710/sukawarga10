<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AppSetting;

/**
 * MpwaService - satu-satunya jalur ke gateway WhatsApp MPWA.
 *
 * Host gateway dari config (services.mpwa.url) dan wajib https + terdaftar di
 * services.mpwa.allowed_hosts; kunci API dibaca lewat AppSetting::rahasia()
 * dan tidak pernah keluar ke browser. Seluruh request HTTP ke gateway lewat
 * kirimKeGateway(), tidak ada Http::post lain di aplikasi.
 */
class MpwaService
{
    /** Endpoint gateway yang boleh dipanggil. */
    private const ENDPOINT = ['/send-message', '/send-button-message'];

    /**
     * Footer pesan WhatsApp.
     *
     * Dulu konstanta berisi nama & lokasi yang ditulis tetap. Sekarang ikut
     * identitas aplikasi supaya pengurus (dan project turunan untuk kampung
     * lain) cukup mengganti lewat menu Pengaturan, tanpa menyentuh kode.
     */
    public static function footer(): string
    {
        return namaAplikasi() . ' • ' . lokasiSingkat();
    }

    /** Baris kop pesan: "Portal <nama> · <lokasi>". */
    private static function kop(): string
    {
        return 'Portal ' . namaAplikasi() . ' · ' . lokasiSingkat();
    }

    /** Tanda tangan pesan ke warga. */
    private static function tandaTangan(): string
    {
        return '🙏 Pengurus ' . namaAplikasi();
    }

    /**
     * Kunci API efektif (milik tenant, atau warisan desa/platform), hanya
     * untuk dipakai server. Jangan diteruskan ke view atau respons.
     */
    public static function apiKey(): string
    {
        return AppSetting::rahasia('mpwa_api_key') ?? '';
    }

    /** Read default sender from DB. */
    public static function sender(): string
    {
        return AppSetting::nilai('mpwa_sender') ?? '';
    }

    /**
     * Base URL gateway dari config, atau null bila tidak lolos allow-list.
     *
     * Dulu bisa ditimpa tenant lewat setting `mpwa_api_url`, sehingga kunci
     * API warisan ikut terkirim ke host pilihan tenant (SSRF + kebocoran
     * kunci). Sekarang hanya https ke host yang terdaftar.
     */
    public static function baseUrl(): ?string
    {
        $url = rtrim((string) config('services.mpwa.url'), '/');
        $bagian = parse_url($url);
        $host = strtolower((string) ($bagian['host'] ?? ''));
        $diizinkan = array_map('strtolower', (array) config('services.mpwa.allowed_hosts', []));

        $sah = ($bagian['scheme'] ?? '') === 'https'
            && $host !== ''
            && in_array($host, $diizinkan, true)
            // Satu per satu: isset(a, b) baru true bila KEDUANYA ada.
            && !isset($bagian['user']) && !isset($bagian['pass'])
            && !isset($bagian['query']) && !isset($bagian['fragment']);

        if (!$sah) {
            Log::error('MPWA: URL gateway ditolak (bukan https atau host di luar allow-list)', ['host' => $host]);

            return null;
        }

        return $url;
    }

    /** Nama host gateway untuk ditampilkan (bukan rahasia). */
    public static function hostGateway(): string
    {
        return (string) parse_url((string) config('services.mpwa.url'), PHP_URL_HOST);
    }

    /**
     * Send a single WhatsApp text message.
     * Returns true on success, false on failure (fire-and-forget — never blocks user).
     * Optionally pass api_key/sender to override DB settings (used for test-from-form).
     */
    public static function send(
        string $to,
        string $message,
        string $sender = '',
        string $apiKey = ''
    ): bool {
        return self::kirimPesan($to, $message, $sender, $apiKey)['ok'];
    }

    /**
     * Kirim satu pesan dan kembalikan hasil yang aman ditampilkan:
     * ['ok' => bool, 'alasan' => ?string]. Alasan selalu kalimat umum; isi
     * respons gateway dan pesan exception hanya masuk log server.
     *
     * @param  list<array{displayText: string, id: string}>  $tombol
     * @return array{ok: bool, alasan: ?string}
     */
    public static function kirimPesan(
        string $to,
        string $message,
        string $sender = '',
        string $apiKey = '',
        array $tombol = [],
        int $timeout = 12
    ): array {
        $number = normalizeWa($to);   // 08xx/8xx/+62/0062 → 62xxxx (format wajib WhatsApp)
        if (!$number || strlen($number) < 10) {
            return ['ok' => false, 'alasan' => 'Nomor tujuan tidak valid.'];
        }

        $apiKey = $apiKey ?: self::apiKey();
        $sender  = $sender  ?: self::sender();

        if (!$apiKey || !$sender) {
            Log::warning('MPWA send skipped: API key or sender not configured.');
            return ['ok' => false, 'alasan' => 'Gateway WhatsApp belum dikonfigurasi.'];
        }

        $payload = [
            'api_key' => $apiKey,
            'sender'  => $sender,
            'number'  => $number,
            'message' => $message,
            'footer'  => self::footer(),
        ];
        if ($tombol !== []) {
            $payload['buttons'] = $tombol;
        }

        return self::kirimKeGateway($tombol !== [] ? '/send-button-message' : '/send-message', $payload, $timeout);
    }

    /**
     * Satu-satunya request HTTP ke gateway. Redirect TIDAK diikuti: host
     * tepercaya yang mengalihkan ke tempat lain tidak boleh membawa kunci API.
     *
     * @return array{ok: bool, alasan: ?string}
     */
    private static function kirimKeGateway(string $endpoint, array $payload, int $timeout): array
    {
        // Instalasi demo berisi nomor fiktif dan bisa dicoba siapa saja:
        // tidak ada satu pesan pun yang boleh keluar, bahkan bila kunci diisi.
        if (config('app.demo')) {
            Log::info('MPWA: pengiriman dilewati karena mode demo', ['to' => samarkanWa($payload['number'] ?? '')]);

            return ['ok' => false, 'alasan' => 'Situs demo: pengiriman WhatsApp dinonaktifkan.'];
        }

        $baseUrl = self::baseUrl();
        if ($baseUrl === null || !in_array($endpoint, self::ENDPOINT, true)) {
            return ['ok' => false, 'alasan' => 'Gateway WhatsApp tidak diizinkan. Hubungi admin platform.'];
        }

        $tujuan = samarkanWa($payload['number'] ?? '');
        try {
            $resp = Http::timeout($timeout)->withoutRedirecting()->post($baseUrl.$endpoint, $payload);
            $body = $resp->json();
            // Semantik lama dipertahankan: status "truthy" (true, 1, "true",
            // "success") atau ada id pesan = terkirim. Hanya string "false"/"0"
            // yang kini dibaca sebagai gagal, bukan truthy.
            $status = is_array($body) ? ($body['status'] ?? false) : false;
            $statusOk = is_string($status)
                ? (filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($status !== ''))
                : (bool) $status;
            $ok = $resp->successful() && is_array($body) && ($statusOk || isset($body['id']));

            if (!$ok) {
                Log::warning('MPWA send failed', [
                    'to' => $tujuan,
                    'http' => $resp->status(),
                    'response' => is_array($body) ? mb_substr((string) ($body['message'] ?? ''), 0, 200) : null,
                ]);

                return ['ok' => false, 'alasan' => 'Gateway menolak pesan. Periksa API key, nomor pengirim, dan nomor tujuan.'];
            }

            return ['ok' => true, 'alasan' => null];
        } catch (\Throwable $e) {
            Log::error('MPWA send exception', ['to' => $tujuan, 'error' => redaksiDiagnostik($e->getMessage())]);

            return ['ok' => false, 'alasan' => 'Gateway WhatsApp tidak dapat dihubungi.'];
        }
    }

    /** Check if a specific auto-notification is enabled (default: true if not set). */
    public static function isEnabled(string $aturanKey): bool
    {
        $val = AppSetting::nilai($aturanKey);
        return $val === null || $val === '1'; // default on
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOMAIN NOTIFICATION TEMPLATES
    // ─────────────────────────────────────────────────────────────────────────

    /**

     * Konfirmasi pembayaran iuran sampah.
     */
    public static function notifyBayarSampah(
        string $noWa, string $nama, string $rt, 
        string $periode, int $jumlah, string $noBukti, string $tanggal
    ): bool {
        if (!$noWa || !self::isEnabled('notif_bayar_sampah')) return false;
        $msg = "✅ *BUKTI PEMBAYARAN IURAN SAMPAH*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "📋 No. Bukti  : *{$noBukti}*\n"
             . "📅 Tanggal    : {$tanggal}\n"
             . "👤 Nama       : {$nama}\n"
             . "🏠 RT         : {$rt}\n"
             . "📌 Periode    : {$periode}\n"
             . "💵 Dibayar    : *Rp " . number_format($jumlah, 0, ',', '.') . "*\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "_Terima kasih atas pembayaran Anda. Bukti ini sah sebagai tanda terima._\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Konfirmasi pembayaran iuran padaringan.
     */
    public static function notifyBayarPadaringan(
        string $noWa, string $nama, string $rt,
        string $periode, int $jumlah, string $noBukti, string $tanggal
    ): bool {
        if (!$noWa || !self::isEnabled('notif_bayar_padaringan')) return false;
        $msg = "✅ *BUKTI PEMBAYARAN IURAN PADARINGAN*\n"

             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "📋 No. Bukti  : *{$noBukti}*\n"
             . "📅 Tanggal    : {$tanggal}\n"
             . "👤 Nama       : {$nama}\n"
             . "🏠 RT         : {$rt}\n"
             . "📌 Periode    : {$periode}\n"
             . "💵 Dibayar    : *Rp " . number_format($jumlah, 0, ',', '.') . "*\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "_Terima kasih atas kepercayaan dan partisipasi Anda._\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Notifikasi pendaftaran berhasil dikirim (menunggu verifikasi).
     */
    public static function notifyPendaftaranDiterima(
        string $noWa, string $nama, string $rt
    ): bool {
        if (!$noWa || !self::isEnabled('notif_daftar_submitted')) return false;
        $msg = "🏘️ *PENDAFTARAN WARGA DITERIMA*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Halo *{$nama}*,\n\n"
             . "Pengajuan pendaftaran Anda sebagai warga RT {$rt} telah kami terima "
             . "dan akan diperiksa pengurus RW.\n\n"
             . "Bila data Anda ternyata sudah terdaftar, silakan masuk ke portal atau "
             . "gunakan menu Lupa Username / PIN.\n\n"
             . "_Jika ada pertanyaan, silakan hubungi pengurus RW secara langsung._\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Notifikasi pendaftaran disetujui — lengkap dengan info akun.
     */
    public static function notifyPendaftaranDisetujui(
        string $noWa, string $nama, string $rt, string $username, string $pin = '123456'
    ): bool {
        if (!$noWa || !self::isEnabled('notif_daftar_disetujui')) return false;
        $msg = "🎉 *PENDAFTARAN DISETUJUI!*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Selamat *{$nama}*!\n\n"
             . "Pendaftaran Anda sebagai warga RT {$rt} telah *DISETUJUI* oleh pengurus RW.\n\n"
             . "🔑 *Info Akun Portal:*\n"
             . "   Username : *{$username}*\n"
             . "   PIN      : *{$pin}*\n\n"
             . "⚠️ *SIMPAN PIN INI BAIK-BAIK!*\n"
             . "PIN Anda bersifat unik dan rahasia. Jangan bagikan kepada siapapun.\n\n"
             . "🌐 Akses portal di: " . alamatPortal() . "\n\n"
             . "_Jika lupa PIN, gunakan fitur 'Lupa PIN' di halaman login._\n\n"
             . "🏘️ Selamat bergabung di komunitas " . namaAplikasi() . "!\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Notifikasi pendaftaran ditolak beserta alasannya.
     */
    public static function notifyPendaftaranDitolak(
        string $noWa, string $nama, string $alasan
    ): bool {
        if (!$noWa || !self::isEnabled('notif_daftar_ditolak')) return false;
        $msg = "❌ *PENDAFTARAN TIDAK DISETUJUI*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Halo *{$nama}*,\n\n"
             . "Mohon maaf, pendaftaran Anda tidak dapat kami setujui saat ini.\n\n"
             . "📋 *Alasan:* {$alasan}\n\n"
             . "_Jika merasa ada kesalahan, silakan hubungi pengurus RW 10 secara langsung "
             . "atau ajukan ulang pendaftaran dengan data yang benar._\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Kirim reminder tunggakan ke satu warga.
     */
    public static function sendReminder(
        string $noWa, string $nama, string $rt,
        array $tunggakan // ['sampah' => 'JAN, FEB', 'padaringan' => 'MAR']
    ): bool {
        if (!$noWa) return false;
        $detail = '';
        if (!empty($tunggakan['sampah'])) $detail .= "   🗑️ Iuran Sampah    : {$tunggakan['sampah']}\n";
        if (!empty($tunggakan['padaringan'])) $detail .= "   🍳 Iuran Padaringan: {$tunggakan['padaringan']}\n";

        $msg = "⚠️ *REMINDER TUNGGAKAN IURAN*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Yth. *{$nama}* (RT {$rt}),\n\n"
             . "Kami menginformasikan bahwa masih terdapat tunggakan iuran:\n\n"
             . $detail
             . "\nMohon kiranya dapat segera melakukan pembayaran kepada petugas RT "
             . "atau datang langsung ke kantor RW.\n\n"
             . "_Terima kasih atas perhatian dan partisipasi Anda dalam menjaga kebersihan "
             . "dan kebersamaan warga RW 10._\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }

    /**
     * Kode pemulihan PIN sekali pakai. Tidak memuat username maupun PIN:
     * username baru ditampilkan setelah kode terbukti benar.
     */
    public static function notifyKodePemulihan(string $noWa, string $nama, string $kode): bool
    {
        if (!$noWa) return false;
        $msg = "🔐 *KODE PEMULIHAN PIN*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Halo *{$nama}*,\n\n"
             . "Kode pemulihan Anda: *{$kode}*\n\n"
             . "Masukkan kode ini di halaman Lupa Username / PIN untuk membuat PIN baru. "
             . "Kode berlaku " . \App\Models\PemulihanPin::MASA_BERLAKU_MENIT . " menit dan hanya bisa dipakai sekali.\n\n"
             . "⚠️ Jangan bagikan kode ini kepada siapa pun, termasuk yang mengaku pengurus. "
             . "Bila Anda tidak memintanya, abaikan pesan ini; PIN Anda tidak berubah.\n\n"
             . "🌐 " . alamatPortal() . "\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }
}
