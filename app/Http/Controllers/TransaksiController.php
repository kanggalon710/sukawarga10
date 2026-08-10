<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\IuranSampah;
use App\Models\IuranPadaringan;
use App\Models\Keluarga;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Services\MpwaService;

class TransaksiController extends Controller
{
    // --- IURAN SAMPAH (PER MINGGU) ---
    public function sampahIndex(Request $request) {
        $tahun = $request->tahun ?? date('Y');
        $bulan = $request->bulan ?? date('n');
        $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
        $bulanNamaAll = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $bulanKey = $bulanAll[(int)$bulan - 1] ?? 'JAN';
        $bulanNama = $bulanNamaAll[(int)$bulan - 1] ?? 'Januari';

        $keluargas = Keluarga::where('status', 'aktif')
            ->where('ikutSampah', true)
            ->orderBy('rt')
            ->orderBy('nama')
            ->get();
        $iuran = IuranSampah::where('tahun', $tahun)->get()->keyBy('keluarga_id');
        $tarifSampah = (int)(AppSetting::where('key', 'tarif_sampah')->value('value') ?? 5000);

        $riwayat = Transaksi::where('kas', 'sampah')->where('jenis', 'masuk')->where('voided', false)
            ->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan)
            ->orderByDesc('tanggal')->orderByDesc('created_at')->get();

