<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal WAJIB dilayani lewat HTTPS.
 *
 * Ada karena insiden 2026-08-18: tenant baru yang sertifikatnya belum terbit
 * tetap melayani halaman login lewat HTTP polos. Cookie sesi ber-flag `secure`
 * (SESSION_SECURE_COOKIE=true) tidak pernah disimpan browser di koneksi
 * telanjang, jadi setiap pengiriman form menabrak "419 PAGE EXPIRED" tanpa
 * petunjuk apa pun - dan yang lebih buruk, PIN warga sempat terkirim tanpa
 * enkripsi sebelum ditolak.
 *
 * Pengalihan dimatikan di luar produksi supaya `php artisan serve` dan suite
 * tes tetap jalan di http://localhost.
 */
class PaksaHttpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permintaan_http_dialihkan_ke_https_saat_aktif(): void
    {
        config(['app.paksa_https' => true]);

        $this->get('http://localhost/login')
            ->assertStatus(301)
            ->assertRedirect('https://localhost/login');
    }

    public function test_query_string_dan_path_dipertahankan(): void
    {
        config(['app.paksa_https' => true]);

        $this->get('http://localhost/laporan/ringkasan?tahun=2026')
            ->assertRedirect('https://localhost/laporan/ringkasan?tahun=2026');
    }

    public function test_permintaan_https_tidak_dialihkan(): void
    {
        config(['app.paksa_https' => true]);

        $this->get('https://localhost/login')->assertOk();
    }

    public function test_tidak_aktif_di_luar_produksi(): void
    {
        // Bawaannya mati di environment tes; tanpa ini seluruh suite yang
        // memakai http://localhost akan berubah jadi rantai redirect.
        $this->assertFalse(config('app.paksa_https'));
        $this->get('http://localhost/login')->assertOk();
    }

    public function test_jalur_validasi_acme_tidak_pernah_dialihkan(): void
    {
        // Penerbitan sertifikat tenant BARU bergantung pada jalur ini bisa
        // diakses lewat HTTP polos - saat itu HTTPS-nya memang belum ada.
        config(['app.paksa_https' => true]);

        $this->get('http://localhost/.well-known/acme-challenge/uji')
            ->assertStatus(404);
    }

    public function test_bisa_dimatikan_lewat_konfigurasi(): void
    {
        // Katup darurat: kalau deteksi skema meleset di suatu lingkungan,
        // operator bisa mematikannya lewat .env tanpa menunggu rilis.
        config(['app.paksa_https' => false]);

        $this->get('http://localhost/login')->assertOk();
    }
}
