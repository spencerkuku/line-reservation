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
        // 修改 role 欄位以支持 system_admin 角色
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'user', 'system_admin') NOT NULL DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 首先將 system_admin 角色改為 admin
        DB::statement("UPDATE `users` SET `role` = 'admin' WHERE `role` = 'system_admin'");
        
        // 然後還原 enum
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user'");
    }
};
