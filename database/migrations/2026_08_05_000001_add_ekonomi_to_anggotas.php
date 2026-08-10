<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            // Ekonomi per-individu — sensus ekonomi tidak boleh hanya mengukur kepala keluarga.
            $table->string('statusPekerjaan', 30)->nullable()->after('pekerjaan');  // Bekerja / Tidak Bekerja / Mencari Kerja / Sekolah / Mengurus Rumah Tangga / Pensiunan
            $table->string('penghasilan', 20)->nullable()->after('statusPekerjaan'); // rentang, bucket sama dgn KK
        });
    }

    public function down(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropColumn(['statusPekerjaan', 'penghasilan']);
        });
    }
};
