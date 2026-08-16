<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\UserRoleAssignment;
use App\Services\AuditLogService;
use App\Services\PembukaTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Prefill form edit desa: kabupaten per desa dalam satu query.
        $kabupatenPerDesa = AppSetting::whereIn('organization_id', $desas->pluck('id'))
            ->where('key', 'kabupaten')->pluck('value', 'organization_id');

        return view('admin.tenant', compact('desas', 'adminPerOrg', 'kabupatenPerDesa'));
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

    /**
     * Ubah identitas desa: nama, kecamatan, kabupaten. Label/slug TIDAK bisa
     * diubah: domain terikat padanya. Ini alat KOREKSI identitas, jadi
     * override kelurahan/kecamatan yang pernah diisi tenant di bawahnya dan
     * data KK lama ikut ditimpa nilai baru - konsistensi menang.
     */
    public function update(Request $request, int $id)
    {
        $this->pastikanAdminPlatform();
        $desa = Organization::where('type', Organization::TYPE_DESA)->findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten' => 'nullable|string|max:100',
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

        $namaPolos = trim($validated['nama']);
        $kabupaten = trim($validated['kabupaten'] ?? '');
        DB::transaction(function () use ($desa, $namaLengkap, $namaPolos, $kecamatan, $kabupaten) {
            $desa->update(['name' => $namaLengkap]);

            $subtree = Organization::idSubtree($desa->id);
            if ($kabupaten !== '') {
                // Di org desa supaya diwarisi semua RW (kop surat dsb).
                AppSetting::simpanUntuk($desa->id, 'kabupaten', $kabupaten);
            }
            AppSetting::whereIn('organization_id', $subtree)
                ->where('key', 'kelurahan')->update(['value' => $namaPolos]);
            if ($kecamatan !== '') {
                AppSetting::whereIn('organization_id', $subtree)
                    ->where('key', 'kecamatan')->update(['value' => $kecamatan]);
            }

            Keluarga::withoutGlobalScope('organisasi')
                ->whereIn('organization_id', $subtree)
                ->update(array_merge(
                    ['kelurahan' => $namaPolos],
                    $kecamatan !== '' ? ['kecamatan' => $kecamatan] : []
                ));
        });
        // Update query builder di atas melewati invalidasi memo simpan().
        app(\App\Services\TenantContext::class)->lupakan('app_settings.efektif');

        AuditLogService::log('ubah_tenant', 'tenant', "Ubah identitas desa {$desa->slug} menjadi {$namaLengkap}".($kabupaten !== '' ? ", kabupaten {$kabupaten}" : ''));

        return redirect()->route('tenant.index')->with('success', "Identitas desa diperbarui: {$namaLengkap}.");
    }

    /**
     * Ganti nomor RW: name/code dimutakhirkan, hostname portal ikut berganti,
     * dan alamat lama dipertahankan sebagai alias (non-primary, tetap aktif)
     * supaya tautan/kredensial yang sudah dibagikan warga tidak putus.
     * Slug SENGAJA tidak disentuh: slug RT anak dan lookup NotificationService/
     * AkunController dibangun darinya (lihat .ai/DECISIONS.md).
     */
    public function updateRw(Request $request, int $id)
    {
        $this->pastikanAdminPlatform();
        $rw = Organization::where('type', Organization::TYPE_RW)
            ->with(['parent', 'domains'])->findOrFail($id);
        $desa = $rw->parent;

        $validated = $request->validate([
            'nomor' => ['required', 'regex:/^\d{1,2}$/'],
        ], [
            'nomor.regex' => 'Nomor RW berupa 1-2 digit angka.',
        ]);
        $nn = str_pad($validated['nomor'], 2, '0', STR_PAD_LEFT);

        $lama = trim(preg_replace('/\D+/', '', $rw->name));
        if ($lama === $nn) {
            return redirect()->route('tenant.index')
                ->with('success', "{$rw->name} sudah bernomor {$nn} - tidak ada yang diubah.");
        }

        $primaryLama = $rw->domains->firstWhere('is_primary', true);
        if ($desa === null || $primaryLama === null) {
            return back()->withErrors([
                'nomor' => 'RW ini belum punya desa induk/domain utama - perbaiki datanya dulu.',
            ]);
        }

        if (Organization::where('parent_id', $desa->id)
            ->where('type', Organization::TYPE_RW)
            ->where('id', '!=', $rw->id)
            ->where('name', "RW {$nn}")->exists()) {
            return back()->withErrors(['nomor' => "RW {$nn} sudah ada di {$desa->name}."])->withInput();
        }

        $basis = substr($primaryLama->hostname, strpos($primaryLama->hostname, '.') + 1);
        $hostBaru = "{$desa->slug}-rw{$nn}.{$basis}";
        if (Domain::where('hostname', $hostBaru)->where('organization_id', '!=', $rw->id)->exists()) {
            return back()->withErrors(['nomor' => "Alamat {$hostBaru} sudah dipakai organisasi lain."])->withInput();
        }

        DB::transaction(function () use ($rw, $desa, $nn, $lama, $primaryLama, $hostBaru) {
            $rw->update([
                'name' => "RW {$nn}",
                'code' => strtoupper("{$desa->slug}-RW{$nn}"),
            ]);

            $primaryLama->update(['is_primary' => false]);
            // Ganti-balik ke nomor lama: baris alias lama dipromosikan lagi,
            // bukan digandakan (hostname unique).
            Domain::firstOrCreate(
                ['hostname' => $hostBaru],
                ['organization_id' => $rw->id, 'is_primary' => true, 'status' => 'aktif']
            )->update(['is_primary' => true, 'status' => 'aktif']);

            // Setting hanya dikoreksi bila memang menunjuk nilai lama; override
            // kustom (mis. domain milik sendiri) dibiarkan.
            if (AppSetting::where('organization_id', $rw->id)->where('key', 'nama_rw')->exists()) {
                AppSetting::simpanUntuk($rw->id, 'nama_rw', "RW {$nn}");
            }
            $portal = AppSetting::where('organization_id', $rw->id)
                ->where('key', 'alamat_portal')->first();
            if ($portal === null || $portal->value === $primaryLama->hostname) {
                AppSetting::simpanUntuk($rw->id, 'alamat_portal', $hostBaru);
            }

            Keluarga::withoutGlobalScope('organisasi')
                ->where('organization_id', $rw->id)->update(['rw' => $nn]);
            if ($lama !== '') {
                // $lama/$nn dijamin digit (regex + preg_replace \D), aman
                // diinterpolasi; REPLACE() ada di SQLite maupun MariaDB.
                Keluarga::withoutGlobalScope('organisasi')
                    ->where('organization_id', $rw->id)
                    ->update(['alamat' => DB::raw("REPLACE(alamat, 'RW {$lama}', 'RW {$nn}')")]);
            }
        });

        AuditLogService::log('ubah_tenant', 'tenant',
            "Ganti nomor {$rw->slug}: RW {$lama} -> RW {$nn}, portal {$hostBaru}");

        return redirect()->route('tenant.index')->with('success',
            "{$desa->name} RW {$nn}: portal baru https://{$hostBaru} - daftarkan subdomainnya di cPanel + AutoSSL. ".
            "Alamat lama {$primaryLama->hostname} tetap hidup sebagai alias; username admin tidak berubah.");
    }

    /**
     * Buatkan akun admin desa (peran desa_admin di organisasi desa): pengelola
     * akun seluruh RW di desanya lewat host {label}.desa.jabnet.id/akun.
     * PIN acak sekali-tayang; akun yang sudah ada tidak direset.
     */
    public function buatAdminDesa(int $id)
    {
        $this->pastikanAdminPlatform();
        $desa = Organization::where('type', Organization::TYPE_DESA)->findOrFail($id);

        $username = "{$desa->slug}-admin";
        $admin = \App\Models\User::where('username', $username)->first();
        $pin = null;
        if ($admin === null) {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $admin = \App\Models\User::create([
                'user_id' => 'USR-'.uniqid(),
                'username' => $username,
                'namaLengkap' => "Admin {$desa->name}",
                'pin' => \Illuminate\Support\Facades\Hash::make($pin),
                'level' => 'ketua_rw',
                'status' => 'aktif',
                'isDefault' => false,
            ]);
        }

        UserRoleAssignment::firstOrCreate([
            'user_id' => $admin->id,
            'role_id' => \App\Models\Role::where('slug', 'desa_admin')->value('id'),
            'organization_id' => $desa->id,
        ]);
        AuditLogService::log('buat_admin_desa', 'tenant', "Akun admin desa {$desa->slug}: {$username}");

        return redirect()->route('tenant.index')->with('hasilAdminDesa', [
            'desa' => $desa->name, 'username' => $username, 'pin' => $pin,
        ]);
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
