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
        abort_unless(auth()->user()->adalahAdminPlatform(), 403);

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
        abort_unless(auth()->user()->adalahAdminPlatform(), 403);

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
}
