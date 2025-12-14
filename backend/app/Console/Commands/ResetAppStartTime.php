<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ResetAppStartTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-start-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '重置應用啟動時間（用於測試運行時間功能）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::forget('app_start_time');
        Cache::forever('app_start_time', now());
        
        $this->info('應用啟動時間已重置為: ' . now());
        $this->info('系統運行時間將從現在開始計算。');
        
        return Command::SUCCESS;
    }
}
