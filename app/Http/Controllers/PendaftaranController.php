<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MpwaService;

class PendaftaranController extends Controller
{
    public function index()
    {
        $pendaftarans = \App\Models\Pendaftaran::orderBy('created_at', 'desc')->get();
        return view('admin.pendaftaran', compact('pendaftarans'));
    }

    public function approve($id)
    {
        $p = \App\Models\Pendaftaran::findOrFail($id);
        if ($p->status !== 'pending') {
            return back()->with('error', 'Status pendaftaran ini sudah diproses.');
        }

        $username = null;
        $generatedPin = null;

        // Check if this NIK or KK is already in keluargas (prevent duplicate warga)
        $dupQuery = \App\Models\Keluarga::where('nik', $p->nik);
        if (!empty($p->no_kk)) {
            $dupQuery->orWhere('noKK', $p->no_kk);
        }
        if ($dupQuery->exists()) {
            // Auto-reject this duplicate and all other pending duplicates
            \App\Models\Pendaftaran::where('nik', $p->nik)
                ->where('status', 'pending')
                ->update(['status' => 'ditolak', 'keterangan' => 'NIK/KK sudah terdaftar sebagai warga (duplikat otomatis ditolak)']);
            return back()->with('error', 'NIK atau No. KK sudah terdaftar sebagai warga. Pendaftaran duplikat otomatis ditolak.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($p, &$username, &$generatedPin) {
            // Create Keluarga
            $keluargaId = 'kk_' . uniqid();
            \App\Models\Keluarga::create([
                'keluarga_id'     => $keluargaId,
                'noKK'            => $p->no_kk,
                'nik'             => $p->nik,
                'nama'            => $p->nama_lengkap,
                // Wilayah dari TENANT request (dulu tetap "RW 10 Sukakarya").
                'alamat'          => trim('RT ' . str_pad($p->rt, 2, '0', STR_PAD_LEFT) . ' ' . namaRw() . ', ' . wilayahTenant()['kelurahan'], ' ,'),
                'rt'              => str_pad($p->rt, 2, '0', STR_PAD_LEFT),
                'rw'              => wilayahTenant()['rw'],
                'kelurahan'       => wilayahTenant()['kelurahan'],
                'kecamatan'       => wilayahTenant()['kecamatan'],
                'noHP'            => normalizeWa($p->no_wa), // satu format nomor di seluruh aplikasi
                'jumlahAnggota'   => 1,
                'status'          => 'aktif',
                'ikutSampah'      => true,
                'ikutPadaringan'  => false,
            ]);

            // Create User Akun with unique username
            $username = str_replace(' ', '', strtolower($p->nama_lengkap));
            $base = $username; $counter = 1;
            while (\App\Models\User::where('username', $username)->exists()) {
                $username = $base . $counter++;
            }

            // Generate random 6-digit PIN (not default 123456)
            $generatedPin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            \App\Models\User::create([
                'user_id'     => 'USR-' . uniqid(),
                'namaLengkap' => $p->nama_lengkap,
                'username'    => $username,
                'pin'         => \Illuminate\Support\Facades\Hash::make($generatedPin),
                'level'       => 'warga',
                'rt'          => $p->rt,
                'wa'          => normalizeWa($p->no_wa),
                'keluarga_id' => $keluargaId, // pengikat kepemilikan halaman Profil Saya
                'isDefault'   => false,
                'status'      => 'aktif',
            ]);

            $p->update(['status' => 'disetujui']);
            \App\Services\AuditLogService::log('approve_pendaftaran', 'warga', 'Pendaftaran disetujui, auto-create Warga: ' . $p->nama_lengkap);
        });

        $waStatus = '';
        // Send WA notification with credentials (fire-and-forget, after transaction commits)
        if ($p->no_wa && $username) {
            $sent = MpwaService::notifyPendaftaranDisetujui($p->no_wa, $p->nama_lengkap, $p->rt, $username, $generatedPin ?? '000000');
            $waStatus = $sent ? ' ✅ Username & PIN dikirim via WhatsApp.' : ' ⚠️ Gagal kirim notifikasi WA.';
        } else {
            $waStatus = ' ⚠️ Tidak ada no. WA — info akun belum terkirim.';
        }

        return back()->with('success', 'Pendaftaran disetujui. Akun dibuat: ' . $username . '.' . $waStatus);
    }

    public function reject(Request $request, $id)
    {
        $p = \App\Models\Pendaftaran::findOrFail($id);
        $alasan = $request->keterangan ?? 'Ditolak oleh admin';

        $p->update([
            'status'     => 'ditolak',
            'keterangan' => $alasan,
        ]);

        \App\Services\AuditLogService::log('reject_pendaftaran', 'warga', 'Pendaftaran ditolak: ' . $p->nama_lengkap);

        // Send WA rejection notification
        if ($p->no_wa) {
            MpwaService::notifyPendaftaranDitolak($p->no_wa, $p->nama_lengkap, $alasan);
        }

        return back()->with('success', 'Pendaftaran berhasil ditolak.' . ($p->no_wa ? ' ✅ Notifikasi WA terkirim.' : ''));
    }
}
