<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditLogService;

class AkunController extends Controller
{
    /**
     * Gerbang + cakupan kelola akun per tingkat host (owner=semua,
     * admin desa=subtree desanya, admin RW=subtree tenantnya).
     * Mengembalikan daftar id organisasi cakupan, atau null = semua akun.
     */
    private function cakupanKelola(): ?array
    {
        $context = app(TenantContext::class);
        $org = $context->organisasi();
        abort_unless($org !== null && auth()->user()->bolehKelolaAkunDi($org), 403);

        if ($org->type === Organization::TYPE_PLATFORM) {
            return null;
        }

        return Organization::idSubtree(($context->rw() ?? $org)->id);
    }

    /** Akun $target termasuk cakupan? Di luar cakupan = 404, bukan bocor. */
    private function pastikanDalamCakupan(User $target, ?array $cakupan): void
    {
        if ($cakupan === null) {
            return;
        }

        $anggota = UserRoleAssignment::where('user_id', $target->id)
            ->whereIn('organization_id', $cakupan)->exists()
            || ($target->keluarga_id !== null
                && \App\Models\Keluarga::withoutGlobalScope('organisasi')
                    ->where('keluarga_id', $target->keluarga_id)
                    ->whereIn('organization_id', $cakupan)->exists());

        abort_unless($anggota, 404);
    }

