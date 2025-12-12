<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 新增 webhook_token 欄位（UUID，唯一且永不重複）
            $table->uuid('webhook_token')->nullable()->unique()->after('line_channel_secret');
        });

        // 為現有租戶生成 webhook_token
        $tenants = DB::table('tenants')->get();
        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['webhook_token' => Str::uuid()->toString()]);
        }

        // 設定 webhook_token 為非空
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('webhook_token')->nullable(false)->change();
        });

        // 移除舊的 webhook_url 欄位
        if (Schema::hasColumn('tenants', 'webhook_url')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('webhook_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 還原 webhook_url 欄位
            $table->string('webhook_url')->nullable()->after('line_channel_secret');
        });

        // 從 webhook_token 還原 webhook_url
        $tenants = DB::table('tenants')->get();
        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['webhook_url' => '/api/webhook/' . $tenant->slug]);
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('webhook_token');
        });
    }
};
