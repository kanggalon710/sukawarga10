<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
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

// Beranda per tingkat hirarki: di luar grup auth karena halaman desa PUBLIK;
// cabang RW/platform mengurus auth-nya sendiri. Nama rute tetap `dashboard`
// supaya route('dashboard') di seluruh aplikasi tidak berubah.
Route::get('/', App\Http\Controllers\BerandaController::class)->name('dashboard');

// Protected Web Routes
Route::middleware('auth')->group(function () {

    // Akun Saya: SEMUA user login (termasuk kepala keluarga) mengganti
    // username & PIN-nya sendiri, dengan verifikasi PIN lama.
    Route::get('/akun-saya', [App\Http\Controllers\AkunSayaController::class, 'index'])->name('akunSaya.index');
    Route::post('/akun-saya', [App\Http\Controllers\AkunSayaController::class, 'simpan'])->name('akunSaya.simpan');

    // Warga Self-Service Profil
    Route::get('/profil', [ProfilWargaController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfilWargaController::class, 'update'])->name('profil.update');
    Route::post('/profil/anggota', [ProfilWargaController::class, 'storeAnggota'])->name('profil.anggota.store');
    Route::delete('/profil/anggota/{anggotaId}', [ProfilWargaController::class, 'destroyAnggota'])->name('profil.anggota.destroy');

    // Warga
    // `fitur:<modul>` = penjaga rute feature flag per tenant (Phase F):
    // modul yang dimatikan lewat setting `fitur_<modul>` menjawab 404,
    // konsisten dengan userCan() yang menyembunyikan menunya.
    // Seluruh area Data Warga memuat PII seluruh tenant - dulu hanya dijaga
    // auth sehingga akun warga bisa membaca dan meng-export semuanya. Warga
    // mengurus datanya sendiri lewat /profil.
    // Impor/ekspor DIPISAH dari CRUD: berkas CSV memuat PII seluruh tenant,
    // bukan hanya RT yang bersangkutan, jadi hanya sekretaris yang memegangnya.
    Route::middleware('fitur:warga')->group(function () {
        Route::middleware('izin:warga.lihat')->group(function () {
            Route::get('/warga', [KeluargaController::class, 'indexWeb'])->name('warga.index');
            Route::get('/warga/create', [KeluargaController::class, 'create'])->name('warga.create');
            Route::get('/warga/{id}/edit', [KeluargaController::class, 'edit'])->whereNumber('id')->name('warga.edit');
        });

        Route::middleware('izin:warga.kelola')->group(function () {
            Route::post('/warga', [KeluargaController::class, 'storeWeb'])->name('warga.store');
            Route::put('/warga/{id}', [KeluargaController::class, 'update'])->whereNumber('id')->name('warga.update');
            Route::delete('/warga/{id}', [KeluargaController::class, 'destroy'])->whereNumber('id')->name('warga.destroy');
            // Anggota Keluarga (nested)
            // whereNumber: tanpa ini POST /warga/import/anggota tertelan rute {id}
            // (id='import') dan fitur import anggota web selalu 404.
            Route::post('/warga/{id}/anggota', [KeluargaController::class, 'storeAnggota'])->whereNumber('id')->name('warga.anggota.store');
            Route::put('/warga/{id}/anggota/{anggotaId}', [KeluargaController::class, 'updateAnggota'])->whereNumber('id')->name('warga.anggota.update');
            Route::delete('/warga/{id}/anggota/{anggotaId}', [KeluargaController::class, 'destroyAnggota'])->whereNumber('id')->name('warga.anggota.destroy');
        });

        // Export & Import Warga
        Route::middleware('izin:warga.ekspor')->group(function () {
            Route::get('/warga/export/keluarga', [App\Http\Controllers\ExportImportController::class, 'exportKeluarga'])->name('warga.export.keluarga');
            Route::get('/warga/export/anggota', [App\Http\Controllers\ExportImportController::class, 'exportAnggota'])->name('warga.export.anggota');
            Route::get('/warga/template/{type}', [App\Http\Controllers\ExportImportController::class, 'downloadTemplate'])->name('warga.template');
        });
        Route::middleware('izin:warga.impor')->group(function () {
            Route::post('/warga/import/keluarga', [App\Http\Controllers\ExportImportController::class, 'importKeluarga'])->name('warga.import.keluarga');
            Route::post('/warga/import/anggota', [App\Http\Controllers\ExportImportController::class, 'importAnggota'])->name('warga.import.anggota');
        });
    });

    // Penagihan (Billing)
    // `izin:<kapabilitas>` = penjaga matriks kapabilitas. Sebelum ini seluruh
    // jalur uang hanya dijaga `auth` + `fitur:`, sehingga akun warga bisa
    // mencatat pembayaran lewat URL langsung (controller-nya tidak mengecek
    // level sama sekali). Urutan `fitur:` dulu supaya modul yang dimatikan
    // tetap menjawab 404, bukan 403.
    Route::middleware('fitur:sampah')->group(function () {
        Route::middleware('izin:sampah.lihat')->get('/sampah', [TransaksiController::class, 'sampahIndex'])->name('billing.sampah');
        Route::middleware('izin:sampah.tagih')->post('/sampah/bayar/{keluarga_id}', [TransaksiController::class, 'sampahStore']);
    });
    Route::middleware('fitur:padaringan')->group(function () {
        Route::middleware('izin:padaringan.lihat')->get('/padaringan', [TransaksiController::class, 'padaringanIndex'])->name('billing.padaringan');
        Route::middleware('izin:padaringan.tagih')->post('/padaringan/bayar/{keluarga_id}', [TransaksiController::class, 'padaringanStore']);
    });

    // Laporan — rute per-bagian, deep-linkable & bisa di-bookmark.
    // Memuat agregat ekonomi & demografi seluruh tenant, jadi bukan untuk warga.
    Route::middleware(['fitur:laporan', 'izin:laporan.lihat'])->group(function () {
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
    });

    // Surat Menyurat
    // Warga boleh mengajukan & melihat surat MILIKNYA SENDIRI (kepemilikan dicek
    // di controller). Kelola & tanda tangan hanya untuk pengurus.
    Route::middleware('fitur:surat')->group(function () {
        Route::middleware('izin:surat.lihat')->group(function () {
            Route::get('/surat', [SuratController::class, 'index'])->name('surat.index');
            Route::get('/surat/{id}', [SuratController::class, 'show'])->name('surat.show');
        });
        // Warga mengajukan (surat.ajukan), sekretaris menerbitkan langsung
        // (surat.buat) - percabangan tahap awalnya ada di controller.
        Route::middleware('izin:surat.buat,surat.ajukan')->post('/surat', [SuratController::class, 'store'])->name('surat.store');
        Route::middleware('izin:surat.cetak')->get('/surat/{id}/cetak', [SuratController::class, 'cetak'])->name('surat.cetak');

        // TTD/tolak: middleware meloloskan pemegang tahap mana pun, lalu
        // controller memastikan tahap YANG SEDANG BERJALAN memang miliknya.
        Route::middleware('izin:surat.ttdRt,surat.ttdRw,surat.finalisasi')->group(function () {
            Route::post('/surat/{id}/approve', [SuratController::class, 'approve'])->name('surat.approve');
        });
        Route::middleware('izin:surat.ttdRt,surat.ttdRw,surat.finalisasi,surat.tolak')->group(function () {
            Route::post('/surat/{id}/reject', [SuratController::class, 'reject'])->name('surat.reject');
        });

        // Menyusun naskah adalah pekerjaan sekretaris; ketua menandatangani.
        Route::middleware('izin:surat.ubahIsi')->put('/surat/{id}/isi', [SuratController::class, 'updateIsi'])->name('surat.isi');
        Route::middleware('izin:surat.ubah')->group(function () {
            Route::put('/surat/{id}', [SuratController::class, 'update'])->name('surat.update');
            Route::patch('/surat/{id}/status', [SuratController::class, 'updateStatus'])->name('surat.updateStatus');
        });
        Route::middleware('izin:surat.hapus')->delete('/surat/{id}', [SuratController::class, 'destroy'])->name('surat.destroy');
    });

    // UMKM Warga — daftar untuk pengurus, pendataan & hapus untuk yang mendata
    Route::middleware('fitur:umkm')->group(function () {
        Route::middleware('izin:umkm.lihat')->get('/umkm', [UmkmController::class, 'index'])->name('umkm.index');
        Route::middleware('izin:umkm.kelola')->group(function () {
            Route::post('/umkm', [UmkmController::class, 'store'])->name('umkm.store');
            Route::delete('/umkm/{id}', [UmkmController::class, 'destroy'])->name('umkm.destroy');
        });
    });

    // Keuangan — mencatat hanya bendahara (setor juga petugas RT); ketua dan
    // sekretaris hanya melihat.
    Route::middleware('fitur:bukukas')->group(function () {
        Route::middleware('izin:bukukas.lihat')->get('/bukukas', [BukuKasController::class, 'index'])->name('bukukas.index');
        Route::middleware('izin:bukukas.catat')->post('/bukukas', [BukuKasController::class, 'store'])->name('bukukas.store');
    });

    Route::middleware('fitur:pengeluaran')->group(function () {
        Route::middleware('izin:pengeluaran.lihat')->get('/kas/pengeluaran', [TransaksiController::class, 'pengeluaranIndex'])->name('kas.pengeluaran');
        Route::middleware('izin:pengeluaran.catat')->post('/kas/pengeluaran', [TransaksiController::class, 'pengeluaranStore'])->name('kas.pengeluaran.store');
    });

    Route::middleware('fitur:setor')->group(function () {
        Route::middleware('izin:setor.lihat')->get('/kas/setor', [SetorSampahController::class, 'index'])->name('kas.setor');
        Route::middleware('izin:setor.catat')->post('/kas/setor', [SetorSampahController::class, 'store'])->name('kas.setor.store');
    });

    Route::middleware('fitur:sumbangan')->group(function () {
        Route::middleware('izin:sumbangan.lihat')->get('/kas/sumbangan', [SumbanganController::class, 'index'])->name('kas.sumbangan');
        Route::middleware('izin:sumbangan.catat')->post('/kas/sumbangan', [SumbanganController::class, 'store'])->name('kas.sumbangan.store');
    });

    // Administrasi
    // Warga boleh melapor & melihat aduannya sendiri; menindaklanjuti hanya pengurus.
    Route::middleware('fitur:aduan')->group(function () {
        // Warga memegang aduan.lihat & aduan.lapor; penyaringan "miliknya"
        // tetap di controller (kepemilikan, bukan kapabilitas).
        Route::middleware('izin:aduan.lihat')->get('/aduan', [AduanController::class, 'index'])->name('aduan.index');
        Route::middleware('izin:aduan.lapor')->post('/aduan', [AduanController::class, 'store'])->name('aduan.store');
        Route::middleware('izin:aduan.tindak')->group(function () {
            Route::put('/aduan/{id}/status', [AduanController::class, 'updateStatus'])->name('aduan.updateStatus');
        });
    });

    Route::middleware('fitur:kegiatan')->group(function () {
        Route::middleware('izin:kegiatan.lihat')->get('/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan.index');
        Route::middleware('izin:kegiatan.kelola')->group(function () {
            Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('kegiatan.store');
            Route::delete('/kegiatan/{id}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');
        });
    });

    // MPWA — mengirim pesan ke seluruh KK tenant dan mengubah aturan notifikasi
    // (`saveAturan` menulis `notif_*` ke app_settings). Biaya gateway dan
    // reputasi nomor RW ada di ujungnya, jadi bukan untuk sembarang akun.
    Route::middleware('fitur:mpwa')->group(function () {
        Route::middleware('izin:mpwa.lihat')->get('/mpwa', [MpwaController::class, 'index'])->name('mpwa.index');
        Route::middleware('izin:mpwa.broadcast')->post('/mpwa/broadcast', [MpwaController::class, 'broadcast'])->name('mpwa.broadcast');
        Route::middleware('izin:mpwa.uji')->post('/mpwa/test', [MpwaController::class, 'testConnection'])->name('mpwa.test');
        Route::middleware('izin:mpwa.kelolaTemplate')->group(function () {
            Route::post('/mpwa/template/save', [MpwaController::class, 'saveTemplate'])->name('mpwa.saveTemplate');
            Route::post('/mpwa/template/delete', [MpwaController::class, 'deleteTemplate'])->name('mpwa.deleteTemplate');
            Route::post('/mpwa/aturan/save', [MpwaController::class, 'saveAturan'])->name('mpwa.saveAturan');
        });
    });


    // Void / Rollback Transaksi — pengawasan, bukan pencatatan: bendahara yang
    // mencatat sengaja tidak boleh membatalkan catatannya sendiri.
    Route::middleware('izin:transaksi.void')->group(function () {
        Route::post('/transaksi/{id}/void', [TransaksiController::class, 'voidTransaksi'])->name('transaksi.void');
    });

    // Pendaftaran Baru — sekretaris memeriksa berkasnya, ketua memutuskan.
    Route::middleware('fitur:pendaftaran')->group(function () {
        Route::middleware('izin:pendaftaran.lihat')->get('/pendaftaran', [PendaftaranController::class, 'index'])->name('pendaftaran.index');
        Route::middleware('izin:pendaftaran.putuskan')->group(function () {
            Route::post('/pendaftaran/{id}/approve', [PendaftaranController::class, 'approve'])->name('pendaftaran.approve');
            Route::post('/pendaftaran/{id}/reject', [PendaftaranController::class, 'reject'])->name('pendaftaran.reject');
        });
    });

    // Admin — Manajemen Akun bertingkat: middleware meloloskan ketua_rw ke
    // atas, lalu AkunController::cakupanKelola() mempersempit per host
    // (RW = superadmin tenant, desa = admin desa, platform = owner) dan
    // membatasi akun mana yang terlihat/tersentuh.
    Route::middleware('fitur:akun')->group(function () {
        Route::middleware('izin:akun.lihat')->get('/akun', [AkunController::class, 'index'])->name('akun.index');
        Route::middleware('izin:akun.kelola')->group(function () {
            Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
            Route::put('/akun/{id}', [AkunController::class, 'update'])->name('akun.update');
            Route::post('/akun/{id}/pin', [AkunController::class, 'updatePin'])->name('akun.updatePin');
            Route::post('/akun/{id}/toggle', [AkunController::class, 'toggleStatus'])->name('akun.toggleStatus');
            Route::delete('/akun/{id}', [AkunController::class, 'destroy'])->name('akun.destroy');
            Route::post('/akun/generate-warga', [AkunController::class, 'generateWarga'])->name('akun.generateWarga');
        });
    });

    // Admin — Log & Settings. Reset data dan hapus duplikat sengaja dipisah
    // (`pengaturan.pemeliharaan`): aksinya menghapus data satu tenant, jadi
    // bukan bagian dari "mengubah pengaturan RW" yang dipegang ketua.
    Route::middleware(['fitur:log', 'izin:log.lihat'])->get('/log', [LogController::class, 'index'])->name('log.index');
    Route::middleware('fitur:pengaturan')->group(function () {
        Route::middleware('izin:pengaturan.lihat')->get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
        Route::middleware('izin:pengaturan.ubah')->post('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
        Route::middleware('izin:pengaturan.pemeliharaan')->group(function () {
            Route::post('/pengaturan/reset-data', [PengaturanController::class, 'resetData'])->name('pengaturan.reset');
            Route::post('/pengaturan/remove-duplicates', [PengaturanController::class, 'removeDuplicates'])->name('pengaturan.removeDuplicates');
        });
    });

    // Platform — Manajemen Desa (buka tenant baru). Dijaga role:superadmin
    // lalu dipersempit ke super_admin PLATFORM di controller. Sengaja TANPA
    // middleware fitur:<modul>: ini fitur platform lintas tenant, bukan modul
    // tenant yang boleh dimatikan lewat feature flag (aturan AGENTS.md #11
    // berlaku untuk modul di getAllMenuItems).
    Route::middleware('izin:platform.tenant')->group(function () {
        Route::get('/tenant', [App\Http\Controllers\TenantController::class, 'index'])->name('tenant.index');
        Route::post('/tenant', [App\Http\Controllers\TenantController::class, 'store'])->name('tenant.store');
        Route::put('/tenant/{id}', [App\Http\Controllers\TenantController::class, 'update'])->name('tenant.update');
        Route::delete('/tenant/{id}', [App\Http\Controllers\TenantController::class, 'destroyDesa'])->name('tenant.destroy');
        Route::post('/tenant/{id}/admin', [App\Http\Controllers\TenantController::class, 'buatAdminDesa'])->name('tenant.adminDesa');
        Route::put('/tenant/rw/{id}', [App\Http\Controllers\TenantController::class, 'updateRw'])->name('tenant.rw.update');
        Route::post('/tenant/rw/{id}/toggle', [App\Http\Controllers\TenantController::class, 'toggleRw'])->name('tenant.rw.toggle');
        Route::delete('/tenant/rw/{id}', [App\Http\Controllers\TenantController::class, 'destroyRw'])->name('tenant.rw.destroy');

        // Matriks kapabilitas per tenant: HANYA admin platform. Pengurus RW
        // (termasuk ketua) melihatnya read-only di Manajemen Akun.
        Route::middleware('izin:platform.matriks')->group(function () {
            Route::get('/tenant/rw/{id}/matriks', [App\Http\Controllers\TenantController::class, 'matriks'])->name('tenant.rw.matriks');
            Route::post('/tenant/rw/{id}/matriks', [App\Http\Controllers\TenantController::class, 'simpanMatriks'])->name('tenant.rw.matriks.simpan');
        });
    });

    // Pembaruan Sistem: cek & jalankan update dari branch production.
    Route::middleware('izin:platform.pembaruan')->group(function () {
        Route::get('/pembaruan', [App\Http\Controllers\PembaruanController::class, 'index'])->name('pembaruan.index');
        Route::post('/pembaruan/cek', [App\Http\Controllers\PembaruanController::class, 'cek'])->name('pembaruan.cek');
        Route::post('/pembaruan/jalankan', [App\Http\Controllers\PembaruanController::class, 'jalankan'])->name('pembaruan.jalankan');
    });

    // Global Search API (parameterized to prevent SQL injection)
    // Hanya pengurus: hasilnya memuat nama + nomor HP seluruh warga, jadi tidak
    // boleh terbuka untuk akun level warga.
    // `fitur:warga` ikut: hasilnya adalah data modul Warga, jadi kalau modulnya
    // dimatikan untuk tenant ini pencariannya pun tidak ada.
    Route::middleware(['fitur:warga', 'izin:warga.cari'])->get('/search', function (\Illuminate\Http\Request $request) {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);
        $results = \App\Models\Keluarga::where('nama', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%')
            ->orWhere('noHP', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%')
            ->limit(10)
            ->get(['id', 'nama', 'rt', 'noHP', 'keluarga_id']);
        return response()->json($results);
    })->name('search');
});
