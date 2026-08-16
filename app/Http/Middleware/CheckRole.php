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

        // Phase E1: level efektif datang dari assignment ber-scope
        // (user, peran, organisasi) yang relevan dengan tenant request;
        // tanpa assignment, lantainya warga (fallback users.level sudah dicabut).
        $userLevel = $user->levelEfektif();

        // Hierarki level: sumber tunggalnya User::LEVEL_POWER.
        $hierarchy = \App\Models\User::LEVEL_POWER;

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
