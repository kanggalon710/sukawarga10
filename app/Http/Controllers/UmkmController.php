<?php

namespace App\Http\Controllers;

use App\Models\Umkm;
use Illuminate\Http\Request;

class UmkmController extends Controller
{
    public function index()
    {
        $umkms = Umkm::orderBy('namaUsaha')->get();
        $total = $umkms->count();
        $aktif = $umkms->where('status', 'aktif')->count();
        $musiman = $umkms->where('status', 'musiman')->count();
        return view('umkm.index', compact('umkms', 'total', 'aktif', 'musiman'));
    }

    public function store(Request $request)
    {
        // Kolom disebut satu per satu. Model ini $guarded = [], jadi $request->all()
        // membuat kolom apa pun bisa diisi dari luar.
        $validated = $request->validate([
            'pemilik'   => 'required|string|max:100',
            'namaUsaha' => 'required|string|max:150',
            'rt'        => 'nullable|string|max:10',
            'jenis'     => 'nullable|string|max:50',
            'deskripsi' => 'nullable|string|max:2000',
            'noHP'      => 'nullable|string|max:25',
            'status'    => 'nullable|in:aktif,musiman,nonaktif',
        ]);

        $validated['umkm_id'] = 'UMKM-' . uniqid();
        $validated['status'] = $validated['status'] ?? 'aktif';
        $validated['noHP'] = normalizeWa($request->input('noHP'));

        Umkm::create($validated);
        return back()->with('success', 'UMKM berhasil didaftarkan.');
    }

    public function destroy($id)
    {
        $umkm = Umkm::findOrFail($id);
        $umkm->delete();
        return back()->with('success', 'UMKM berhasil dihapus.');
    }
}
