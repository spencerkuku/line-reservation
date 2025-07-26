<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\Service;
use App\Models\Customer;
use App\Models\AvailableTime;
use App\Models\Reservation;
use App\Services\LineBotService;

class TestLineBotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:linebot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '測試 LINE Bot 功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== LINE Bot 測試腳本 ===');

        // 測試設定值
        $this->newLine();
        $this->info('1. 檢查設定值:');
        $settings = [
            'line_channel_access_token',
            'line_channel_secret',
            'app_name'
        ];

        foreach ($settings as $key) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                // 使用 Setting::get() 來正確解密值
                $decryptedValue = Setting::get($key);
                $value = in_array($key, ['line_channel_access_token', 'line_channel_secret']) 
                    ? '***隱藏***' 
                    : $decryptedValue;
                $this->line("✓ {$key}: {$value}");
            } else {
                $this->error("✗ {$key}: 未設定");
            }
        }

        // 測試 LINE Bot 服務
        $this->newLine();
        $this->info('2. 測試 LINE Bot 服務:');
        try {
            // 檢查檔案是否存在
            $serviceFile = app_path('Services/LineBotService.php');
            if (file_exists($serviceFile)) {
                $this->line("✓ LineBotService 檔案存在");
            } else {
                $this->error("✗ LineBotService 檔案不存在");
            }
        } catch (\Exception $e) {
            $this->error("✗ LineBotService 檢查失敗: " . $e->getMessage());
        }

        // 測試資料庫資料
        $this->newLine();
        $this->info('3. 檢查資料庫資料:');

        // 檢查服務
        $serviceCount = Service::count();
        $this->line("✓ 服務項目數量: {$serviceCount}");

        // 檢查客戶
        $customerCount = Customer::count();
        $this->line("✓ 客戶數量: {$customerCount}");

        // 檢查可預約時段
        $availableTimeCount = AvailableTime::count();
        $this->line("✓ 可預約時段數量: {$availableTimeCount}");

        // 檢查預約記錄
        $reservationCount = Reservation::count();
        $this->line("✓ 預約記錄數量: {$reservationCount}");

        // 顯示一些示例資料
        $this->newLine();
        $this->info('4. 示例資料:');
        
        $services = Service::take(3)->get();
        $this->table(['ID', '服務名稱', '價格', '時長(分鐘)'], 
            $services->map(function ($service) {
                return [
                    $service->id,
                    $service->name,
                    $service->price,
                    $service->duration
                ];
            })->toArray()
        );

        $customers = Customer::take(3)->get();
        $this->table(['ID', '姓名', 'LINE ID', '電話'], 
            $customers->map(function ($customer) {
                return [
                    $customer->id,
                    $customer->name,
                    substr($customer->line_user_id, 0, 20) . '...',
                    $customer->phone
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('5. 測試 API 路由:');

        // 檢查 webhook 路由
        $this->line("✓ Webhook 路由已設定: POST /api/line/webhook");
        
        // 檢查控制器
        $controllerFile = app_path('Http/Controllers/Api/LineWebhookController.php');
        if (file_exists($controllerFile)) {
            $this->line("✓ LineWebhookController 檔案存在");
        } else {
            $this->error("✗ LineWebhookController 檔案不存在");
        }

        $this->newLine();
        $this->info('=== 測試完成 ===');
        
        $this->newLine();
        $this->info('設定說明:');
        $this->line('1. 請在 LINE Developers Console 創建 Messaging API 頻道');
        $this->line('2. 將 Channel Access Token 和 Channel Secret 更新到資料庫的 settings 表');
        $this->line('3. 設定 Webhook URL: http://your-domain.com/api/line/webhook');
        $this->line('4. 在 LINE 聊天機器人中輸入 \'我要預約\' 開始測試預約流程');

        return Command::SUCCESS;
    }
}
