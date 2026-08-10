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
        $request->validate([
            'pemilik' => 'required', 'namaUsaha' => 'required',
        ]);

        Umkm::create(array_merge($request->all(), ['umkm_id' => 'UMKM-' . uniqid()]));
        return back()->with('success', 'UMKM berhasil didaftarkan.');
    }

    public function destroy($id)
    {
        $umkm = Umkm::findOrFail($id);
        $umkm->delete();
        return back()->with('success', 'UMKM berhasil dihapus.');
    }
}
