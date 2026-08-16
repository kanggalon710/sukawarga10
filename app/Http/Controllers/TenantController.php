<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\UserRoleAssignment;
use App\Services\AuditLogService;
use App\Services\PembukaTenant;
use Illuminate\Http\Request;

/**
 * Manajemen Desa (Phase G tahap 1): membuka desa + RW + domain + admin dari
 * browser. HANYA untuk super_admin PLATFORM - rute dijaga role:superadmin,
 * lalu dipersempit di sini karena superadmin ber-scope tenant lolos
 * middleware tapi tidak boleh membuka desa baru.
 */
class TenantController extends Controller
{
    public function index()
    {
        $this->pastikanAdminPlatform();

        $desas = Organization::where('type', Organization::TYPE_DESA)
            ->with([
                'children' => fn ($q) => $q->where('type', Organization::TYPE_RW)->orderBy('slug'),
                'children.domains',
            ])
            ->orderBy('name')->get();

        // Admin per RW dalam SATU query (bukan per baris tabel).
        $adminPerOrg = UserRoleAssignment::query()
            ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->join('users', 'users.id', '=', 'user_role_assignments.user_id')
            ->where('roles.slug', 'rw_admin')
            ->whereIn('user_role_assignments.organization_id', $desas->flatMap->children->pluck('id'))
            ->get(['user_role_assignments.organization_id', 'users.username'])
            ->groupBy('organization_id');

        return view('admin.tenant', compact('desas', 'adminPerOrg'));
    }

    public function store(Request $request, PembukaTenant $pembuka)
    {
        $this->pastikanAdminPlatform();

        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'label' => ['required', 'string', 'max:40', 'regex:'.PembukaTenant::POLA_LABEL],
            'kecamatan' => 'nullable|string|max:100',
            'rw' => 'required|string|max:100',
        ], [
            'label.regex' => 'Label hanya boleh huruf kecil, angka, dan strip (mis. cibunar atau cibunar-kota).',
        ]);

        try {
            $hasil = $pembuka->buka(
                $validated['nama'],
                $validated['label'],
                $validated['kecamatan'] ?? '',
                [$validated['rw']],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['rw' => $e->getMessage()])->withInput();
        }

        AuditLogService::log('buat_tenant', 'tenant',
            'Buka tenant: '.$hasil['desa']->name.' ('.count($hasil['baris']).' RW) oleh '.auth()->user()->username);

        return redirect()->route('tenant.index')->with('hasilTenant', [
            'desa' => $hasil['desa']->name,
            'baris' => $hasil['baris'],
        ]);
    }

    /** Ubah nama tampilan desa. Label/slug TIDAK bisa diubah: domain terikat padanya. */
    public function update(Request $request, int $id)
    {
        $this->pastikanAdminPlatform();
        $desa = Organization::where('type', Organization::TYPE_DESA)->findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
        ]);

        $kecamatan = trim($validated['kecamatan'] ?? '');
        $namaLengkap = $kecamatan !== ''
            ? trim($validated['nama'])." ({$kecamatan})"
            : trim($validated['nama']);

        // Penjaga duplikat yang sama dengan pembuatan: ganti nama tidak boleh
        // menabrak desa lain.
        $kembar = Organization::where('type', Organization::TYPE_DESA)
            ->where('id', '!=', $desa->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($namaLengkap)])
            ->first();
        if ($kembar !== null) {
            return back()->withErrors([
                'nama' => "{$namaLengkap} sudah terdaftar dengan label '{$kembar->slug}'.",
            ])->withInput();
        }

        $desa->update(['name' => $namaLengkap]);
        AuditLogService::log('ubah_tenant', 'tenant', "Ubah nama desa {$desa->slug} menjadi {$namaLengkap}");

        return redirect()->route('tenant.index')->with('success', "Nama desa diperbarui: {$namaLengkap}.");
    }

    /** Aktif/nonaktifkan RW: resolver menolak seluruh domain organisasi nonaktif. */
    public function toggleRw(int $id)
    {
        $this->pastikanAdminPlatform();
        $rw = Organization::where('type', Organization::TYPE_RW)->findOrFail($id);

        $baru = ($rw->status ?? 'aktif') === 'aktif' ? 'nonaktif' : 'aktif';
        $rw->update(['status' => $baru]);
        AuditLogService::log('ubah_tenant', 'tenant', "Status {$rw->slug} -> {$baru}");

        return redirect()->route('tenant.index')->with('success', "{$rw->name} kini {$baru}.");
    }

    /** Model data tenant yang menghalangi penghapusan RW bila masih berisi. */
    private const MODEL_PENGHALANG_HAPUS = [
        \App\Models\Keluarga::class, \App\Models\Transaksi::class,
        \App\Models\Surat::class, \App\Models\Aduan::class,
        \App\Models\Umkm::class, \App\Models\Kegiatan::class,
        \App\Models\Pengeluaran::class, \App\Models\Sumbangan::class,
        \App\Models\SetorSampah::class, \App\Models\Pendaftaran::class,
    ];

    public function destroyRw(int $id)
    {
        $this->pastikanAdminPlatform();
        $rw = Organization::where('type', Organization::TYPE_RW)->findOrFail($id);

        foreach (self::MODEL_PENGHALANG_HAPUS as $kelas) {
            if ($kelas::withoutGlobalScope('organisasi')->where('organization_id', $rw->id)->exists()) {
                return redirect()->route('tenant.index')->with('error',
                    "{$rw->name} masih berisi data warga/keuangan - nonaktifkan saja, jangan dihapus.");
            }
        }

        // Akun admin dibiarkan hidup (tanpa assignment = warga biasa);
        // menghapus akun orang bukan urusan tombol ini.
        $rw->domains()->delete();
        UserRoleAssignment::where('organization_id', $rw->id)->delete();
        $rw->delete();
        AuditLogService::log('hapus_tenant', 'tenant', "Hapus {$rw->slug} (kosong) beserta domain & assignment-nya");

        return redirect()->route('tenant.index')->with('success', "{$rw->name} dihapus.");
    }

    public function destroyDesa(int $id)
    {
        $this->pastikanAdminPlatform();
        $desa = Organization::where('type', Organization::TYPE_DESA)->findOrFail($id);

        if ($desa->children()->exists()) {
            return redirect()->route('tenant.index')->with('error',
                "{$desa->name} masih punya RW - hapus/pindahkan RW-nya dulu.");
        }

        $desa->domains()->delete();
        $desa->delete();
        AuditLogService::log('hapus_tenant', 'tenant', "Hapus desa {$desa->slug} (tanpa RW)");

        return redirect()->route('tenant.index')->with('success', "{$desa->name} dihapus.");
    }
}
