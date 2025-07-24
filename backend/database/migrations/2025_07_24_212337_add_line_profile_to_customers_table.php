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
        Schema::table('customers', function (Blueprint $table) {
            // 添加 LINE SDK 相關欄位
            $table->string('line_display_name')->nullable()->after('name')->comment('LINE 顯示名稱');
            $table->string('line_picture_url')->nullable()->after('line_display_name')->comment('LINE 頭像 URL');
            $table->string('line_status_message')->nullable()->after('line_picture_url')->comment('LINE 狀態訊息');
            
            // 移除不再需要的欄位註解，這些現在改存在 reservations 表
            $table->string('phone')->nullable()->change()->comment('保留作為參考，實際預約電話存在 reservations 表');
            $table->text('notes')->nullable()->change()->comment('客戶總體備註，預約特定備註存在 reservations 表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['line_display_name', 'line_picture_url', 'line_status_message']);
        });
    }
};
