<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Gerbang fitur platform lintas tenant (Manajemen Desa, Pembaruan
     * Sistem): rute dijaga role:superadmin dulu, lalu dipersempit di sini
     * karena superadmin ber-scope tenant lolos middleware tapi bukan
     * pemegang kuasa platform.
     */
    protected function pastikanAdminPlatform(): void
    {
        abort_unless(auth()->user()->adalahAdminPlatform(), 403);
    }
}
