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
        // 先確保 is_system_admin = true 的用戶都有 role = 'system_admin'
        DB::statement("UPDATE `users` SET `role` = 'system_admin' WHERE `is_system_admin` = 1");
        
        // 移除 is_system_admin 欄位
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_system_admin_index');
            $table->dropColumn('is_system_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_admin')->default(false)->after('role');
            $table->index('is_system_admin');
        });
        
        // 還原 is_system_admin 欄位值
        DB::statement("UPDATE `users` SET `is_system_admin` = 1 WHERE `role` = 'system_admin'");
    }
};
