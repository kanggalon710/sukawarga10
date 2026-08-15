<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AppSetting;

/**
 * MpwaService — Production WhatsApp notification service
 * Uses mpwa.jabnet.id API exclusively.
 * API Key & sender are embedded; only sender can be overridden per-call.
 */
class MpwaService
{
    const BASE_URL = 'https://mpwa.jabnet.id';

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

    /** Read API key from DB, fallback empty (will fail gracefully). */
    public static function apiKey(): string
    {
        return AppSetting::nilai('mpwa_api_key') ?? '';
    }

    /** Read default sender from DB. */
    public static function sender(): string
    {
        return AppSetting::nilai('mpwa_sender') ?? '';
    }

    /** Base URL gateway; bisa ditimpa lewat AppSetting `mpwa_api_url`. */
    public static function baseUrl(): string
    {
        return rtrim(AppSetting::nilai('mpwa_api_url') ?: self::BASE_URL, '/');
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
        $number = normalizeWa($to);   // 08xx/8xx/+62/0062 → 62xxxx (format wajib WhatsApp)
        if (!$number || strlen($number) < 10) return false;

        $apiKey = $apiKey ?: self::apiKey();
        $sender  = $sender  ?: self::sender();

        if (!$apiKey || !$sender) {
            Log::warning('MPWA send skipped: API key or sender not configured.');
            return false;
        }

        try {
            $resp = Http::timeout(12)->post(self::baseUrl() . '/send-message', [
                'api_key' => $apiKey,
                'sender'  => $sender,
                'number'  => $number,
                'message' => $message,
                'footer'  => self::footer(),
            ]);

            $body = $resp->json();
            $ok = $resp->successful() && (($body['status'] ?? false) || isset($body['id']));

            if (!$ok) {
                Log::warning('MPWA send failed', ['to' => $number, 'response' => $body]);
            }
            return $ok;

        } catch (\Exception $e) {
            Log::error('MPWA send exception', ['to' => $number, 'error' => $e->getMessage()]);
            return false;
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
             . "Pendaftaran Anda sebagai warga RT {$rt} telah kami terima.\n\n"
             . "⏳ *Status:* Menunggu Verifikasi\n\n"
             . "Tim pengurus RW akan segera memverifikasi data Anda. "
             . "Anda akan mendapat notifikasi WhatsApp setelah proses verifikasi selesai.\n\n"
             . "_Jika ada pertanyaan, silakan hubungi pengurus RW 10 secara langsung._\n\n"
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
     * Kirim kredensial akun saat lupa PIN/username.
     */
    public static function notifyForgotCredentials(
        string $noWa, string $nama, string $username, string $newPin
    ): bool {
        if (!$noWa) return false;
        $msg = "🔐 *RESET KREDENSIAL AKUN*\n"
             . self::kop() . "\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Halo *{$nama}*,\n\n"
             . "Berikut data akun Anda yang telah direset:\n\n"
             . "🔑 *Info Akun:*\n"
             . "   Username : *{$username}*\n"
             . "   PIN Baru : *{$newPin}*\n\n"
             . "⚠️ *SIMPAN PIN INI BAIK-BAIK!*\n"
             . "Jangan bagikan PIN kepada siapapun.\n\n"
             . "🌐 Login di: " . alamatPortal() . "\n\n"
             . self::tandaTangan();
        return self::send($noWa, $msg);
    }
}
