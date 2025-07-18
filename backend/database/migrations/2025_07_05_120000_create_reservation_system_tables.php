<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 修改現有的 users 表，添加 LINE 相關欄位
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'line_user_id')) {
                $table->string('line_user_id')->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'user'])->default('user');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['Active', 'Inactive', 'Banned'])->default('Active');
            }
        });

        // 服務項目表
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('duration'); // 分鐘
            $table->decimal('price', 8, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 可預約時段表
        Schema::create('available_times', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->integer('max_capacity')->default(1);
            $table->integer('current_bookings')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 預約表
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('available_time_id')->nullable()->constrained()->onDelete('set null');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        // 系統設定表
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, json, boolean, integer
            $table->timestamps();
        });

        // LINE 訊息日誌表
        Schema::create('line_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('line_user_id');
            $table->string('message_type'); // text, image, location, etc.
            $table->text('message_content');
            $table->text('bot_response')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('line_message_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('available_times');
        Schema::dropIfExists('services');
        
        // 移除添加到 users 表的欄位
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'line_user_id')) {
                $table->dropColumn('line_user_id');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
