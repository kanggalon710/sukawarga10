<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->string('void_reason')->nullable()->after('voided');
            $table->string('void_by')->nullable()->after('void_reason');
            $table->timestamp('void_at')->nullable()->after('void_by');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn(['void_reason', 'void_by', 'void_at']);
        });
    }
};
