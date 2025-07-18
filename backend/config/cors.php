<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge(
        // 主要前端 URL
        [env('FRONTEND_URL', 'http://localhost:5173')],
        
        // 從環境變數解析額外的允許來源
        explode(',', env('CORS_ALLOWED_ORIGINS', '')),
        
        // 開發環境的常用端口（僅在 local 環境）
        env('APP_ENV') === 'local' ? [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
        ] : []
    )),

    'allowed_origins_patterns' => [
        // 允許localhost和127.0.0.1的開發環境
        '/^https?:\/\/(localhost|127\.0\.0\.1):[0-9]+$/',
        // 允許所有 ngrok 域名（用於開發測試）
        '/^https:\/\/[a-z0-9\-]+\.ngrok-free\.app$/',
        '/^https:\/\/[a-z0-9\-]+\.ngrok\.io$/',
        // 允許自定義的域名模式（從環境變數）
        env('CORS_PATTERNS') ? env('CORS_PATTERNS') : '',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Forwarded-For',
        'X-Forwarded-Proto',
        'X-Forwarded-Port',
    ],

    'exposed_headers' => [
        'X-Pagination-Count',
        'X-Pagination-Page',
        'X-Pagination-Limit',
    ],

    'max_age' => 86400, // 24小時

    'supports_credentials' => true,

];
