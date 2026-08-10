<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            // Parameter keluarga baru untuk keputusan lebih detail (diisi warga via MPWA)
            $table->string('dayaListrik', 20)->nullable()->after('sumberListrik');     // 450VA, 900VA, 1300VA, 2200VA+, Non-meteran
            $table->string('aksesInternet', 20)->nullable()->after('dayaListrik');      // Tidak ada, Seluler, WiFi/Fixed
            $table->integer('jumlahTanggungan')->nullable()->after('aksesInternet');
            $table->string('rawanBencana', 30)->nullable()->after('jumlahTanggungan');  // Tidak, Banjir, Longsor, Kebakaran
            // {penyakitKronis: [TBC,Hipertensi,Diabetes,Jiwa], ikutKB: bool, stunting: bool}
            $table->json('kesehatan')->nullable()->after('kelompokRentan');
        });
    }

    public function down(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->dropColumn(['dayaListrik', 'aksesInternet', 'jumlahTanggungan', 'rawanBencana', 'kesehatan']);
        });
    }
};
