<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class LogController extends Controller
{
    public function index()
    {
        // Dipaginasi, bukan limit(200) diam-diam: audit_logs tumbuh terus dan
        // batas tersembunyi membuat log lama tampak tidak pernah ada.
        $logs = AuditLog::orderByDesc('tanggal')->paginate(50)->withQueryString();
        return view('admin.log', compact('logs'));
    }
}
