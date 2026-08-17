<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\PembaruAplikasi;
use Illuminate\Support\Facades\Cache;

/**
 * Pembaruan Sistem: cek versi terbaru branch production dan update satu klik.
 * Khusus admin platform - lihat gerbang pastikanAdminPlatform di base
 * Controller.
 */
class PembaruanController extends Controller
{
    public function index(PembaruAplikasi $pembaru)
    {
        $this->pastikanAdminPlatform();

        return view('admin.pembaruan', [
            'versi' => $pembaru->versiTerpasang(),
            'status' => PembaruAplikasi::statusTercatat(),
        ]);
    }

    public function cek(PembaruAplikasi $pembaru)
    {
        $this->pastikanAdminPlatform();

        try {
            $status = $pembaru->cek();
        } catch (\RuntimeException $e) {
            return redirect()->route('pembaruan.index')->with('error', $e->getMessage());
        }

        return redirect()->route('pembaruan.index')->with(
            'success',
            $status['tersedia']
                ? "Ada {$status['jumlah']} pembaruan baru."
                : 'Aplikasi sudah mutakhir.'
        );
    }

    public function jalankan(PembaruAplikasi $pembaru)
    {
        $this->pastikanAdminPlatform();

        // Kunci 10 menit: klik ganda tidak boleh menjalankan dua pull paralel.
        $kunci = Cache::lock('pembaruan.jalankan', 600);
        if (! $kunci->get()) {
            return redirect()->route('pembaruan.index')
                ->with('error', 'Pembaruan lain sedang berjalan. Tunggu sampai selesai.');
        }

        try {
            $hasil = $pembaru->jalankan();
            if ($hasil['mutakhir']) {
                return redirect()->route('pembaruan.index')
                    ->with('success', 'Aplikasi sudah mutakhir. Tidak ada yang perlu diperbarui.');
            }

            AuditLogService::log('pembaruan', 'sistem',
                'Pembaruan aplikasi dijalankan oleh '.auth()->user()->username);

            return redirect()->route('pembaruan.index')
                ->with('success', 'Pembaruan selesai. Aplikasi kini di versi terbaru.')
                ->with('logPembaruan', $hasil['log']);
        } catch (\RuntimeException $e) {
            AuditLogService::log('pembaruan_gagal', 'sistem', $e->getMessage());
            $log = json_decode($e->getPrevious()?->getMessage() ?? '[]', true) ?: [];

            return redirect()->route('pembaruan.index')
                ->with('error', $e->getMessage().' Bila berulang, jalankan lewat terminal (lihat DEPLOY.md).')
                ->with('logPembaruan', $log);
        } finally {
            $kunci->release();
        }
    }
}
