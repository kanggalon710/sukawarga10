<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tanggalLahir' => 'date',
    ];

    /**
     * Keluarga (KK) yang menaungi anggota ini.
     */
    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'keluarga_id', 'keluarga_id');
    }
}
