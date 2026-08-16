<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Services\TenantContext;

/**
 * Beranda per tingkat hirarki di rute `/` (nama rute tetap `dashboard`):
 * - host RW       -> dashboard tenant seperti biasa (login dulu),
 * - host desa     -> profil desa PUBLIK: daftar RW + tautan portalnya,
 * - host platform -> dashboard owner (khusus admin platform); pengurus/warga
 *                    yang nyasar ke root diarahkan ke portal RW asalnya.
 */
class BerandaController extends Controller
{
    public function __invoke(TenantContext $context)
    {
        $org = $context->organisasi();

        if ($org === null || $context->rw() !== null) {
            if (! auth()->check()) {
                return redirect()->route('login');
            }

            return app(DashboardController::class)->index();
        }

        return $org->type === Organization::TYPE_DESA
            ? $this->berandaDesa($org)
            : $this->berandaPlatform();
    }

    private function berandaDesa(Organization $desa)
    {
        $daftarRw = $desa->children()->where('type', Organization::TYPE_RW)
            ->orderBy('slug')->with('domains')->get();

        // Agregat jumlah KK per RW: satu query lintas scope. withoutGlobalScope
        // sah di sini (aturan AGENTS #9) - halaman desa memang membaca angka
        // milik RW-RW di bawahnya, dibatasi eksplisit ke id anak-anaknya,
        // dan hanya ANGKA yang tampil, bukan data pribadi.
        $jumlahKk = Keluarga::withoutGlobalScope('organisasi')
            ->whereIn('organization_id', $daftarRw->pluck('id'))
            ->where('status', 'aktif')
            ->selectRaw('organization_id, COUNT(*) AS jumlah')
            ->groupBy('organization_id')
            ->pluck('jumlah', 'organization_id');

        return view('beranda.desa', compact('desa', 'daftarRw', 'jumlahKk'));
    }

    private function berandaPlatform()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }
        if (! auth()->user()->adalahAdminPlatform()) {
            // Pengurus/warga yang membuka root: bimbing ke portal RW asalnya.
            $alamat = auth()->user()->rwAsal()
                ?->domains()->orderByDesc('is_primary')->value('hostname');
            abort_unless($alamat, 403);

            return redirect()->away('https://'.$alamat);
        }

        $daftarDesa = Organization::where('type', Organization::TYPE_DESA)
            ->with([
                'children' => fn ($q) => $q->where('type', Organization::TYPE_RW)->orderBy('slug'),
                'children.domains', 'domains',
            ])->orderBy('name')->get();

        // Angka lintas tenant untuk owner platform (alasan escape hatch sama
        // dengan berandaDesa; halaman ini khusus admin platform).
        $totalKk = Keluarga::withoutGlobalScope('organisasi')->where('status', 'aktif')->count();
        $jumlahKkPerOrg = Keluarga::withoutGlobalScope('organisasi')
            ->where('status', 'aktif')
            ->selectRaw('organization_id, COUNT(*) AS jumlah')
            ->groupBy('organization_id')
            ->pluck('jumlah', 'organization_id');

        return view('beranda.platform', [
            'daftarDesa' => $daftarDesa,
            'totalDesa' => $daftarDesa->count(),
            'totalRw' => $daftarDesa->sum(fn ($d) => $d->children->count()),
            'totalKk' => $totalKk,
            'jumlahKkPerOrg' => $jumlahKkPerOrg,
            'totalDomain' => Domain::count(),
        ]);
    }
}
