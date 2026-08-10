<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class LogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::orderByDesc('tanggal')->limit(200)->get();
        return view('admin.log', compact('logs'));
    }
}
