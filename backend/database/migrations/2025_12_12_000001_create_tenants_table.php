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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 租戶名稱（公司名）
            $table->string('slug')->unique(); // 用於 URL 的唯一識別碼
            $table->string('email')->unique(); // 租戶聯絡信箱
            $table->string('phone')->nullable(); // 聯絡電話
            $table->text('address')->nullable(); // 公司地址
            $table->string('logo')->nullable(); // 公司 Logo
            
            // LINE Bot 設定
            $table->text('line_channel_access_token')->nullable(); // 加密儲存
            $table->text('line_channel_secret')->nullable(); // 加密儲存
            $table->string('webhook_url')->nullable(); // 自動生成的 webhook URL
            
            // 訂閱與狀態管理
            $table->enum('status', ['active', 'inactive', 'suspended', 'trial'])->default('trial');
            $table->date('trial_ends_at')->nullable(); // 試用期結束日
            $table->date('subscription_ends_at')->nullable(); // 訂閱到期日
            $table->string('plan')->default('basic'); // 訂閱方案
            
            // 租戶設定
            $table->json('settings')->nullable(); // 其他設定（如預約確認模式等）
            
            $table->timestamps();
            $table->softDeletes();
            
            // 索引
            $table->index('status');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
