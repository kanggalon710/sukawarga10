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
    public function index()
    {
        $user = auth()->user();
        if (!$user->canManageUsers() && !$user->isSuperAdmin()) {
            return redirect('/')->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }
        // CASE portabel (MySQL + SQLite). FIELD() hanya tersedia di MySQL.
        $users = User::orderByRaw("CASE level
                WHEN 'superadmin' THEN 1 WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 1
                WHEN 'ketua_rw' THEN 2 WHEN 'bendahara' THEN 3
                WHEN 'petugas_rt' THEN 4 WHEN 'warga' THEN 5 ELSE 6 END")
            ->orderBy('username')->get();
        return view('admin.akun', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'namaLengkap' => 'required',
            'username' => 'required|unique:users',
            'pin' => 'required|digits:6',
            'level' => 'required|in:superadmin,ketua_rw,bendahara,petugas_rt,warga',
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
        $user = User::findOrFail($id);
        $request->validate([
            'namaLengkap' => 'required',
            'level' => 'required|in:superadmin,ketua_rw,bendahara,petugas_rt,warga',
            'rt' => 'nullable|required_if:level,petugas_rt',
        ]);

        $user->update($request->only(['namaLengkap', 'level', 'rt', 'wa']));

        $this->selaraskanAssignment($user->fresh());

        return back()->with('success', 'Data akun ' . $user->username . ' berhasil diperbarui.');
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

    public function updatePin(Request $request, $id)
    {
        $request->validate(['pin' => 'required|digits:6']);
        $user = User::findOrFail($id);
        $user->update(['pin' => Hash::make($request->pin)]);

        AuditLogService::log('ubah_pin', 'user', 'PIN diubah untuk: ' . $user->username);

        return back()->with('success', 'PIN akun ' . $user->username . ' berhasil diubah.');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        if ($user->isDefault) return back()->with('error', 'Akun default tidak bisa dinonaktifkan.');

        $newStatus = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->update(['status' => $newStatus]);

        AuditLogService::log('toggle_user_status', 'user', 'Status ' . $user->username . ' → ' . $newStatus);

        return back()->with('success', 'Status akun ' . $user->username . ' diubah menjadi ' . $newStatus . '.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->isDefault) return back()->with('error', 'Akun default tidak bisa dihapus.');
        // Tanpa FK cascade; baris yatim = hak hantu bila id user terpakai ulang.
        UserRoleAssignment::where('user_id', $user->id)->delete();
        $user->delete();
        return back()->with('success', 'Akun ' . $user->username . ' berhasil dihapus.');
    }

    public function savePermissions(Request $request)
    {
        $perms = $request->input('permissions');
        if (!is_array($perms)) {
            return response()->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        // Ensure superadmin always has full access
        $allMenus = array_map(fn($m) => $m['key'], getAllMenuItems());
        $perms['superadmin'] = $allMenus;

        \App\Models\AppSetting::simpan('role_permissions', json_encode($perms));

        return response()->json(['success' => true]);
    }
}
