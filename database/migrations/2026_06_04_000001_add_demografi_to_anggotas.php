<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            // Demografi per-individu — mengaktifkan piramida penduduk, tingkat pendidikan, APS, dll.
            $table->string('pendidikan', 30)->nullable()->after('pekerjaan');       // Tidak/Belum Sekolah, SD, SMP, SMA/SMK, D1-D3, S1, S2/S3
            $table->string('statusSekolah', 30)->nullable()->after('pendidikan');    // Belum sekolah, Masih sekolah, Putus sekolah, Lulus
            $table->string('statusPerkawinan', 20)->nullable()->after('statusSekolah'); // Belum kawin, Kawin, Cerai hidup, Cerai mati
            $table->string('agama', 20)->nullable()->after('statusPerkawinan');
            $table->string('statusHidup', 15)->default('hidup')->after('agama');     // hidup, pindah, wafat
            $table->string('kondisiKhusus', 60)->nullable()->after('statusHidup');   // disabilitas / penyakit kronis / ibu hamil / -
        });
    }

    public function down(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropColumn(['pendidikan', 'statusSekolah', 'statusPerkawinan', 'agama', 'statusHidup', 'kondisiKhusus']);
        });
    }
};
