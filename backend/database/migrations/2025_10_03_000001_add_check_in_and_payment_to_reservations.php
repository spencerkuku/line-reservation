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
        Schema::table('reservations', function (Blueprint $table) {
            // 報到相關欄位
            $table->string('check_in_code', 20)->unique()->nullable()->after('status')->comment('報到碼');
            $table->enum('check_in_status', ['pending', 'checked_in', 'no_show', 'late'])->default('pending')->after('check_in_code')->comment('報到狀態');
            $table->timestamp('check_in_time')->nullable()->after('check_in_status')->comment('報到時間');
            $table->unsignedBigInteger('check_in_by')->nullable()->after('check_in_time')->comment('報到操作人員');
            $table->boolean('no_show')->default(false)->after('check_in_by')->comment('是否爽約');
            
            // 付款相關欄位
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid')->after('no_show')->comment('付款狀態');
            $table->enum('payment_method', ['cash', 'credit_card', 'debit_card', 'transfer', 'line_pay', 'other'])->nullable()->after('payment_status')->comment('付款方式');
            $table->decimal('payment_amount', 10, 2)->default(0)->after('payment_method')->comment('實際付款金額');
            $table->timestamp('payment_time')->nullable()->after('payment_amount')->comment('付款時間');
            $table->text('payment_note')->nullable()->after('payment_time')->comment('付款備註');
            
            // 外鍵
            $table->foreign('check_in_by')->references('id')->on('users')->onDelete('set null');
            
            // 索引
            $table->index('check_in_code');
            $table->index('check_in_status');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['check_in_by']);
            $table->dropIndex(['check_in_code']);
            $table->dropIndex(['check_in_status']);
            $table->dropIndex(['payment_status']);
            
            $table->dropColumn([
                'check_in_code',
                'check_in_status',
                'check_in_time',
                'check_in_by',
                'no_show',
                'payment_status',
                'payment_method',
                'payment_amount',
                'payment_time',
                'payment_note'
            ]);
        });
    }
};
