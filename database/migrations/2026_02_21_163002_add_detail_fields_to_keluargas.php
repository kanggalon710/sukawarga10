<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            // Step 2: Rumah & Sanitasi
            $table->string('tipeBangunan')->nullable()->after('statusRumah');
            $table->string('luasLantai')->nullable()->after('tipeBangunan');
            $table->integer('jmlKamarTidur')->nullable()->after('luasLantai');
            $table->string('bahanLantai')->nullable()->after('jmlKamarTidur');
            $table->string('bahanDinding')->nullable()->after('bahanLantai');
            $table->string('bahanAtap')->nullable()->after('bahanDinding');
            $table->string('sumberAirMinum')->nullable()->after('bahanAtap');
            $table->string('sumberAirMandi')->nullable()->after('sumberAirMinum');
            $table->string('sumberMasak')->nullable()->after('sumberAirMandi');
            $table->string('kepemilikanJamban')->nullable()->after('sumberMasak');
            $table->string('pembuanganTinja')->nullable()->after('kepemilikanJamban');
            $table->string('caraBuangSampah')->nullable()->after('pembuanganTinja');
            $table->string('sumberListrik')->nullable()->after('caraBuangSampah');

            // Step 3: Ekonomi & Aset
            $table->string('sumberPendapatan')->nullable()->after('penghasilan');
            $table->boolean('punyaTabungan')->nullable()->after('sumberPendapatan');
            $table->boolean('punyaHutangUsaha')->nullable()->after('punyaTabungan');
            $table->string('aksesKredit')->nullable()->after('punyaHutangUsaha');
            $table->json('aset')->nullable()->after('aksesKredit'); // {motor: bool, mobil: bool, tanah: bool, etc}

            // Step 4: Bansos & Program
            $table->json('bansos')->nullable()->after('aset'); // {bpjs: bool, pkh: bool, bpnt: bool, etc}
            $table->json('kelompokRentan')->nullable()->after('bansos'); // {lansia: bool, balita: bool, hamil: bool, etc}
            $table->string('statusKK')->nullable()->after('status'); // aktif / pindah / meninggal
        });
    }

    public function down(): void
    {
        Schema::table('keluargas', function (Blueprint $table) {
            $table->dropColumn([
                'tipeBangunan', 'luasLantai', 'jmlKamarTidur', 'bahanLantai', 'bahanDinding', 'bahanAtap',
                'sumberAirMinum', 'sumberAirMandi', 'sumberMasak', 'kepemilikanJamban', 'pembuanganTinja',
                'caraBuangSampah', 'sumberListrik', 'sumberPendapatan', 'punyaTabungan', 'punyaHutangUsaha',
                'aksesKredit', 'aset', 'bansos', 'kelompokRentan', 'statusKK',
            ]);
        });
    }
};
