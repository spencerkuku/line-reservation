<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class LoggingService
{
    /**
     * 生成請求 ID
     */
    public static function generateRequestId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * 記錄 API 請求開始
     */
    public static function logApiRequestStart(Request $request, string $requestId, ?string $action = null): void
    {
        Log::channel('api')->info('API Request Started', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'action' => $action,
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => self::sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'input_data' => self::sanitizeInput($request->except(['_token', 'password', 'password_confirmation'])),
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true)
        ]);
    }

    /**
     * 記錄 API 請求成功
     */
    public static function logApiRequestSuccess(string $requestId, $responseData = null, int $statusCode = 200): void
    {
        Log::channel('api')->info('API Request Success', [
            'request_id' => $requestId,
            'status_code' => $statusCode,
            'response_size' => $responseData ? strlen(json_encode($responseData)) : 0,
            'execution_time' => microtime(true) - LARAVEL_START,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 記錄 API 請求失敗
     */
    public static function logApiRequestError(string $requestId, \Throwable $exception, $inputData = null): void
    {
        Log::channel('api')->error('API Request Failed', [
            'request_id' => $requestId,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'input_data' => self::sanitizeInput($inputData),
            'execution_time' => microtime(true) - LARAVEL_START,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 記錄 LINE Bot 事件
     */
    public static function logLineBotEvent(string $eventType, string $userId, array $eventData = [], ?string $requestId = null): void
    {
        Log::channel('linebot')->info('LINE Bot Event', [
            'request_id' => $requestId ?: self::generateRequestId(),
            'event_type' => $eventType,
            'user_id' => $userId,
            'event_data' => $eventData,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true)
        ]);
    }

    /**
     * 記錄 LINE Bot 錯誤
     */
    public static function logLineBotError(string $action, string $userId, \Throwable $exception, array $context = [], ?string $requestId = null): void
    {
        Log::channel('linebot')->error('LINE Bot Error', [
            'request_id' => $requestId ?: self::generateRequestId(),
            'action' => $action,
            'user_id' => $userId,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true)
        ]);
    }

    /**
     * 記錄預約相關事件
     */
    public static function logReservationEvent(string $action, array $data = [], ?string $requestId = null): void
    {
        Log::channel('reservations')->info('Reservation Event', [
            'request_id' => $requestId ?: self::generateRequestId(),
            'action' => $action,
            'data' => $data,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 記錄客戶相關事件
     */
    public static function logCustomerEvent(string $action, array $data = [], ?string $requestId = null): void
    {
        Log::channel('customers')->info('Customer Event', [
            'request_id' => $requestId ?: self::generateRequestId(),
            'action' => $action,
            'data' => $data,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 記錄效能相關資訊
     */
    public static function logPerformance(string $operation, float $startTime, array $context = []): void
    {
        $executionTime = microtime(true) - $startTime;
        
        Log::info('Performance Log', [
            'operation' => $operation,
            'execution_time' => $executionTime,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'context' => $context,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 記錄使用者行為
     */
    public static function logUserAction(string $action, array $data = [], ?string $requestId = null): void
    {
        Log::info('User Action', [
            'request_id' => $requestId ?: self::generateRequestId(),
            'action' => $action,
            'user_id' => Auth::id(),
            'data' => $data,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 清理敏感的 Header 資訊
     */
    private static function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['***REDACTED***'];
            }
        }
        
        return $headers;
    }

    /**
     * 清理敏感的輸入資料
     */
    private static function sanitizeInput($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($input[$field])) {
                $input[$field] = '***REDACTED***';
            }
        }
        
        return $input;
    }
}
