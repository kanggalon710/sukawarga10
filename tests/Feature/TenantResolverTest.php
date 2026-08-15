<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Organization;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B2 multi-tenant: pemetaan hostname ke tenant.
 * Aturan intinya dari §14 dokumen arsitektur: hostname tak terdaftar ditolak,
 * TIDAK PERNAH fallback diam-diam ke tenant lain.
 */
class TenantResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_hostname_dev_teregistrasi_sehingga_suite_dan_artisan_serve_jalan(): void
    {
        // Host bawaan seluruh feature test adalah localhost; kalau ini gagal,
        // resolver sedang mematahkan lingkungan pengembangan.
        $this->get('/login')->assertOk();
    }

    public function test_hostname_produksi_dikenali(): void
    {
        $this->get('https://paru.jabnet.id/login')->assertOk();
    }

    public function test_hostname_legacy_masih_dilayani(): void
    {
        $this->get('https://sukawarga10.jabnet.id/login')->assertOk();
    }

    public function test_hostname_asing_ditolak_404_bukan_fallback(): void
    {
        $this->get('https://desa-lain.example.id/login')->assertNotFound();
        $this->get('https://paru.jabnet.id.jahat.example/login')->assertNotFound();
    }

    public function test_domain_nonaktif_ditolak(): void
    {
        Domain::where('hostname', 'sukawarga10.jabnet.id')->update(['status' => 'nonaktif']);

        $this->get('https://sukawarga10.jabnet.id/login')->assertNotFound();
        // Domain utama tidak ikut terpengaruh.
        $this->get('https://paru.jabnet.id/login')->assertOk();
    }

    public function test_organisasi_nonaktif_menolak_seluruh_domainnya(): void
    {
        Organization::where('slug', 'rw-10-sukakarya')->update(['status' => 'nonaktif']);

        $this->get('https://paru.jabnet.id/login')->assertNotFound();
    }

    public function test_context_terisi_dengan_rantai_hierarki(): void
    {
        $this->get('https://paru.jabnet.id/login')->assertOk();

        $context = app(TenantContext::class);
        $this->assertTrue($context->sudahDitetapkan());
        $this->assertSame('paru.jabnet.id', $context->hostname());
        $this->assertSame('RW10', $context->rw()?->code);
        $this->assertSame('SUKAKARYA', $context->desa()?->code);
        $this->assertSame('PLATFORM', $context->platform()?->code);
    }

    public function test_hostname_legacy_dan_utama_menunjuk_tenant_yang_sama(): void
    {
        $this->get('https://paru.jabnet.id/login')->assertOk();
        $utama = app(TenantContext::class)->organisasi()->id;

        $this->get('https://sukawarga10.jabnet.id/login')->assertOk();
        $legacy = app(TenantContext::class)->organisasi()->id;

        $this->assertSame($utama, $legacy);
    }

    public function test_health_check_tidak_terikat_hostname_tenant(): void
    {
        // `/up` dipakai monitor infrastruktur yang memanggil lewat IP/host
        // internal; resolver sengaja hanya dipasang di grup web.
        $this->get('https://host-internal-monitor.local/up')->assertOk();
    }
}
