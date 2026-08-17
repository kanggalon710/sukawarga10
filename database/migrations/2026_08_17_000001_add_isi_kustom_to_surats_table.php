<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi surat hasil suntingan pengurus (HTML tersanitasi). NULL berarti
     * halaman cetak memakai template otomatis per kode surat.
     */
    public function up(): void
    {
        Schema::table('surats', function (Blueprint $table) {
            $table->longText('isi_kustom')->nullable()->after('keperluan');
        });
    }

    public function down(): void
    {
        Schema::table('surats', function (Blueprint $table) {
            $table->dropColumn('isi_kustom');
        });
    }
};
