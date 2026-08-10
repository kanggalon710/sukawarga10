<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surats', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('status');
            $table->string('rt_target')->nullable()->after('user_id');
            $table->string('approval_step')->default('selesai')->after('rt_target');
            // diajukan → ttd_rt → ttd_rw → cap_sekretaris → selesai | ditolak

            $table->string('rt_signed_by')->nullable();
            $table->timestamp('rt_signed_at')->nullable();
            $table->string('rw_signed_by')->nullable();
            $table->timestamp('rw_signed_at')->nullable();
            $table->string('sek_signed_by')->nullable();
            $table->timestamp('sek_signed_at')->nullable();

            $table->string('rejected_by')->nullable();
            $table->text('rejected_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('surats', function (Blueprint $table) {
            $table->dropColumn([
                'user_id', 'rt_target', 'approval_step',
                'rt_signed_by', 'rt_signed_at',
                'rw_signed_by', 'rw_signed_at',
                'sek_signed_by', 'sek_signed_at',
                'rejected_by', 'rejected_reason',
            ]);
        });
    }
};
