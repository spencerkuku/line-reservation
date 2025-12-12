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
        Schema::table('settings', function (Blueprint $table) {
            // 移除舊的唯一約束（只有 key）
            $table->dropUnique('settings_key_unique');
            
            // 新增複合唯一約束（tenant_id + key）
            $table->unique(['tenant_id', 'key'], 'settings_tenant_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // 移除複合唯一約束
            $table->dropUnique('settings_tenant_key_unique');
            
            // 還原舊的唯一約束
            $table->unique('key', 'settings_key_unique');
        });
    }
};
