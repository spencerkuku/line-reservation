<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 在添加外鍵約束前，先確保數據完整性
        // 刪除任何指向不存在customer的預約記錄
        DB::statement('DELETE FROM reservations WHERE customer_id IS NOT NULL AND customer_id NOT IN (SELECT id FROM customers)');
        
        Schema::table('reservations', function (Blueprint $table) {
            // 添加外鍵約束到已存在的 customer_id 欄位
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
    }
};
