<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    use MilikOrganisasi;

    protected $guarded = [];

    //
}
