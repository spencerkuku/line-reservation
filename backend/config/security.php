<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | 安全相關配置設定
    | 符合 OWASP Top 10 2021 安全標準
    |
    */

    /*
    |--------------------------------------------------------------------------
    | 內容安全政策 (CSP)
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI', null),
        
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'object-src' => ["'none'"],
            'media-src' => ["'self'"],
            'frame-src' => ["'none'"],
            'child-src' => ["'none'"],
            'worker-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'manifest-src' => ["'self'"],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | 速率限制設定
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'login_attempts' => env('RATE_LIMIT_LOGIN', 5),
        'api_requests' => env('RATE_LIMIT_API', 100),
        'general_requests' => env('RATE_LIMIT_GENERAL', 60),
        'webhook_requests' => env('RATE_LIMIT_WEBHOOK', 1000),
        'window_minutes' => env('RATE_LIMIT_WINDOW', 1),
        'lockout_duration' => env('RATE_LIMIT_LOCKOUT', 15), // 分鐘
    ],

    /*
    |--------------------------------------------------------------------------
    | 密碼政策
    |--------------------------------------------------------------------------
    */
    'password_policy' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
        'history_limit' => env('PASSWORD_HISTORY_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | 會話安全
    |--------------------------------------------------------------------------
    */
    'session' => [
        'timeout_minutes' => env('SESSION_TIMEOUT', 480), // 8 小時
        'idle_timeout_minutes' => env('SESSION_IDLE_TIMEOUT', 30),
        'regenerate_on_login' => true,
        'invalidate_on_logout' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 檔案上傳安全
    |--------------------------------------------------------------------------
    */
    'file_upload' => [
        'max_size' => env('UPLOAD_MAX_SIZE', 5242880), // 5MB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png', 
            'image/gif',
            'image/webp',
            'application/pdf'
        ],
        'scan_for_malware' => env('UPLOAD_SCAN_MALWARE', false),
        'quarantine_suspicious' => env('UPLOAD_QUARANTINE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API 安全
    |--------------------------------------------------------------------------
    */
    'api' => [
        'require_https' => env('API_REQUIRE_HTTPS', true),
        'cors_origins' => array_filter(explode(',', env('API_CORS_ORIGINS', ''))),
        'version_header' => env('API_VERSION_HEADER', 'X-API-Version'),
        'request_id_header' => env('API_REQUEST_ID_HEADER', 'X-Request-ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 日誌記錄
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'security_events' => env('LOG_SECURITY_EVENTS', true),
        'api_requests' => env('LOG_API_REQUESTS', true),
        'failed_attempts' => env('LOG_FAILED_ATTEMPTS', true),
        'data_access' => env('LOG_DATA_ACCESS', false),
        'retention_days' => env('LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | 加密設定
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'algorithm' => env('ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
        'key_rotation_days' => env('ENCRYPTION_KEY_ROTATION_DAYS', 365),
        'hash_algorithm' => env('HASH_ALGORITHM', 'sha256'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 外部請求安全 (SSRF 防護)
    |--------------------------------------------------------------------------
    */
    'http_client' => [
        'timeout' => env('HTTP_CLIENT_TIMEOUT', 30),
        'max_redirects' => env('HTTP_CLIENT_MAX_REDIRECTS', 3),
        'verify_ssl' => env('HTTP_CLIENT_VERIFY_SSL', true),
        'blocked_ips' => [
            '127.0.0.1',
            '::1',
            '0.0.0.0',
            '169.254.169.254', // AWS metadata
            'metadata.google.internal', // GCP metadata
        ],
        'blocked_ports' => [22, 23, 25, 53, 110, 143, 993, 995],
        'allowed_schemes' => ['http', 'https'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全標頭
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'hsts_max_age' => env('HSTS_MAX_AGE', 31536000), // 1 年
        'hsts_include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
        'hsts_preload' => env('HSTS_PRELOAD', true),
        'remove_server_header' => env('REMOVE_SERVER_HEADER', true),
        'x_powered_by' => env('X_POWERED_BY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | 監控和警報
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'failed_login_threshold' => env('MONITOR_FAILED_LOGIN_THRESHOLD', 10),
        'suspicious_activity_threshold' => env('MONITOR_SUSPICIOUS_THRESHOLD', 5),
        'alert_email' => env('SECURITY_ALERT_EMAIL'),
        'alert_webhook' => env('SECURITY_ALERT_WEBHOOK'),
    ],

];
