<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 正規化資料庫結構：
     * 1. 移除 reservations 表中的重複客戶資訊欄位
     * 2. 移除 available_times 表中的 current_bookings 計算欄位  
     * 3. 移除 customers 表中的統計欄位
     */
    public function up(): void
    {
        // 1. 移除 reservations 表中的重複客戶資訊欄位
        // 這些資訊應該從 customers 表取得，避免資料不一致
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_notes']);
        });

        // 2. 移除 available_times 表中的計算欄位
        // current_bookings 應該通過查詢 reservations 表計算得出
        Schema::table('available_times', function (Blueprint $table) {
            $table->dropColumn('current_bookings');
        });

        // 3. 移除 customers 表中的統計欄位
        // 這些統計資料應該通過查詢計算，避免資料不同步
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['total_reservations', 'total_spent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 還原 reservations 表的客戶資訊欄位
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('customer_name')->after('customer_id')->comment('預約時填寫的姓名');
            $table->string('customer_phone')->after('customer_name')->comment('預約時填寫的電話');
            $table->text('customer_notes')->nullable()->after('customer_phone')->comment('預約時填寫的備註');
        });

        // 還原 available_times 表的計算欄位
        Schema::table('available_times', function (Blueprint $table) {
            $table->integer('current_bookings')->default(0)->after('max_capacity');
        });

        // 還原 customers 表的統計欄位
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('total_reservations')->default(0)->after('referral_source');
            $table->decimal('total_spent', 10, 2)->default(0)->after('total_reservations');
        });
    }
};
