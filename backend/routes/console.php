<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 報到提醒排程 - 每分鐘執行一次檢查（功能預設停用，可在設定中啟用）
Schedule::command('check-in:send-reminder')->everyMinute();

// 日誌管理排程
// 每天凌晨 2:00 壓縮 7 天前的日誌檔案，並刪除 30 天前的壓縮檔
Schedule::command('logs:compress --days=7 --delete-compressed=30')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
