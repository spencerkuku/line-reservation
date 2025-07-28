<?php

namespace App\Console\Commands;

use App\Services\LoggingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestLoggingCommand extends Command
{
    protected $signature = 'test:logging';
    protected $description = 'Test the logging service with sample data';

    public function handle()
    {
        $this->info('Testing LoggingService...');
        
        // 測試 LINE Bot 事件記錄
        $this->info('Testing LINE Bot event logging...');
        LoggingService::logLineBotEvent('test_event', 'test_user_123', [
            'action' => 'command_test',
            'timestamp' => now()->toISOString()
        ]);
        
        // 測試預約事件記錄
        $this->info('Testing reservation event logging...');
        LoggingService::logReservationEvent('test_reservation', [
            'reservation_id' => 999,
            'service_name' => 'Test Service',
            'customer_name' => 'Test Customer'
        ]);
        
        // 測試客戶事件記錄
        $this->info('Testing customer event logging...');
        LoggingService::logCustomerEvent('test_customer_action', [
            'customer_id' => 888,
            'action' => 'profile_updated'
        ]);
        
        // 測試性能記錄
        $this->info('Testing performance logging...');
        $startTime = microtime(true);
        usleep(100000); // 模擬 100ms 的操作
        LoggingService::logPerformance('Test Operation', $startTime, [
            'test_data' => 'performance_test'
        ]);
        
        // 測試用戶操作記錄
        $this->info('Testing user action logging...');
        LoggingService::logUserAction('test_admin_action', [
            'action' => 'system_test',
            'description' => 'Testing logging system'
        ]);
        
        $this->info('Logging tests completed! Check the following log files:');
        $this->line('- storage/logs/linebot.log');
        $this->line('- storage/logs/reservations.log');
        $this->line('- storage/logs/customers.log');
        $this->line('- storage/logs/api.log');
        $this->line('- storage/logs/laravel.log');
        
        return Command::SUCCESS;
    }
}
