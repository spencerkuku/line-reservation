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
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            
            // 操作者資訊
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable(); // 冗餘欄位，避免刪除用戶後無法查詢
            $table->string('user_email')->nullable();
            
            // 操作資訊
            $table->string('action')->index(); // created, updated, deleted, login, logout, etc.
            $table->string('module')->index(); // users, services, reservations, settings, etc.
            $table->text('description'); // 操作描述
            
            // 操作對象
            $table->string('subject_type')->nullable()->index(); // Model 類型
            $table->unsignedBigInteger('subject_id')->nullable(); // Model ID
            $table->json('subject_data')->nullable(); // 對象資料快照
            
            // 變更內容
            $table->json('old_values')->nullable(); // 變更前的值
            $table->json('new_values')->nullable(); // 變更後的值
            
            // 請求資訊
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('method', 10); // GET, POST, PUT, DELETE
            $table->text('url');
            
            // 狀態
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // 索引
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
