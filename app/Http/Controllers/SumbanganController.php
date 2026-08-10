<?php

namespace App\Http\Controllers;

use App\Models\Sumbangan;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class SumbanganController extends Controller
{
    public function index()
    {
        $sumbangans = Sumbangan::orderByDesc('tanggal')->get();
        return view('kas.sumbangan', compact('sumbangans'));
    }

    public function store(Request $request)
    {
        $request->validate(['donatur' => 'required', 'jumlah' => 'required|numeric|min:1', 'kas' => 'required|in:umum,sampah,padaringan']);

        $kas = $request->kas ?? 'umum';

        Sumbangan::create([
            'sumbangan_id' => 'SMB-' . uniqid(),
            'tanggal' => $request->tanggal ?? now()->toDateString(),
            'donatur' => $request->donatur,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
        ]);

        Transaksi::create([
            'transaksi_id' => 'TRX-' . uniqid(),
            'kas' => $kas,
            'jenis' => 'masuk',
            'tanggal' => $request->tanggal ?? now()->toDateString(),
            'jumlah' => $request->jumlah,
            'keterangan' => 'Sumbangan dari ' . $request->donatur . ' (' . ucfirst($kas) . ')',
            'operator' => auth()->user()->username ?? 'admin',
        ]);

        return back()->with('success', 'Sumbangan berhasil dicatat.');
    }
}
