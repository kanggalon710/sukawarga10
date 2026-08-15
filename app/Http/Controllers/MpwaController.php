<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Keluarga;
use App\Models\IuranSampah;
use App\Models\IuranPadaringan;
use App\Models\AppSetting;
use App\Services\AuditLogService;
use App\Services\MpwaService;

class MpwaController extends Controller
{
    // Base URL & footer gateway TIDAK didefinisikan ulang di sini -
    // sumber tunggalnya MpwaService (lihat MpwaService::baseUrl() & ::footer()).

    /**
     * Template bawaan halaman broadcast.
     *
     * Method, bukan konstanta, karena isinya menyebut nama komunitas yang kini
     * datang dari Pengaturan (namaAplikasi()) dan konstanta tidak boleh memanggil
     * fungsi. Template yang sudah disimpan pengurus tidak tersentuh perubahan ini.
     */
    private static function defaultTemplates(): array
    {
        $pengurus = 'Pengurus ' . namaAplikasi();
        $warga = 'warga ' . namaAplikasi();

        return [
            [
                'id'      => 'reminder',
                'nama'    => 'Reminder Tunggakan',
                'icon'    => '🔔',
                'deskripsi' => 'Pengingat iuran yang belum dibayar',
                'isi'     => "Assalamualaikum Wr. Wb.\n\nKepada Bapak/Ibu {{nama}} (RT {{rt}}),\n\nKami menginformasikan bahwa masih terdapat tunggakan iuran warga.\n\nMohon kiranya dapat melakukan pembayaran segera kepada petugas RT atau bendahara RW.\n\nTerima kasih atas perhatian dan kerjasamanya. 🙏\n\nHormat kami,\n" . $pengurus,
                'builtin' => true,
            ],
            [
                'id'      => 'info',
                'nama'    => 'Info Kegiatan RW',
                'icon'    => '📅',
                'deskripsi' => 'Informasi kegiatan/acara RW',
                'isi'     => "Assalamualaikum Wr. Wb.\n\nDengan ini kami sampaikan kepada seluruh {$warga}:\n\n📅 Kegiatan  : ...\n📆 Hari/Tgl  : ...\n🕐 Waktu     : ...\n📍 Tempat    : ...\n\nDimohon kehadiran dan partisipasi seluruh warga.\n\nHormat kami,\n" . $pengurus,
                'builtin' => true,
            ],
            [
                'id'      => 'umum',
                'nama'    => 'Pengumuman Umum',
                'icon'    => '📢',
                'deskripsi' => 'Pengumuman untuk seluruh warga',
                'isi'     => "Assalamualaikum Wr. Wb.\n\n📢 PENGUMUMAN\n\nKepada seluruh {$warga}:\n\n...\n\nDemikian pengumuman ini disampaikan. Terima kasih atas perhatiannya.\n\nHormat kami,\n" . $pengurus,
                'builtin' => true,
            ],
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function getAllTemplates(): array
    {
        $custom = json_decode(AppSetting::nilai('mpwa_templates') ?? '[]', true) ?: [];
        return array_merge(self::defaultTemplates(), $custom);
    }

    private function getCustomTemplates(): array
    {
        return json_decode(AppSetting::nilai('mpwa_templates') ?? '[]', true) ?: [];
    }

    private function saveCustomTemplates(array $list): void
    {
        AppSetting::simpan('mpwa_templates', json_encode(array_values($list)));
    }

    // ── Index ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $tahun    = date('Y');
        $bulanIni = (int) date('m');
        $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
        $bulanKey = $bulanAll[$bulanIni - 1] ?? 'JAN';

        $kkSampah        = Keluarga::where('status','aktif')->where('ikutSampah', true)->count();
        $kkBayarSampah   = IuranSampah::where('tahun', $tahun)->get()->filter(fn($i) =>
            collect($i->weeks ?? [])->contains(fn($v,$k) => str_starts_with($k, $bulanKey) && $v === 'lunas')
        )->count();
        $tunggakanSampah = max(0, $kkSampah - $kkBayarSampah);

        $kkPadaringan        = Keluarga::where('status','aktif')->where('ikutPadaringan', true)->count();
        $kkBayarPadaringan   = IuranPadaringan::where('tahun', $tahun)->get()->filter(fn($i) => $i->months[$bulanKey] ?? false)->count();
        $tunggakanPadaringan = max(0, $kkPadaringan - $kkBayarPadaringan);

        $totalTunggakan = $tunggakanSampah + $tunggakanPadaringan;
        $wargaList      = Keluarga::where('status','aktif')->whereNotNull('noHP')->where('noHP','!=','')->orderBy('rt')->get();
        $settings       = AppSetting::semuaEfektif();
        $templates      = $this->getAllTemplates();

        // Active senders — show DB sender first, then others
        $savedSender = $settings['mpwa_sender'] ?? '';
        $senders = [];
        if ($savedSender) $senders[$savedSender] = $savedSender . ' (Terkonfigurasi)';
        foreach (['6289630599885','6285121950670','6281399101416','628235850696'] as $n) {
            if (!isset($senders[$n])) $senders[$n] = $n;
        }

        return view('admin.mpwa', compact(
            'totalTunggakan','tunggakanSampah','tunggakanPadaringan',
            'wargaList','settings','senders','savedSender','templates'
        ));
    }

    // ── Test Connection ───────────────────────────────────────────────────────
    public function testConnection(Request $request)
    {
        $apiKey = $request->input('api_key') ?: MpwaService::apiKey();
        $sender = $request->input('sender')  ?: MpwaService::sender();
        $testNo = normalizeWa($request->input('test_number', ''));

        if (!$apiKey) return response()->json(['success' => false, 'message' => 'API Key belum dikonfigurasi di Pengaturan.']);
        if (!$sender) return response()->json(['success' => false, 'message' => 'Nomor Pengirim belum dikonfigurasi di Pengaturan.']);
        if (!$testNo) return response()->json(['success' => false, 'message' => 'Nomor tujuan test tidak valid.']);

        $resp = Http::timeout(20)->post(MpwaService::baseUrl() . '/send-message', [
            'api_key' => $apiKey,
            'sender'  => $sender,
            'number'  => $testNo,
            'message' => "✅ *Test Koneksi MPWA*\n\n" . namaAplikasi() . " berhasil terhubung ke gateway WhatsApp!\n_Pesan ini dikirim otomatis oleh sistem._",
            'footer'  => MpwaService::footer(),
        ]);

        $body = $resp->json();
        $ok = $resp->successful() && ($body['status'] === true || $body['status'] === 'true' || isset($body['id']));

        if ($ok) {
            AuditLogService::log('mpwa_test', 'mpwa', "Test koneksi MPWA berhasil ke: $testNo via sender $sender");
            return response()->json(['success' => true, 'message' => "Pesan test berhasil dikirim ke $testNo!"]);
        }
        return response()->json(['success' => false, 'message' => $body['message'] ?? 'Gagal. Cek API Key dan nomor Sender.']);
    }

    // ── Broadcast ─────────────────────────────────────────────────────────────
    public function broadcast(Request $request)
    {
        $request->validate([
            'pesan'  => 'required|string|max:4000',
            'target' => 'required|string',
            'sender' => 'required|string',
        ]);

        $pesan         = $request->pesan;
        $target        = $request->target;
        $sender        = $request->sender;
        $customNumbers = $request->input('custom_numbers', []);
        $buttonTexts   = array_slice(array_filter($request->input('buttons', []), fn($b) => !empty(trim($b))), 0, 3);

        $apiKey = MpwaService::apiKey();
        if (!$apiKey) return response()->json(['success' => false, 'message' => 'API Key MPWA belum dikonfigurasi. Buka Pengaturan → WhatsApp API.']);

        // Resolve recipients
        if ($target === 'custom' && !empty($customNumbers)) {
            $customNumbers = array_values(array_filter(array_map('normalizeWa', $customNumbers)));
            $recipients = Keluarga::where('status','aktif')
                ->whereIn('noHP', $customNumbers)
                ->get();
        } else {
            $query = Keluarga::where('status','aktif')->whereNotNull('noHP')->where('noHP','!=','');
            if (str_starts_with($target, 'rt')) {
                $rtNum = str_pad(ltrim($target, 'rt'), 2, '0', STR_PAD_LEFT);
                $query->where('rt', $rtNum);
            }
            $recipients = $query->get();
        }

        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada warga dengan nomor HP untuk target tersebut.']);
        }

        // Build button objects for API
        $buttons = [];
        foreach ($buttonTexts as $i => $text) {
            $buttons[] = ['displayText' => $text, 'id' => 'btn_' . ($i + 1)];
        }

        $sent = 0; $failed = 0; $errors = [];

        foreach ($recipients as $warga) {
            $number = normalizeWa($warga->noHP);
            if (!$number || strlen($number) < 10) { $failed++; continue; }

            $msg = str_replace(
                ['{{nama}}', '{{rt}}', '{{tunggakan}}'],
                [$warga->kepala_keluarga ?? $warga->nama ?? '-', $warga->rt ?? '-', '...'],
                $pesan
            );

            try {
                $payload = [
                    'api_key' => $apiKey,
                    'sender'  => $sender,
                    'number'  => $number,
                    'message' => $msg,
                    'footer'  => MpwaService::footer(),
                ];

                // Use button endpoint if buttons exist
                if (!empty($buttons)) {
                    $payload['buttons'] = $buttons;
                    $endpoint = '/send-button-message';
                } else {
                    $endpoint = '/send-message';
                }

                $resp = Http::timeout(15)->post(MpwaService::baseUrl() . $endpoint, $payload);
                $body = $resp->json();
                if ($resp->successful() && (($body['status'] ?? false) || isset($body['id']))) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = ($warga->kepala_keluarga ?? '?') . ': ' . ($body['message'] ?? 'error');
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ($warga->kepala_keluarga ?? '?') . ': ' . $e->getMessage();
            }

            usleep(300000); // 300ms anti-rate-limit
        }

        AuditLogService::log('mpwa_broadcast', 'mpwa', "Broadcast ke $target: $sent berhasil, $failed gagal.");
        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => $recipients->count(),
            'errors'  => array_slice($errors, 0, 5),
            'message' => "$sent pesan berhasil dikirim, $failed gagal dari {$recipients->count()} target.",
        ]);
    }

    // ── Template: Save (add or edit) ──────────────────────────────────────────
    public function saveTemplate(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'isi'  => 'required|string|max:4000',
        ]);

        $list = $this->getCustomTemplates();
        $id   = $request->input('id');

        $entry = [
            'id'        => $id ?: 'c_' . time() . '_' . rand(100,999),
            'nama'      => $request->nama,
            'icon'      => $request->input('icon', '📝'),
            'deskripsi' => $request->input('deskripsi', ''),
            'isi'       => $request->isi,
            'builtin'   => false,
        ];

        if ($id) {
            $list = array_map(fn($t) => $t['id'] === $id ? $entry : $t, $list);
        } else {
            $list[] = $entry;
        }

        $this->saveCustomTemplates($list);
        AuditLogService::log('mpwa_template_save', 'mpwa', "Template '{$entry['nama']}' disimpan.");

        return response()->json([
            'success'   => true,
            'templates' => $this->getAllTemplates(),
        ]);
    }

    // ── Template: Delete ──────────────────────────────────────────────────────
    public function deleteTemplate(Request $request)
    {
        $id   = $request->input('id');
        $list = $this->getCustomTemplates();
        $list = array_filter($list, fn($t) => $t['id'] !== $id);
        $this->saveCustomTemplates($list);
        AuditLogService::log('mpwa_template_delete', 'mpwa', "Template ID $id dihapus.");

        return response()->json([
            'success'   => true,
            'templates' => $this->getAllTemplates(),
        ]);
    }

    // ── Aturan Otomatis: Save ─────────────────────────────────────────────────
    public function saveAturan(Request $request)
    {
        $key   = $request->input('key');
        $value = $request->input('value', '1');

        $allowed = [
            'notif_bayar_sampah', 'notif_bayar_padaringan',
            'notif_daftar_submitted', 'notif_daftar_disetujui', 'notif_daftar_ditolak',
        ];
        if (!in_array($key, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Key tidak valid.']);
        }

        AppSetting::simpan($key, $value);
        return response()->json(['success' => true, 'key' => $key, 'value' => $value]);
    }
}
