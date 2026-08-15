<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use App\Models\Concerns\ScopedKeOrganisasi;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // ScopedKeOrganisasi: pembacaan otomatis dibatasi tenant request (Phase E2),
    // dijaga tests/Feature/IsolasiTenantTest.php.
    use MilikOrganisasi, ScopedKeOrganisasi;

    protected $guarded = [];

    //
}
