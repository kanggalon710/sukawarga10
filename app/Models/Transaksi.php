<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $fillable = [
        'tanggal', 'jenis', 'kategori', 'keterangan', 'jumlah',
        'keluarga_id', 'bulan', 'tahun', 'user_id',
        // 'voided', 'voided_by', 'voided_at' are NOT fillable — only changed via explicit void action
    ];

    protected $casts = [
        'voided' => 'boolean',
        'tanggal' => 'date',
    ];
}
