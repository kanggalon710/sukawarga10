<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Akun Saya: setiap user login mengganti username & PIN-nya SENDIRI, dengan
 * verifikasi PIN lama - kepala keluarga tidak perlu pengurus untuk urusan
 * kredensialnya, dan pengurus tidak perlu tahu PIN siapa pun.
 */
class AkunSayaController extends Controller
{
    public function index()
    {
        return view('akun-saya');
    }

    public function simpan(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username,'.$user->id],
            'pin_lama' => ['required', 'digits:6'],
            'pin_baru' => ['nullable', 'digits:6'],
        ]);

        if (! Hash::check($validated['pin_lama'], $user->pin)) {
            return back()->withErrors(['pin_lama' => 'PIN lama salah.'])->withInput();
        }

        $perubahan = ['username' => $validated['username']];
        if (! empty($validated['pin_baru'])) {
            $perubahan['pin'] = Hash::make($validated['pin_baru']);
        }
        $user->update($perubahan);

        AuditLogService::log('ubah_akun_sendiri', 'user',
            'Ganti kredensial sendiri: '.$validated['username']);

        return back()->with('success', 'Akun Anda berhasil diperbarui.'
            .(! empty($validated['pin_baru']) ? ' Gunakan PIN baru saat login berikutnya.' : ''));
    }
}
