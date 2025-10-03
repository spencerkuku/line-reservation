<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 報到提醒排程 - 每分鐘執行一次檢查（功能預設停用，可在設定中啟用）
Schedule::command('check-in:send-reminder')->everyMinute();
