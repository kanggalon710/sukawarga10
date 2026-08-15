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
        // Kolom disebut satu per satu. Model ini $guarded = [], jadi $request->all()
        // membuat kolom apa pun bisa diisi dari luar.
        $validated = $request->validate([
            'judul'     => 'required|string|max:150',
            'tanggal'   => 'required|date',
            'waktu'     => 'nullable|string|max:50',
            'tempat'    => 'nullable|string|max:150',
            'jenis'     => 'nullable|string|max:50',
            'pic'       => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string|max:2000',
            'status'    => 'nullable|in:direncanakan,selesai,dibatalkan',
        ]);

        $validated['kegiatan_id'] = 'KGT-' . uniqid();
        $validated['status'] = $validated['status'] ?? 'direncanakan';

        Kegiatan::create($validated);
        return back()->with('success', 'Kegiatan berhasil dicatat.');
    }

    public function destroy($id)
    {
        Kegiatan::findOrFail($id)->delete();
        return back()->with('success', 'Kegiatan berhasil dihapus.');
    }
}
