<?php

namespace App\Http\Controllers;

use App\Models\SetorSampah;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class SetorSampahController extends Controller
{
    public function index()
    {
        $setoran = SetorSampah::orderByDesc('created_at')->get();
        return view('kas.setor', compact('setoran'));
    }

    public function store(Request $request)
    {
        $request->validate(['jumlah' => 'required|numeric|min:1', 'rt' => 'required']);

        SetorSampah::create([
            'setor_id' => 'STR-' . uniqid(),
            'rt' => $request->rt,
            'tanggal' => $request->tanggal ?? now()->toDateString(),
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan ?? 'Setoran sampah RT ' . $request->rt,
        ]);

        Transaksi::create([
            'transaksi_id' => 'TRX-' . uniqid(),
            'kas' => 'sampah',
            'jenis' => 'masuk',
            'tanggal' => $request->tanggal ?? now()->toDateString(),
            'jumlah' => $request->jumlah,
            'keterangan' => 'Setoran sampah RT ' . $request->rt,
            'operator' => auth()->user()->username ?? 'admin',
        ]);

        return back()->with('success', 'Setoran sampah berhasil dicatat.');
    }
}
