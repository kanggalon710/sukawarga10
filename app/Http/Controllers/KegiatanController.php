<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;

class KegiatanController extends Controller
{
    public function index()
    {
        $kegiatans = Kegiatan::orderByDesc('tanggal')->get();
        return view('kegiatan.index', compact('kegiatans'));
    }

    public function store(Request $request)
    {
        $request->validate(['judul' => 'required', 'tanggal' => 'required|date']);

        Kegiatan::create(array_merge($request->all(), ['kegiatan_id' => 'KGT-' . uniqid()]));
        return back()->with('success', 'Kegiatan berhasil dicatat.');
    }

    public function destroy($id)
    {
        Kegiatan::findOrFail($id)->delete();
        return back()->with('success', 'Kegiatan berhasil dihapus.');
    }
}
