<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class BukuKasController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->get('tahun', date('Y'));
        $bulan = $request->get('bulan', '');
        $kas = $request->get('kas', '');

        $query = Transaksi::whereYear('tanggal', $tahun);
        if ($bulan) $query->whereMonth('tanggal', $bulan);
        if ($kas) $query->where('kas', $kas);

        $transaksi = $query->orderByDesc('tanggal')->orderByDesc('created_at')->get();

        $totalMasuk = Transaksi::whereYear('tanggal', $tahun)->where('voided', false)
            ->when($bulan, fn($q) => $q->whereMonth('tanggal', $bulan))
            ->when($kas, fn($q) => $q->where('kas', $kas))
            ->where('jenis', 'masuk')->sum('jumlah');
        $totalKeluar = Transaksi::whereYear('tanggal', $tahun)->where('voided', false)
            ->when($bulan, fn($q) => $q->whereMonth('tanggal', $bulan))
            ->when($kas, fn($q) => $q->where('kas', $kas))
            ->where('jenis', 'keluar')->sum('jumlah');

        return view('kas.bukukas', compact('transaksi', 'tahun', 'bulan', 'kas', 'totalMasuk', 'totalKeluar'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'kas' => 'required', 'jenis' => 'required', 'tanggal' => 'required|date',
            'jumlah' => 'required|numeric|min:1', 'keterangan' => 'required',
        ]);

        Transaksi::create([
            'transaksi_id' => 'TRX-' . uniqid(),
            'kas' => $request->kas,
            'jenis' => $request->jenis,
            'tanggal' => $request->tanggal,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
            'operator' => auth()->user()->username ?? 'admin',
        ]);

        AuditLogService::log('create', 'transaksi', $request->jenis . ' ' . $request->kas . ': Rp ' . number_format($request->jumlah, 0, ',', '.') . ' - ' . $request->keterangan);
        return back()->with('success', 'Transaksi berhasil dicatat.');
    }
}
