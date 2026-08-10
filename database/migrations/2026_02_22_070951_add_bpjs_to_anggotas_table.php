<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->string('statusBPJS', 30)->nullable()->after('pekerjaan'); // PBI, Mandiri, Tidak Aktif, Belum Terdaftar
            $table->string('noBPJS', 20)->nullable()->after('statusBPJS');
        });
    }

    public function down(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropColumn(['statusBPJS', 'noBPJS']);
        });
    }
};
