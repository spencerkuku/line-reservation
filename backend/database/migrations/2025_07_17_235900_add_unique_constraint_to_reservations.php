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
            // 添加複合索引來改善查詢性能和防止重複預約
            $table->index(['available_time_id', 'reservation_date', 'reservation_time'], 'idx_reservations_time_lookup');
            
            // 添加狀態索引
            $table->index('status', 'idx_reservations_status');
            
            // 添加客戶和日期的複合索引以提高查詢效率
            $table->index(['customer_id', 'reservation_date'], 'idx_reservations_customer_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_time_lookup');
            $table->dropIndex('idx_reservations_status');
            $table->dropIndex('idx_reservations_customer_date');
        });
    }
};
