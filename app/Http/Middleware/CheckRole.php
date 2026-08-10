<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $userLevel = $user->level ?? 'warga';

        // Define role hierarchy — higher roles can access lower role pages
        $hierarchy = [
            'superadmin' => 5,
            'admin' => 5,
            'ketua_rw' => 4,
            'bendahara' => 3,
            'petugas_rt' => 2,
            'warga' => 1,
        ];

        $userPower = $hierarchy[$userLevel] ?? 0;
        $requiredPower = 0;

        foreach ($roles as $role) {
            $rolePower = $hierarchy[$role] ?? 0;
            if ($rolePower > $requiredPower) $requiredPower = $rolePower;
        }

        // Check if user has at least one of the required roles
        if (in_array($userLevel, $roles) || $userPower >= $requiredPower) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}
