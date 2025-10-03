<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 移除報到碼相關欄位和索引
            $table->dropIndex(['check_in_code']);
            $table->dropColumn('check_in_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 恢復報到碼欄位
            $table->string('check_in_code', 20)->unique()->nullable()->after('status')->comment('報到碼');
            $table->index('check_in_code');
        });
    }
};