        return view('billing.sampah', compact('keluargas', 'iuran', 'tahun', 'bulan', 'bulanKey', 'bulanNama', 'tarifSampah', 'riwayat'));
    }

    public function sampahStore(Request $request, $keluarga_id) {
        $request->validate([
            'tahun' => 'required|integer',
            'bulan_key' => 'required|string',
            'minggu' => 'required|array|min:1',
            'tanggal_bayar' => 'required|date',
        ]);

        $tahun = $request->tahun;
        $bulanKey = $request->bulan_key;
        $mingguList = $request->minggu;
        $tarifSampah = (int)(AppSetting::where('key', 'tarif_sampah')->value('value') ?? 5000);
        $totalBayar = count($mingguList) * $tarifSampah;
        $keluarga = Keluarga::findOrFail($keluarga_id);

        $iuran = IuranSampah::firstOrNew(['keluarga_id' => $keluarga_id, 'tahun' => $tahun]);
        $existingWeeks = $iuran->weeks ?? [];
        $existingDates = $iuran->weekDates ?? [];

        $periodLabels = [];
        foreach($mingguList as $m) {
            $key = $bulanKey . '-M' . $m;
            $existingWeeks[$key] = 'lunas';
            $existingDates[$key] = $request->tanggal_bayar;
            $periodLabels[] = $bulanKey . ' Minggu-' . $m;
        }
        $iuran->weeks = $existingWeeks;
        $iuran->weekDates = $existingDates;
        $iuran->save();

        $noBukti = 'KS-' . date('Ymd', strtotime($request->tanggal_bayar)) . '-' . strtoupper(substr(uniqid(), -4));
        Transaksi::create([
            'transaksi_id' => $noBukti,
            'tanggal' => $request->tanggal_bayar,
            'jenis' => 'masuk',
            'kas' => 'sampah',
            'keterangan' => 'Iuran Sampah ' . implode(', ', $periodLabels) . ' — ' . $keluarga->nama . ' (RT ' . $keluarga->rt . ')',
            'jumlah' => $totalBayar,
            'refKeluargaId' => $keluarga_id,
            'operator' => auth()->user()->username ?? 'admin',
        ]);

        // Send WA receipt (fire-and-forget — never delays response)
        $noWa = $keluarga->noHP ?? $keluarga->no_wa ?? '';
        if ($noWa) {
            MpwaService::notifyBayarSampah(
                $noWa,
                $keluarga->nama ?? $keluarga->kepala_keluarga ?? '-',
                $keluarga->rt ?? '-',
                implode(', ', $periodLabels),
                (int) $totalBayar,
                $noBukti,
                date('d/m/Y', strtotime($request->tanggal_bayar))
            );
        }

        return back()->with('success', 'Pembayaran berhasil dicatat. No. Bukti: ' . $noBukti . ' — Rp ' . number_format($totalBayar,0,',','.') . (($noWa) ? ' ✅ Bukti dikirim ke WA warga.' : ''));
    }

    // --- IURAN PADARINGAN (PER BULAN) ---
    public function padaringanIndex(Request $request) {
        $tahun = $request->tahun ?? date('Y');
        $keluargas = Keluarga::where('status', 'aktif')->where('ikutPadaringan', true)->orderBy('rt')->orderBy('nama')->get();
        $iuran = IuranPadaringan::where('tahun', $tahun)->get()->keyBy('keluarga_id');
        $tarifPadaringan = (int)(AppSetting::where('key', 'tarif_padaringan')->value('value') ?? 15000);

        $riwayat = Transaksi::where('kas', 'padaringan')->where('jenis', 'masuk')->where('voided', false)
            ->whereYear('tanggal', $tahun)->orderByDesc('tanggal')->orderByDesc('created_at')->limit(20)->get();

        return view('billing.padaringan', compact('keluargas', 'iuran', 'tahun', 'tarifPadaringan', 'riwayat'));
    }

    public function padaringanStore(Request $request, $keluarga_id) {
        $request->validate([
            'tahun' => 'required|integer',
            'bulans' => 'required|array|min:1',
            'tanggal_bayar' => 'required|date',
        ]);

        $tahun = $request->tahun;
        $bulanList = $request->bulans;
        $tarifPadaringan = (int)(AppSetting::where('key', 'tarif_padaringan')->value('value') ?? 15000);
        $nominal = $request->nominal ?? $tarifPadaringan;
        $totalBayar = count($bulanList) * $nominal;
        $keluarga = Keluarga::findOrFail($keluarga_id);

        $iuran = IuranPadaringan::firstOrNew(['keluarga_id' => $keluarga_id, 'tahun' => $tahun]);
        $existingMonths = $iuran->months ?? [];
        $existingDates = $iuran->monthDates ?? [];

        foreach($bulanList as $bulan) {
            $existingMonths[$bulan] = true;
            $existingDates[$bulan] = $request->tanggal_bayar;
        }
        $iuran->months = $existingMonths;
        $iuran->monthDates = $existingDates;
        $iuran->save();

        $noBukti = 'KP-' . date('Ymd', strtotime($request->tanggal_bayar)) . '-' . strtoupper(substr(uniqid(), -4));
        Transaksi::create([
            'transaksi_id' => $noBukti,
            'tanggal' => $request->tanggal_bayar,
            'jenis' => 'masuk',
            'kas' => 'padaringan',
            'keterangan' => 'Iuran Padaringan ' . implode(', ', $bulanList) . ' ' . $tahun . ' — ' . $keluarga->nama . ' (RT ' . $keluarga->rt . ')',
            'jumlah' => $totalBayar,
            'refKeluargaId' => $keluarga_id,
            'operator' => auth()->user()->username ?? 'admin',
        ]);

        // Send WA receipt (fire-and-forget)
        $noWa = $keluarga->noHP ?? $keluarga->no_wa ?? '';
        if ($noWa) {
            MpwaService::notifyBayarPadaringan(
                $noWa,
                $keluarga->nama ?? $keluarga->kepala_keluarga ?? '-',
                $keluarga->rt ?? '-',
                implode(', ', $bulanList) . ' ' . $tahun,
                (int) $totalBayar,
                $noBukti,
                date('d/m/Y', strtotime($request->tanggal_bayar))
            );
        }

        return back()->with('success', 'Pembayaran berhasil dicatat. No. Bukti: ' . $noBukti . ' — Rp ' . number_format($totalBayar,0,',','.') . (($noWa) ? ' ✅ Bukti dikirim ke WA warga.' : ''));
    }

    // --- VOID / ROLLBACK TRANSAKSI (SUPER ADMIN) ---
    public function voidTransaksi(Request $request, $id) {
        $user = auth()->user();
        if (!$user->canVoid()) {
            return back()->with('error', 'Anda tidak memiliki akses untuk membatalkan transaksi.');
        }

        $request->validate(['void_reason' => 'required|string|min:5']);

        $transaksi = Transaksi::findOrFail($id);
        if ($transaksi->voided) {
            return back()->with('error', 'Transaksi ini sudah dibatalkan sebelumnya.');
        }

        // Rollback iuran status if applicable
        $this->rollbackIuranStatus($transaksi);

        // Mark as voided
        $transaksi->update([
            'voided' => true,
            'void_reason' => $request->void_reason,
            'void_by' => $user->username,
            'void_at' => now(),
        ]);

        // Audit log
        try {
            AuditLog::create([
                'log_id' => 'LOG-' . uniqid(),
                'tanggal' => now(),
                'aksi' => 'void',
                'collection' => 'transaksi',
                'recordId' => $transaksi->transaksi_id,
                'deskripsi' => 'Void ' . $transaksi->transaksi_id . ': ' . $transaksi->keterangan . ' — ' . $request->void_reason,
                'operator' => $user->username,
            ]);
        } catch (\Exception $e) {}

        return back()->with('success', 'Transaksi ' . $transaksi->transaksi_id . ' berhasil dibatalkan (VOID).');
    }

    private function rollbackIuranStatus(Transaksi $trx) {
        if (!$trx->refKeluargaId) return;

        $tahun = date('Y', strtotime($trx->tanggal));

        if ($trx->kas === 'sampah' && $trx->jenis === 'masuk') {
            $iuran = IuranSampah::where('keluarga_id', $trx->refKeluargaId)->where('tahun', $tahun)->first();
            if ($iuran) {
                $weeks = $iuran->weeks ?? [];
                $weekDates = $iuran->weekDates ?? [];
                // Parse period from keterangan: "Iuran Sampah JAN Minggu-1, JAN Minggu-2 — Nama"
                if (preg_match_all('/([A-Z]{3})\s*Minggu-(\d)/', $trx->keterangan, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $key = $m[1] . '-M' . $m[2];
                        unset($weeks[$key], $weekDates[$key]);
                    }
                }
                $iuran->weeks = $weeks;
                $iuran->weekDates = $weekDates;
                $iuran->save();
            }
        } elseif ($trx->kas === 'padaringan' && $trx->jenis === 'masuk') {
            $iuran = IuranPadaringan::where('keluarga_id', $trx->refKeluargaId)->where('tahun', $tahun)->first();
            if ($iuran) {
                $months = $iuran->months ?? [];
                $monthDates = $iuran->monthDates ?? [];
                $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
                foreach ($bulanAll as $b) {
                    if (str_contains($trx->keterangan, $b)) {
                        unset($months[$b], $monthDates[$b]);
                    }
                }
                $iuran->months = $months;
                $iuran->monthDates = $monthDates;
                $iuran->save();
            }
        }
    }

    // --- MANAJEMEN KAS ---
    public function pengeluaranIndex(Request $request) {
        $tahun = $request->tahun ?? date('Y');
        $query = Transaksi::where('jenis', 'keluar')->where('voided', false)->orderByDesc('tanggal');
        if ($request->kas) $query->where('kas', $request->kas);
        if ($request->bulan) $query->whereMonth('tanggal', $request->bulan);
        $query->whereYear('tanggal', $tahun);
        $transaksi = $query->get();
        $totalPengeluaran = $transaksi->sum('jumlah');
        return view('kas.pengeluaran', compact('transaksi', 'totalPengeluaran'));
    }

    public function pengeluaranStore(Request $request) {
        $validated = $request->validate([
            'kas' => 'required', 'tanggal' => 'required|date',
            'jumlah' => 'required|numeric|min:1', 'keterangan' => 'required', 'kategori' => 'nullable',
        ]);
        $noBukti = 'BK-' . date('Ymd', strtotime($validated['tanggal'])) . '-' . strtoupper(substr(uniqid(), -4));
        $validated['transaksi_id'] = $noBukti;
        $validated['jenis'] = 'keluar';
        $validated['operator'] = auth()->user()->username ?? 'admin';
        Transaksi::create($validated);
        return back()->with('success', 'Pengeluaran dicatat. No. Bukti: ' . $noBukti);
    }

    public function setorIndex() { return view('kas.setor'); }
    public function sumbanganIndex() { return view('kas.sumbangan'); }
}
