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
        Schema::table('customers', function (Blueprint $table) {
            // Only add indexes for columns that exist
            // status index might already exist, so check first
            if (!$this->indexExists('customers', 'customers_status_index')) {
                $table->index('status', 'customers_status_index');
            }
        });
        
        Schema::table('reservations', function (Blueprint $table) {
            if (!$this->indexExists('reservations', 'reservations_reservation_date_index')) {
                $table->index('reservation_date', 'reservations_reservation_date_index');
            }
            if (!$this->indexExists('reservations', 'reservations_status_index')) {
                $table->index('status', 'reservations_status_index');
            }
            if (!$this->indexExists('reservations', 'reservations_customer_id_reservation_date_index')) {
                $table->index(['customer_id', 'reservation_date'], 'reservations_customer_id_reservation_date_index');
            }
            if (!$this->indexExists('reservations', 'reservations_reservation_date_status_index')) {
                $table->index(['reservation_date', 'status'], 'reservations_reservation_date_status_index');
            }
        });
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return !empty($indexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if ($this->indexExists('customers', 'customers_status_index')) {
                $table->dropIndex('customers_status_index');
            }
        });
        
        Schema::table('reservations', function (Blueprint $table) {
            if ($this->indexExists('reservations', 'reservations_reservation_date_index')) {
                $table->dropIndex('reservations_reservation_date_index');
            }
            if ($this->indexExists('reservations', 'reservations_status_index')) {
                $table->dropIndex('reservations_status_index');
            }
            if ($this->indexExists('reservations', 'reservations_customer_id_reservation_date_index')) {
                $table->dropIndex('reservations_customer_id_reservation_date_index');
            }
            if ($this->indexExists('reservations', 'reservations_reservation_date_status_index')) {
                $table->dropIndex('reservations_reservation_date_status_index');
            }
        });
    }
};
