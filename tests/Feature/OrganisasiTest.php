<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B1 multi-tenant: hierarki organisasi + pemetaan domain.
 * Migrasi 2026_08_15_000004 menanam keadaan existing; tes ini mengunci
 * bentuknya supaya fase berikutnya (resolver, scoping) berdiri di fondasi
 * yang terjamin, bukan asumsi.
 */
class OrganisasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarki_existing_tertanam_setelah_migrasi(): void
    {
        $platform = Organization::where('type', Organization::TYPE_PLATFORM)->first();
        $desa = Organization::where('type', Organization::TYPE_DESA)->first();
        $rw = Organization::where('type', Organization::TYPE_RW)->first();

        $this->assertNotNull($platform);
        $this->assertNull($platform->parent_id, 'Platform adalah akar, tidak boleh punya induk.');
        $this->assertNotNull($desa);
        $this->assertSame($platform->id, $desa->parent_id);
        $this->assertNotNull($rw);
        $this->assertSame($desa->id, $rw->parent_id);
        $this->assertSame('RW10', $rw->code);
    }

    public function test_domain_produksi_menunjuk_rw_10(): void
    {
        $domain = Domain::where('hostname', 'paru.jabnet.id')->first();

        $this->assertNotNull($domain);
        $this->assertTrue($domain->is_primary);
        $this->assertSame('aktif', $domain->status);
        $this->assertSame(Organization::TYPE_RW, $domain->organization->type);
    }

    public function test_hostname_legacy_terdaftar_sebagai_non_primary(): void
    {
        $legacy = Domain::where('hostname', 'sukawarga10.jabnet.id')->first();

        $this->assertNotNull($legacy);
        $this->assertFalse($legacy->is_primary);
        $this->assertSame('legacy', $legacy->status);
        // Menunjuk organisasi yang SAMA dengan domain utama: resolver nanti
        // harus mengarahkan keduanya ke satu tenant, bukan dua tenant kembar.
        $this->assertSame(
            Domain::where('hostname', 'paru.jabnet.id')->value('organization_id'),
            $legacy->organization_id
        );
    }

    public function test_hostname_tidak_boleh_kembar(): void
    {
        $rw = Organization::where('type', Organization::TYPE_RW)->first();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Domain::create([
            'organization_id' => $rw->id,
            'hostname' => 'paru.jabnet.id',
        ]);
    }

    public function test_leluhur_menelusuri_rantai_induk(): void
    {
        $rw = Organization::where('type', Organization::TYPE_RW)->first();
        $rt = Organization::create([
            'parent_id' => $rw->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01', 'slug' => 'rt-01-uji',
        ]);

        $this->assertSame('SUKAKARYA', $rt->leluhur(Organization::TYPE_DESA)?->code);
        $this->assertSame('PLATFORM', $rt->leluhur(Organization::TYPE_PLATFORM)?->code);
        // Termasuk dirinya sendiri bila tipe sudah cocok.
        $this->assertSame($rt->id, $rt->leluhur(Organization::TYPE_RT)?->id);
        $this->assertNull(
            Organization::where('type', Organization::TYPE_PLATFORM)->first()->leluhur('rt'),
            'Menelusuri ke bawah tidak mungkin: leluhur hanya naik.'
        );
    }

    public function test_menghapus_induk_tidak_melenyapkan_anak(): void
    {
        $rw = Organization::where('type', Organization::TYPE_RW)->first();
        $rt = Organization::create([
            'parent_id' => $rw->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 99', 'code' => 'RT99', 'slug' => 'rt-99-uji',
        ]);

        $rw->delete();

        // nullOnDelete: anak yatim, bukan ikut terhapus diam-diam.
        $this->assertDatabaseHas('organizations', ['id' => $rt->id, 'parent_id' => null]);
    }
}
