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
        Schema::table('users', function (Blueprint $table) {
            // 新增 tenant_id，可為 null（System Admin 不屬於任何租戶）
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            
            // 新增系統管理員角色支持
            // 修改 role 欄位以支持新角色
            $table->boolean('is_system_admin')->default(false)->after('role');
            
            // 首次登入強制修改密碼
            $table->boolean('must_change_password')->default(false)->after('password');
            
            // 索引
            $table->index('tenant_id');
            $table->index('is_system_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_system_admin', 'must_change_password']);
        });
    }
};
