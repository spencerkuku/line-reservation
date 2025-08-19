<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 修復預約時間一致性問題
     * - 確保 reservation_date 是 DATE 類型
     * - 確保 reservation_time 是 TIME 類型  
     * - 添加註釋說明正確的時間處理方式
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 確保資料類型正確
            $table->date('reservation_date')->comment('預約日期 (格式: Y-m-d)')->change();
            $table->time('reservation_time')->comment('預約時間 (格式: H:i:s)')->change();
            
            // 檢查索引是否已存在，避免重複創建
            if (!$this->indexExists('reservations', 'idx_reservation_datetime')) {
                $table->index(['reservation_date', 'reservation_time'], 'idx_reservation_datetime');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if ($this->indexExists('reservations', 'idx_reservation_datetime')) {
                $table->dropIndex('idx_reservation_datetime');
            }
            
            // 移除註釋
            $table->date('reservation_date')->comment(null)->change();
            $table->time('reservation_time')->comment(null)->change();
        });
    }

    /**
     * 檢查索引是否存在
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->listTableDetails($table);
        
        return $doctrineTable->hasIndex($indexName);
    }
};
