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
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->text('blocked_notes')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->unsignedBigInteger('unblocked_by')->nullable();
            $table->string('unblock_reason')->nullable();
            
            $table->foreign('blocked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('unblocked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropForeign(['unblocked_by']);
            $table->dropColumn(['blocked_at', 'blocked_reason', 'blocked_notes', 'blocked_by', 'unblocked_at', 'unblocked_by', 'unblock_reason']);
        });
    }
};
