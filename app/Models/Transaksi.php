<?php

namespace App\Models;

use App\Models\Concerns\MilikOrganisasi;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use MilikOrganisasi;

    // Harus cocok dengan kolom tabel `transaksis`. Sebelumnya daftar ini menyebut
    // kolom yang tidak ada (keluarga_id, bulan, tahun, user_id) dan melewatkan kolom
    // yang benar-benar ditulis controller, sehingga transaksi_id & kas dibuang diam-diam
    // oleh mass assignment dan INSERT gagal (keduanya NOT NULL, transaksi_id UNIQUE).
    protected $fillable = [
        'transaksi_id', 'tanggal', 'jenis', 'kas', 'kategori', 'keterangan',
        'jumlah', 'refKeluargaId', 'periode', 'refNo', 'kwitansiNo', 'operator',
        // 'voided', 'void_reason', 'void_by', 'void_at' sengaja TIDAK fillable —
        // hanya boleh berubah lewat aksi void eksplisit di TransaksiController.
    ];

    protected $casts = [
        'voided' => 'boolean',
        'tanggal' => 'date',
        'periode' => 'array', // kunci periode iuran, mis. ['JAN-M1','JAN-M2'] atau ['JAN','FEB']
    ];
}
