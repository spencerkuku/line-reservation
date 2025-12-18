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
        // admin_activity_logs 表 - 關鍵索引
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (!$this->indexExists('admin_activity_logs', 'idx_tenant_created')) {
                $table->index(['tenant_id', 'created_at'], 'idx_tenant_created');
            }
            if (!$this->indexExists('admin_activity_logs', 'idx_module_action')) {
                $table->index(['module', 'action'], 'idx_module_action');
            }
            if (!$this->indexExists('admin_activity_logs', 'idx_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_user_created');
            }
            if (!$this->indexExists('admin_activity_logs', 'idx_status')) {
                $table->index('status', 'idx_status');
            }
        });

        // line_message_logs 表 - 關鍵索引
        Schema::table('line_message_logs', function (Blueprint $table) {
            if (!$this->indexExists('line_message_logs', 'idx_tenant_created')) {
                $table->index(['tenant_id', 'created_at'], 'idx_tenant_created');
            }
            if (!$this->indexExists('line_message_logs', 'idx_user_direction')) {
                $table->index(['line_user_id', 'direction'], 'idx_user_direction');
            }
            if (!$this->indexExists('line_message_logs', 'idx_tenant_direction_created')) {
                $table->index(['tenant_id', 'direction', 'created_at'], 'idx_tenant_direction_created');
            }
        });

        // reservations 表 - 補充索引
        Schema::table('reservations', function (Blueprint $table) {
            if (!$this->indexExists('reservations', 'idx_tenant_date')) {
                $table->index(['tenant_id', 'reservation_date'], 'idx_tenant_date');
            }
            if (!$this->indexExists('reservations', 'idx_check_in_status')) {
                $table->index('check_in_status', 'idx_check_in_status');
            }
            if (!$this->indexExists('reservations', 'idx_payment_status')) {
                $table->index('payment_status', 'idx_payment_status');
            }
            if (!$this->indexExists('reservations', 'idx_customer_status')) {
                $table->index(['customer_id', 'status'], 'idx_customer_status');
            }
        });

        // customers 表 - 補充索引
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->indexExists('customers', 'idx_tenant_status')) {
                $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            }
            if (!$this->indexExists('customers', 'idx_tenant_line')) {
                $table->index(['tenant_id', 'line_user_id'], 'idx_tenant_line');
            }
            if (!$this->indexExists('customers', 'idx_phone')) {
                $table->index('phone', 'idx_phone');
            }
            if (!$this->indexExists('customers', 'idx_email')) {
                $table->index('email', 'idx_email');
            }
        });

        // available_times 表 - 補充索引
        Schema::table('available_times', function (Blueprint $table) {
            if (!$this->indexExists('available_times', 'idx_tenant_active')) {
                $table->index(['tenant_id', 'is_active'], 'idx_tenant_active');
            }
            if (!$this->indexExists('available_times', 'idx_start_time')) {
                $table->index('start_time', 'idx_start_time');
            }
        });

        // services 表 - 補充索引
        Schema::table('services', function (Blueprint $table) {
            if (!$this->indexExists('services', 'idx_tenant_active')) {
                $table->index(['tenant_id', 'is_active'], 'idx_tenant_active');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if ($this->indexExists('services', 'idx_tenant_active')) {
                $table->dropIndex('idx_tenant_active');
            }
        });

        Schema::table('available_times', function (Blueprint $table) {
            if ($this->indexExists('available_times', 'idx_tenant_active')) {
                $table->dropIndex('idx_tenant_active');
            }
            if ($this->indexExists('available_times', 'idx_start_time')) {
                $table->dropIndex('idx_start_time');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if ($this->indexExists('customers', 'idx_tenant_status')) {
                $table->dropIndex('idx_tenant_status');
            }
            if ($this->indexExists('customers', 'idx_tenant_line')) {
                $table->dropIndex('idx_tenant_line');
            }
            if ($this->indexExists('customers', 'idx_phone')) {
                $table->dropIndex('idx_phone');
            }
            if ($this->indexExists('customers', 'idx_email')) {
                $table->dropIndex('idx_email');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if ($this->indexExists('reservations', 'idx_tenant_date')) {
                $table->dropIndex('idx_tenant_date');
            }
            if ($this->indexExists('reservations', 'idx_check_in_status')) {
                $table->dropIndex('idx_check_in_status');
            }
            if ($this->indexExists('reservations', 'idx_payment_status')) {
                $table->dropIndex('idx_payment_status');
            }
            if ($this->indexExists('reservations', 'idx_customer_status')) {
                $table->dropIndex('idx_customer_status');
            }
        });

        Schema::table('line_message_logs', function (Blueprint $table) {
            if ($this->indexExists('line_message_logs', 'idx_tenant_created')) {
                $table->dropIndex('idx_tenant_created');
            }
            if ($this->indexExists('line_message_logs', 'idx_user_direction')) {
                $table->dropIndex('idx_user_direction');
            }
            if ($this->indexExists('line_message_logs', 'idx_tenant_direction_created')) {
                $table->dropIndex('idx_tenant_direction_created');
            }
        });

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if ($this->indexExists('admin_activity_logs', 'idx_tenant_created')) {
                $table->dropIndex('idx_tenant_created');
            }
            if ($this->indexExists('admin_activity_logs', 'idx_module_action')) {
                $table->dropIndex('idx_module_action');
            }
            if ($this->indexExists('admin_activity_logs', 'idx_user_created')) {
                $table->dropIndex('idx_user_created');
            }
            if ($this->indexExists('admin_activity_logs', 'idx_status')) {
                $table->dropIndex('idx_status');
            }
        });
    }
};
