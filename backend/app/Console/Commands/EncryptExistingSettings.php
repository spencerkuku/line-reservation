<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptExistingSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:encrypt-existing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing sensitive settings in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('開始加密現有的敏感設定...');
        
        // 需要加密的設定鍵
        $encryptedKeys = [
            'line_channel_access_token',
            'line_channel_secret'
        ];
        
        $encryptedCount = 0;
        
        foreach ($encryptedKeys as $key) {
            $setting = Setting::where('key', $key)->first();
            
            if ($setting) {
                // 檢查是否已經加密
                try {
                    Crypt::decryptString($setting->value);
                    $this->info("設定 '{$key}' 已經是加密狀態，跳過。");
                    continue;
                } catch (\Exception $e) {
                    // 解密失敗，表示還未加密，需要加密
                }
                
                // 加密原始值
                $originalValue = $setting->value;
                $encryptedValue = Crypt::encryptString($originalValue);
                
                // 直接更新資料庫，避免觸發 Setting::set 方法的重複加密
                DB::table('settings')
                    ->where('key', $key)
                    ->update(['value' => $encryptedValue]);
                
                $this->info("已加密設定: {$key}");
                $encryptedCount++;
            } else {
                $this->warn("找不到設定: {$key}");
            }
        }
        
        $this->info("加密完成！共加密了 {$encryptedCount} 個設定。");
        
        return Command::SUCCESS;
    }
}
