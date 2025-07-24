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
            $table->string('customer_name')->after('customer_id')->comment('預約時填寫的姓名');
            $table->string('customer_phone')->after('customer_name')->comment('預約時填寫的電話');
            $table->text('customer_notes')->nullable()->after('customer_phone')->comment('預約時填寫的備註');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_notes']);
        });
    }
};
