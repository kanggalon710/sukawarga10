<?php

namespace App\Http\Controllers;

use App\Models\Aduan;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AduanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $level = $user->level ?? 'warga';
        $isWarga = ($level === 'warga');

        $status = $request->get('status', '');

        if ($isWarga) {
            // Warga only sees their own complaints
            $query = Aduan::where('user_id', $user->id)->orderByDesc('tanggal');
        } else {
            // Pengurus sees all
            $query = Aduan::orderByDesc('tanggal');
        }

        if ($status) $query->where('status', $status);
        $aduans = $query->get();

        $keluargas = \App\Models\Keluarga::where('status', 'aktif')->orderBy('nama')->get(['keluarga_id', 'nama', 'rt']);

        return view('layanan.aduan', compact('aduans', 'status', 'keluargas', 'user', 'level', 'isWarga'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $level = $user->level ?? 'warga';
        $isWarga = ($level === 'warga');

        $request->validate(['isi' => 'required']);

        $pelapor = $isWarga
            ? ($user->namaLengkap ?? $user->username)
            : $request->pelapor;

        $rt = $isWarga
            ? ($user->rt ?? $request->rt)
            : $request->rt;

        $aduan = Aduan::create([
            'aduan_id'   => 'ADU-' . uniqid(),
            'tanggal'    => now()->toDateString(),
            'pelapor'    => $pelapor,
            'rt'         => $rt,
            'user_id'    => $user->id,
            'kategori'   => $request->kategori,
            'prioritas'  => $request->prioritas ?? 'sedang',
            'isi'        => $request->isi,
            'status'     => 'masuk',
            'recordedBy' => $user->username ?? 'system',
        ]);

        // Auto-notify pengurus about new complaint
        if (NotificationService::isEnabled('notif_aduan_baru')) {
            $prioLabel = match($request->prioritas) {
                'tinggi' => '🔴 TINGGI',
                'sedang' => '🟡 Sedang',
                default  => '🟢 Rendah',
            };
            $msg = "📢 *ADUAN BARU DARI WARGA*\n\nPelapor: {$pelapor}\nRT: {$rt}\nKategori: {$request->kategori}\nPrioritas: {$prioLabel}\n\nIsi:\n{$request->isi}\n\nSilakan login untuk menindaklanjuti.";
            NotificationService::notifyPengurus($msg);
        }

        return back()->with('success', 'Aduan berhasil dicatat.');
    }

    public function updateStatus(Request $request, $id)
    {
        $aduan = Aduan::findOrFail($id);
        $user = auth()->user();

        $aduan->update([
            'status' => $request->status,
            'catatanTL' => $request->catatanTL,
        ]);

        // Notify warga when resolved
        if ($request->status === 'selesai' && $aduan->user_id && NotificationService::isEnabled('notif_aduan_selesai')) {
            $wargaUser = \App\Models\User::find($aduan->user_id);
            if ($wargaUser && $wargaUser->wa) {
                $msg = "✅ *ADUAN ANDA TELAH DISELESAIKAN*\n\nAduan: {$aduan->isi}\n\nCatatan penyelesaian: {$request->catatanTL}\n\nTerima kasih telah melaporkan.";
                NotificationService::notifyWarga($wargaUser->wa, $msg);
            }
        }

        return back()->with('success', 'Status aduan diperbarui.');
    }
}
