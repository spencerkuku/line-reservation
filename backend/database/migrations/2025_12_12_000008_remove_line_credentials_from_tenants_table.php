<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 移除租戶表的 LINE 憑證欄位，因為這些資料已存儲在 settings 表中
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['line_channel_access_token', 'line_channel_secret']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('line_channel_access_token')->nullable()->after('logo');
            $table->text('line_channel_secret')->nullable()->after('line_channel_access_token');
        });
    }
};
