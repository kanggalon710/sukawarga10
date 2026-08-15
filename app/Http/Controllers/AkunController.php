<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'rt' => 'nullable',
            'wa' => 'nullable',
        ]);

        User::create([
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

        AuditLogService::log('tambah_user', 'user', 'Tambah akun: ' . $request->username . ' (' . $request->level . ')');

        return back()->with('success', 'Akun ' . $request->username . ' berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'namaLengkap' => 'required',
            'level' => 'required|in:superadmin,ketua_rw,bendahara,petugas_rt,warga',
        ]);

        $user->update($request->only(['namaLengkap', 'level', 'rt', 'wa']));

        return back()->with('success', 'Data akun ' . $user->username . ' berhasil diperbarui.');
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
