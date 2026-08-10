<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Sanctum token guard)
|--------------------------------------------------------------------------
| 2026-06-05: rute lama dihapus karena referensi kelas/method TIDAK ADA
| (AuthController; KeluargaController@index/store/syncBatch;
| TransaksiController@index/store/syncBatch) — sisa arsitektur PWA
| offline-sync lama yang sudah digantikan app server-rendered.
| Autentikasi nyata memakai web-guard (WebAuthController) via routes/web.php.
| Endpoint /user standar Sanctum dipertahankan (valid, dipakai bila token
| API diaktifkan di kemudian hari).
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
