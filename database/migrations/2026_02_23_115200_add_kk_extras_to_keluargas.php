<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->date('tanggalLahirKK')->nullable()->after('nik');
            $table->string('jenisKelaminKK', 10)->nullable()->after('tanggalLahirKK');
            $table->string('jenisSertifikat', 50)->nullable()->after('statusRumah');
            $table->string('fotoKK')->nullable()->after('catatan');
            $table->string('fotoRumah')->nullable()->after('fotoKK');
            $table->string('dokumenPBB')->nullable()->after('fotoRumah');
        });
    }

    public function down(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->dropColumn([
                'tanggalLahirKK', 'jenisKelaminKK', 'jenisSertifikat',
                'fotoKK', 'fotoRumah', 'dokumenPBB'
            ]);
        });
    }
};
