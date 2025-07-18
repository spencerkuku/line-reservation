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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('line_user_id')->unique()->nullable(); // LINE用戶ID
            $table->string('name'); // 客戶姓名
            $table->string('phone')->nullable(); // 電話號碼
            $table->string('email')->nullable(); // 電子郵件
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); // 性別
            $table->date('birthday')->nullable(); // 生日
            $table->text('address')->nullable(); // 地址
            $table->text('notes')->nullable(); // 備註
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active'); // 狀態
            $table->json('preferences')->nullable(); // 偏好設定 (JSON格式)
            $table->timestamp('last_interaction_at')->nullable(); // 最後互動時間
            $table->string('referral_source')->nullable(); // 來源管道
            $table->integer('total_reservations')->default(0); // 總預約次數
            $table->decimal('total_spent', 10, 2)->default(0); // 總消費金額
            $table->timestamps();
            
            // 索引
            $table->index(['status', 'created_at']);
            $table->index('phone');
            $table->index('email');
            $table->index('last_interaction_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