    public function index()
    {
        $cakupan = $this->cakupanKelola();

        // CASE portabel (MySQL + SQLite). FIELD() hanya tersedia di MySQL.
        $q = User::orderByRaw("CASE level
                WHEN 'superadmin' THEN 1 WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 1
                WHEN 'ketua_rw' THEN 2 WHEN 'sekretaris' THEN 3 WHEN 'bendahara' THEN 4
                WHEN 'petugas_rt' THEN 5 WHEN 'warga' THEN 6 ELSE 7 END")
            ->orderBy('username');

        if ($cakupan !== null) {
            // Akun "milik" cakupan = ber-assignment di organisasinya ATAU
            // keluarganya tercatat di sana. Dulu daftar ini TANPA saringan:
            // admin satu RW melihat (dan bisa mengubah) akun semua tenant.
            $q->where(function ($w) use ($cakupan) {
                $w->whereIn('id', UserRoleAssignment::whereIn('organization_id', $cakupan)->select('user_id'))
                    ->orWhereIn('keluarga_id', \App\Models\Keluarga::withoutGlobalScope('organisasi')
                        ->whereIn('organization_id', $cakupan)->select('keluarga_id'));
            });
        }

        $users = $q->get();

        // Instruksi di bawah metrik: alamat portal login warga dalam cakupan.
        // Host RW menunjuk alamat tenantnya sendiri; host desa/platform
        // mendaftar RW aktif miliknya - akun warga hanya bisa login di sana.
        $rwPortal = Organization::where('type', Organization::TYPE_RW)
            ->where('status', 'aktif')
            ->when($cakupan !== null, fn ($w) => $w->whereIn('id', $cakupan))
            ->with([
                'parent:id,name',
                'domains' => fn ($d) => $d->where('is_primary', true)->where('status', 'aktif'),
            ])
            ->get()
            ->map(fn ($rw) => [
                'rw' => $rw->name,
                'desa' => $rw->parent?->name ?? '',
                'host' => $rw->domains->first()?->hostname,
            ])
            ->filter(fn ($p) => $p['host'] !== null)
            ->sortBy([['desa', 'asc'], ['rw', 'asc']])
            ->values();

        return view('admin.akun', compact('users', 'rwPortal'));
    }

    public function store(Request $request)
    {
        $this->cakupanKelola();
        $request->validate([
            'namaLengkap' => 'required',
            'username' => 'required|unique:users',
            'pin' => 'required|digits:6',
            'level' => 'required|in:superadmin,ketua_rw,sekretaris,bendahara,petugas_rt,warga',
            // Petugas RT tanpa RT tidak punya organisasi assignment: tolak di
            // batas masuk, jangan lahirkan akun tanpa hak yang membingungkan.
            'rt' => 'nullable|required_if:level,petugas_rt',
            'wa' => 'nullable',
        ]);

        $user = User::create([
            'user_id' => 'USR-' . uniqid(),
            'namaLengkap' => $request->namaLengkap,
            'username' => $request->username,
            'pin' => Hash::make($request->pin),
            'level' => $request->level,
            'rt' => $request->rt,
            'wa' => $request->wa,
            'isDefault' => false,
            'status' => 'aktif',
        ]);

        $this->selaraskanAssignment($user);

        AuditLogService::log('tambah_user', 'user', 'Tambah akun: ' . $request->username . ' (' . $request->level . ')');

        return back()->with('success', 'Akun ' . $request->username . ' berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $cakupan = $this->cakupanKelola();
        $user = User::findOrFail($id);
        $this->pastikanDalamCakupan($user, $cakupan);

        $request->validate([
            'namaLengkap' => 'required',
            'username' => ['sometimes', 'required', 'string', 'max:60', 'unique:users,username,' . $user->id],
            // `sometimes`: form di host non-RW tidak mengirim level/rt.
            'level' => 'sometimes|required|in:superadmin,ketua_rw,sekretaris,bendahara,petugas_rt,warga',
            'rt' => 'nullable|required_if:level,petugas_rt',
        ]);

        // Level & RT hanya diubah di host RW: sinkronisasi assignment butuh
        // tenant RW; mengubah kolom level dari host platform/desa membuatnya
        // lepas sinkron dari assignment (sumber hak sesungguhnya).
        $diHostRw = app(TenantContext::class)->rw() !== null;
        $kolom = $diHostRw
            ? ['namaLengkap', 'username', 'level', 'rt', 'wa']
            : ['namaLengkap', 'username', 'wa'];
        $user->update($request->only($kolom));

        if ($diHostRw) {
            $this->selaraskanAssignment($user->fresh());
        }

        AuditLogService::log('ubah_user', 'user', 'Ubah akun: ' . $user->fresh()->username);

        return back()->with('success', 'Data akun ' . $user->fresh()->username . ' berhasil diperbarui.');
    }

    /**
     * Sinkronkan assignment dengan level pilihan form. Sejak fallback
     * users.level pensiun, assignment inilah sumber hak akses; kolom level
     * tinggal catatan tampilan & sasaran notifikasi.
     *
     * Form ini hanya mengelola assignment di subtree RW tenant request:
     * satu peran per tenant (yang lama diganti, bukan ditumpuk), dan
     * assignment platform/desa (dipasang seeder atau konsol) tidak pernah
     * disentuh - superadmin dari form ini pun ber-scope tenant, supaya admin
     * satu RW tidak bisa mencetak admin lintas platform.
     */
    private function selaraskanAssignment(User $user): void
    {
        $rw = app(TenantContext::class)->rw();
        if ($rw === null) {
            return;
        }

        UserRoleAssignment::where('user_id', $user->id)
            ->whereIn('organization_id', Organization::idSubtree($rw->id))
            ->delete();

        // Warga adalah lantai default levelEfektif(); tanpa baris assignment.
        if ($user->level === null || $user->level === 'warga') {
            return;
        }

        $organisasi = $user->level === 'petugas_rt'
            ? $this->organisasiRt($rw, (string) $user->rt)
            : $rw;

        // legacy_level saja ambigu (desa_admin & rw_admin sama-sama
        // 'ketua_rw'): utamakan peran yang scope_type-nya cocok dengan
        // organisasi sasaran, baru jatuh ke padanan level mana pun
        // (super_admin ber-scope platform tapi dipasang di RW tenant).
        $roleId = Role::where('legacy_level', $user->level)
            ->where('scope_type', $organisasi->type)->value('id')
            ?? Role::where('legacy_level', $user->level)->value('id');

        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'organization_id' => $organisasi->id,
        ]);
    }

    /**
     * Organisasi RT di bawah RW tenant; dibuat bila belum ada, dengan
     * normalisasi dan format nama yang sama dengan seed migrasi B1 supaya
     * tidak lahir RT kembar ('1' vs '01').
     */
    private function organisasiRt(Organization $rw, string $rt): Organization
    {
        $rt = str_pad(trim($rt), 2, '0', STR_PAD_LEFT);

        return Organization::firstOrCreate(
            ['slug' => Organization::slugRt($rw, $rt)],
            [
                'parent_id' => $rw->id, 'type' => Organization::TYPE_RT,
                'name' => "RT {$rt}", 'code' => "RT{$rt}", 'status' => 'aktif',
            ]
        );
    }

