<?php

return [
    'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN', ''),
    'channel_secret' => env('LINE_CHANNEL_SECRET', ''),
    'webhook_url' => rtrim(env('APP_URL', ''), '/') . '/api/webhook',
    
    // 預設會從資料庫 settings 表讀取
    'use_database_config' => true,
];
