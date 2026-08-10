<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('nama');
            $table->string('tempatLahir')->nullable()->after('nik');
            $table->date('tanggalLahir')->nullable()->after('tempatLahir');
        });
    }

    public function down(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropColumn(['nik', 'tempatLahir', 'tanggalLahir']);
        });
    }
};