    /**
     * Buatkan akun login untuk SEMUA KK tenant ini yang belum punya
     * (users.keluarga_id kosong) - jalur massal pasca-impor data warga.
     * PIN acak per akun, tampil SEKALI di halaman hasil; distribusinya
     * urusan pengurus RT (noHP hasil impor sering kosong).
     */
    public function generateWarga()
    {
        $this->cakupanKelola();
        if (app(TenantContext::class)->rw() === null) {
            return redirect()->route('akun.index')
                ->with('error', 'Pembuatan akun warga massal dijalankan dari portal RW masing-masing, bukan dari sini.');
        }

        // KK tenant (tersaring scope) yang belum tertaut akun mana pun.
        $terpakai = User::whereNotNull('keluarga_id')->pluck('keluarga_id');
        $tanpaAkun = \App\Models\Keluarga::whereNotIn('keluarga_id', $terpakai)
            ->orderBy('nama')->get(['keluarga_id', 'nama', 'rt', 'noHP']);

        if ($tanpaAkun->isEmpty()) {
            return redirect()->route('akun.index')->with('success', 'Semua KK sudah punya akun.');
        }

        $hasil = [];
        foreach ($tanpaAkun as $kk) {
            $dasar = preg_replace('/[^a-z0-9]/', '', strtolower($kk->nama)) ?: 'warga';
            $username = $dasar;
            $urut = 1;
            while (User::where('username', $username)->exists()) {
                $username = $dasar . $urut++;
            }

            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            User::create([
                'user_id' => 'USR-' . uniqid(),
                'username' => $username,
                'namaLengkap' => $kk->nama,
                'pin' => Hash::make($pin),
                'level' => 'warga',
                'rt' => $kk->rt,
                'wa' => normalizeWa($kk->noHP),
                'keluarga_id' => $kk->keluarga_id,
                'isDefault' => false,
                'status' => 'aktif',
            ]);
            $hasil[] = ['keluarga' => $kk->nama, 'rt' => $kk->rt, 'username' => $username, 'pin' => $pin];
        }

        AuditLogService::log('generate_akun_warga', 'user', count($hasil) . ' akun warga dibuat massal oleh ' . auth()->user()->username);

        return redirect()->route('akun.index')
            ->with('hasilAkunWarga', $hasil)
            ->with('success', count($hasil) . ' akun warga dibuat. Catat PIN-nya SEKARANG - hanya tampil sekali.');
    }

    public function updatePin(Request $request, $id)
    {
        $cakupan = $this->cakupanKelola();
        $request->validate(['pin' => 'required|digits:6']);
        $user = User::findOrFail($id);
        $this->pastikanDalamCakupan($user, $cakupan);
        $user->update(['pin' => Hash::make($request->pin)]);

        AuditLogService::log('ubah_pin', 'user', 'PIN diubah untuk: ' . $user->username);

        return back()->with('success', 'PIN akun ' . $user->username . ' berhasil diubah.');
    }

    public function toggleStatus($id)
    {
        $cakupan = $this->cakupanKelola();
        $user = User::findOrFail($id);
        $this->pastikanDalamCakupan($user, $cakupan);
        if ($user->isDefault) return back()->with('error', 'Akun default tidak bisa dinonaktifkan.');

        $newStatus = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->update(['status' => $newStatus]);

        AuditLogService::log('toggle_user_status', 'user', 'Status ' . $user->username . ' → ' . $newStatus);

        return back()->with('success', 'Status akun ' . $user->username . ' diubah menjadi ' . $newStatus . '.');
    }

    public function destroy($id)
    {
        $cakupan = $this->cakupanKelola();
        $user = User::findOrFail($id);
        $this->pastikanDalamCakupan($user, $cakupan);
        if ($user->isDefault) return back()->with('error', 'Akun default tidak bisa dihapus.');
        // Tanpa FK cascade; baris yatim = hak hantu bila id user terpakai ulang.
        UserRoleAssignment::where('user_id', $user->id)->delete();
        $user->delete();
        return back()->with('success', 'Akun ' . $user->username . ' berhasil dihapus.');
    }

    // savePermissions() DIHAPUS bersama rute POST /akun/permissions: matriks
    // hak akses kini ditetapkan admin platform lewat Manajemen Desa dan hanya
    // BISA DILIHAT pengurus tenant. Sebelumnya superadmin tenant bisa
    // mengubahnya sendiri - dan karena ketua RW di banyak tenant adalah orang
    // yang sama dengan operatornya, itu berarti batas peran bisa dilonggarkan
    // oleh yang dibatasinya.
}
