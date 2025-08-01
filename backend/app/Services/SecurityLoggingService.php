<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SecurityLoggingService
{
    /**
     * 安全日誌記錄服務
     * 符合 OWASP A09:2021 建議
     */

    // 安全事件類型
    const EVENT_TYPES = [
        'LOGIN_SUCCESS' => 'login_success',
        'LOGIN_FAILURE' => 'login_failure',
        'LOGIN_LOCKED' => 'login_locked',
        'LOGOUT' => 'logout',
        'PASSWORD_CHANGE' => 'password_change',
        'PERMISSION_DENIED' => 'permission_denied',
        'SUSPICIOUS_ACTIVITY' => 'suspicious_activity',
        'DATA_ACCESS' => 'data_access',
        'DATA_MODIFICATION' => 'data_modification',
        'FILE_UPLOAD' => 'file_upload',
        'API_ABUSE' => 'api_abuse',
        'INJECTION_ATTEMPT' => 'injection_attempt',
        'XSS_ATTEMPT' => 'xss_attempt'
    ];

    /**
     * 記錄安全事件
     */
    public static function logSecurityEvent(
        string $eventType, 
        array $context = [], 
        string $level = 'info',
        Request $request = null
    ): void {
        $request = $request ?? request();
        
        $logData = [
            'event_type' => $eventType,
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID'),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'referer' => $request->header('Referer'),
            'context' => $context
        ];

        // 清理敏感數據
        $logData = self::sanitizeLogData($logData);

        Log::channel('security')->{$level}("Security Event: {$eventType}", $logData);

        // 如果是高風險事件，同時記錄到系統日誌
        if (self::isHighRiskEvent($eventType)) {
            Log::warning("High Risk Security Event: {$eventType}", $logData);
        }
    }

    /**
     * 記錄登入成功
     */
    public static function logLoginSuccess($user, Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['LOGIN_SUCCESS'],
            [
                'user_email' => self::hashEmail($user->email),
                'user_role' => $user->role,
                'remember_me' => $request ? $request->boolean('remember') : false
            ],
            'info',
            $request
        );
    }

    /**
     * 記錄登入失敗
     */
    public static function logLoginFailure(string $email, string $reason, Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['LOGIN_FAILURE'],
            [
                'attempted_email' => self::hashEmail($email),
                'failure_reason' => $reason
            ],
            'warning',
            $request
        );
    }

    /**
     * 記錄帳號鎖定
     */
    public static function logAccountLocked(string $email, Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['LOGIN_LOCKED'],
            [
                'locked_email' => self::hashEmail($email)
            ],
            'error',
            $request
        );
    }

    /**
     * 記錄權限拒絕
     */
    public static function logPermissionDenied(string $resource, string $action, Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['PERMISSION_DENIED'],
            [
                'resource' => $resource,
                'attempted_action' => $action,
                'user_role' => Auth::user()->role ?? 'anonymous'
            ],
            'warning',
            $request
        );
    }

    /**
     * 記錄可疑活動
     */
    public static function logSuspiciousActivity(string $activity, array $details = [], Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['SUSPICIOUS_ACTIVITY'],
            array_merge(['activity' => $activity], $details),
            'error',
            $request
        );
    }

    /**
     * 記錄數據訪問
     */
    public static function logDataAccess(string $resource, array $identifiers = [], Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['DATA_ACCESS'],
            [
                'resource' => $resource,
                'identifiers' => $identifiers
            ],
            'info',
            $request
        );
    }

    /**
     * 記錄數據修改
     */
    public static function logDataModification(
        string $resource, 
        string $action, 
        array $changes = [], 
        Request $request = null
    ): void {
        self::logSecurityEvent(
            self::EVENT_TYPES['DATA_MODIFICATION'],
            [
                'resource' => $resource,
                'action' => $action,
                'changes_count' => count($changes),
                'changed_fields' => array_keys($changes)
            ],
            'info',
            $request
        );
    }

    /**
     * 記錄注入攻擊嘗試
     */
    public static function logInjectionAttempt(string $type, array $payload = [], Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['INJECTION_ATTEMPT'],
            [
                'injection_type' => $type,
                'payload_hash' => hash('sha256', json_encode($payload))
            ],
            'error',
            $request
        );
    }

    /**
     * 記錄 XSS 攻擊嘗試
     */
    public static function logXssAttempt(string $input, string $field = '', Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['XSS_ATTEMPT'],
            [
                'field' => $field,
                'input_hash' => hash('sha256', $input),
                'input_length' => strlen($input)
            ],
            'error',
            $request
        );
    }

    /**
     * 記錄 API 濫用
     */
    public static function logApiAbuse(string $endpoint, int $requestCount, Request $request = null): void
    {
        self::logSecurityEvent(
            self::EVENT_TYPES['API_ABUSE'],
            [
                'endpoint' => $endpoint,
                'request_count' => $requestCount
            ],
            'warning',
            $request
        );
    }

    /**
     * 清理日誌數據中的敏感資訊
     */
    private static function sanitizeLogData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'authorization'];
        
        array_walk_recursive($data, function (&$value, $key) use ($sensitiveFields) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveFields)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * 雜湊 email 用於日誌記錄
     */
    private static function hashEmail(string $email): string
    {
        return substr(hash('sha256', $email), 0, 16);
    }

    /**
     * 檢查是否為高風險事件
     */
    private static function isHighRiskEvent(string $eventType): bool
    {
        $highRiskEvents = [
            self::EVENT_TYPES['LOGIN_LOCKED'],
            self::EVENT_TYPES['SUSPICIOUS_ACTIVITY'],
            self::EVENT_TYPES['INJECTION_ATTEMPT'],
            self::EVENT_TYPES['XSS_ATTEMPT'],
            self::EVENT_TYPES['API_ABUSE']
        ];

        return in_array($eventType, $highRiskEvents);
    }

    /**
     * 生成安全報告
     */
    public static function generateSecurityReport(int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        // 這裡應該查詢實際的日誌數據
        // 為了示例，返回基本結構
        return [
            'period' => "{$hours} hours",
            'start_time' => $startTime->toISOString(),
            'end_time' => now()->toISOString(),
            'summary' => [
                'total_events' => 0,
                'login_attempts' => 0,
                'failed_logins' => 0,
                'suspicious_activities' => 0,
                'blocked_ips' => []
            ]
        ];
    }
}
