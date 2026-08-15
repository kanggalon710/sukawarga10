<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KeluargaController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\BukuKasController;
use App\Http\Controllers\UmkmController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\AduanController;
use App\Http\Controllers\SuratController;
use App\Http\Controllers\SumbanganController;
use App\Http\Controllers\SetorSampahController;
use App\Http\Controllers\AkunController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\MpwaController;
use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\ProfilWargaController;

// Public Routes
Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->name('login.post');
Route::post('/login/register', [WebAuthController::class, 'registerWarga'])->name('login.register');
Route::post('/login/forgot', [WebAuthController::class, 'forgotCredentials'])->name('login.forgot');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Protected Web Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Warga Self-Service Profil
    Route::get('/profil', [ProfilWargaController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfilWargaController::class, 'update'])->name('profil.update');
    Route::post('/profil/anggota', [ProfilWargaController::class, 'storeAnggota'])->name('profil.anggota.store');
    Route::delete('/profil/anggota/{anggotaId}', [ProfilWargaController::class, 'destroyAnggota'])->name('profil.anggota.destroy');

    // Warga
    Route::get('/warga', [KeluargaController::class, 'indexWeb'])->name('warga.index');
    Route::get('/warga/create', [KeluargaController::class, 'create'])->name('warga.create');
    Route::post('/warga', [KeluargaController::class, 'storeWeb'])->name('warga.store');
    Route::get('/warga/{id}/edit', [KeluargaController::class, 'edit'])->name('warga.edit');
    Route::put('/warga/{id}', [KeluargaController::class, 'update'])->name('warga.update');
    Route::delete('/warga/{id}', [KeluargaController::class, 'destroy'])->name('warga.destroy');
    // Anggota Keluarga (nested)
    Route::post('/warga/{id}/anggota', [KeluargaController::class, 'storeAnggota'])->name('warga.anggota.store');
    Route::put('/warga/{id}/anggota/{anggotaId}', [KeluargaController::class, 'updateAnggota'])->name('warga.anggota.update');
    Route::delete('/warga/{id}/anggota/{anggotaId}', [KeluargaController::class, 'destroyAnggota'])->name('warga.anggota.destroy');

    // Export & Import Warga
    Route::get('/warga/export/keluarga', [App\Http\Controllers\ExportImportController::class, 'exportKeluarga'])->name('warga.export.keluarga');
    Route::get('/warga/export/anggota', [App\Http\Controllers\ExportImportController::class, 'exportAnggota'])->name('warga.export.anggota');
    Route::post('/warga/import/keluarga', [App\Http\Controllers\ExportImportController::class, 'importKeluarga'])->name('warga.import.keluarga');
    Route::post('/warga/import/anggota', [App\Http\Controllers\ExportImportController::class, 'importAnggota'])->name('warga.import.anggota');
    Route::get('/warga/template/{type}', [App\Http\Controllers\ExportImportController::class, 'downloadTemplate'])->name('warga.template');

    // Penagihan (Billing)
    Route::get('/sampah', [TransaksiController::class, 'sampahIndex'])->name('billing.sampah');
    Route::post('/sampah/bayar/{keluarga_id}', [TransaksiController::class, 'sampahStore']);
    Route::get('/padaringan', [TransaksiController::class, 'padaringanIndex'])->name('billing.padaringan');
    Route::post('/padaringan/bayar/{keluarga_id}', [TransaksiController::class, 'padaringanStore']);

    // Laporan — rute per-bagian, deep-linkable & bisa di-bookmark
    Route::get('/laporan', function (\Illuminate\Http\Request $r) {
        // Kompatibel mundur: /laporan?tab=demografi → /laporan/demografi
        $legacy = ['ranking' => 'ringkasan', 'bulanan' => 'ringkasan'];
        $tab = $r->query('tab', 'ringkasan');
        $tab = $legacy[$tab] ?? $tab;
        return redirect()->route('laporan.tab', array_filter([
            'tab' => in_array($tab, ['ringkasan', 'demografi', 'ekonomi', 'permukiman']) ? $tab : 'ringkasan',
            'tahun' => $r->query('tahun'),
        ]));
    })->name('laporan.index');
    Route::get('/laporan/{tab}', [LaporanController::class, 'index'])
        ->whereIn('tab', ['ringkasan', 'demografi', 'ekonomi', 'permukiman'])
        ->name('laporan.tab');

    // Surat Menyurat
    // Warga boleh mengajukan & melihat surat MILIKNYA SENDIRI (kepemilikan dicek
    // di controller). Kelola & tanda tangan hanya untuk pengurus.
    Route::get('/surat', [SuratController::class, 'index'])->name('surat.index');
    Route::post('/surat', [SuratController::class, 'store'])->name('surat.store');
    Route::get('/surat/{id}/cetak', [SuratController::class, 'cetak'])->name('surat.cetak');
    Route::get('/surat/{id}', [SuratController::class, 'show'])->name('surat.show');

    // TTD/tolak: RT ke atas (tahap mana yang boleh ditandatangani dicek di controller)
    Route::middleware('role:petugas_rt')->group(function () {
        Route::post('/surat/{id}/approve', [SuratController::class, 'approve'])->name('surat.approve');
        Route::post('/surat/{id}/reject', [SuratController::class, 'reject'])->name('surat.reject');
    });

    // Ubah/hapus surat: ketua RW ke atas
    Route::middleware('role:ketua_rw')->group(function () {
        Route::put('/surat/{id}', [SuratController::class, 'update'])->name('surat.update');
        Route::patch('/surat/{id}/status', [SuratController::class, 'updateStatus'])->name('surat.updateStatus');
        Route::delete('/surat/{id}', [SuratController::class, 'destroy'])->name('surat.destroy');
    });


    // UMKM Warga — daftar boleh dilihat semua, pendataan & hapus hanya pengurus
    Route::get('/umkm', [UmkmController::class, 'index'])->name('umkm.index');
    Route::middleware('role:petugas_rt')->group(function () {
        Route::post('/umkm', [UmkmController::class, 'store'])->name('umkm.store');
        Route::delete('/umkm/{id}', [UmkmController::class, 'destroy'])->name('umkm.destroy');
    });

    // Keuangan
    Route::get('/bukukas', [BukuKasController::class, 'index'])->name('bukukas.index');
    Route::post('/bukukas', [BukuKasController::class, 'store'])->name('bukukas.store');

    Route::get('/kas/pengeluaran', [TransaksiController::class, 'pengeluaranIndex'])->name('kas.pengeluaran');
    Route::post('/kas/pengeluaran', [TransaksiController::class, 'pengeluaranStore'])->name('kas.pengeluaran.store');

    Route::get('/kas/setor', [SetorSampahController::class, 'index'])->name('kas.setor');
    Route::post('/kas/setor', [SetorSampahController::class, 'store'])->name('kas.setor.store');

    Route::get('/kas/sumbangan', [SumbanganController::class, 'index'])->name('kas.sumbangan');
    Route::post('/kas/sumbangan', [SumbanganController::class, 'store'])->name('kas.sumbangan.store');

    // Administrasi
    // Warga boleh melapor & melihat aduannya sendiri; menindaklanjuti hanya pengurus.
    Route::get('/aduan', [AduanController::class, 'index'])->name('aduan.index');
    Route::post('/aduan', [AduanController::class, 'store'])->name('aduan.store');
    Route::middleware('role:petugas_rt')->group(function () {
        Route::put('/aduan/{id}/status', [AduanController::class, 'updateStatus'])->name('aduan.updateStatus');
    });

    Route::get('/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan.index');
    Route::middleware('role:petugas_rt')->group(function () {
        Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('kegiatan.store');
        Route::delete('/kegiatan/{id}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');
    });

    // MPWA
    Route::get('/mpwa', [MpwaController::class, 'index'])->name('mpwa.index');
    Route::post('/mpwa/broadcast', [MpwaController::class, 'broadcast'])->name('mpwa.broadcast');
    Route::post('/mpwa/test', [MpwaController::class, 'testConnection'])->name('mpwa.test');
    Route::post('/mpwa/template/save', [MpwaController::class, 'saveTemplate'])->name('mpwa.saveTemplate');
    Route::post('/mpwa/template/delete', [MpwaController::class, 'deleteTemplate'])->name('mpwa.deleteTemplate');
    Route::post('/mpwa/aturan/save', [MpwaController::class, 'saveAturan'])->name('mpwa.saveAturan');


    // Void / Rollback Transaksi (admin only)
    Route::middleware('role:superadmin,ketua_rw')->group(function () {
        Route::post('/transaksi/{id}/void', [TransaksiController::class, 'voidTransaksi'])->name('transaksi.void');
    });

    // Pendaftaran Baru (Admin only)
    Route::middleware('role:superadmin,ketua_rw')->group(function () {
        Route::get('/pendaftaran', [PendaftaranController::class, 'index'])->name('pendaftaran.index');
        Route::post('/pendaftaran/{id}/approve', [PendaftaranController::class, 'approve'])->name('pendaftaran.approve');
        Route::post('/pendaftaran/{id}/reject', [PendaftaranController::class, 'reject'])->name('pendaftaran.reject');
    });

    // Admin — User Management (superadmin only)
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/akun', [AkunController::class, 'index'])->name('akun.index');
        Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
        Route::put('/akun/{id}', [AkunController::class, 'update'])->name('akun.update');
        Route::post('/akun/{id}/pin', [AkunController::class, 'updatePin'])->name('akun.updatePin');
        Route::post('/akun/{id}/toggle', [AkunController::class, 'toggleStatus'])->name('akun.toggleStatus');
        Route::delete('/akun/{id}', [AkunController::class, 'destroy'])->name('akun.destroy');
        Route::post('/akun/permissions', [AkunController::class, 'savePermissions'])->name('akun.savePermissions');
    });

    // Admin — Log & Settings (superadmin + ketua_rw)
    Route::middleware('role:superadmin,ketua_rw')->group(function () {
        Route::get('/log', [LogController::class, 'index'])->name('log.index');
        Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
        Route::post('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
        Route::post('/pengaturan/reset-data', [PengaturanController::class, 'resetData'])->name('pengaturan.reset');
        Route::post('/pengaturan/remove-duplicates', [PengaturanController::class, 'removeDuplicates'])->name('pengaturan.removeDuplicates');
    });

    // Global Search API (parameterized to prevent SQL injection)
    // Hanya pengurus: hasilnya memuat nama + nomor HP seluruh warga, jadi tidak
    // boleh terbuka untuk akun level warga.
    Route::middleware('role:petugas_rt')->get('/search', function (\Illuminate\Http\Request $request) {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);
        $results = \App\Models\Keluarga::where('nama', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%')
            ->orWhere('noHP', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%')
            ->limit(10)
            ->get(['id', 'nama', 'rt', 'noHP', 'keluarga_id']);
        return response()->json($results);
    })->name('search');
});
