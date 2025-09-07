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
            // 添加預約時客戶資料的快照欄位
            $table->string('reservation_name')->nullable()->after('customer_id')->comment('預約時填寫的姓名（快照）');
            $table->string('reservation_phone')->nullable()->after('reservation_name')->comment('預約時填寫的電話（快照）');
            $table->text('reservation_notes')->nullable()->after('reservation_phone')->comment('預約時填寫的備註（快照）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 移除快照欄位
            $table->dropColumn(['reservation_name', 'reservation_phone', 'reservation_notes']);
        });
    }
};
