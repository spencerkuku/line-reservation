<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // 記錄請求
        Log::channel('api')->info('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'request_body' => $this->sanitizeData($request->all()),
        ]);

        $response = $next($request);

        // 計算執行時間
        $executionTime = microtime(true) - $startTime;
        $executionTimeMs = round($executionTime * 1000, 2);

        // 記錄回應
        Log::channel('api')->info('API Response', [
            'status_code' => $response->getStatusCode(),
            'execution_time' => $executionTimeMs . 'ms',
            'response_size' => strlen($response->getContent()) . ' bytes',
        ]);

        // 更新 API 性能指標
        $this->updateApiMetrics($executionTimeMs);

        return $response;
    }

    /**
     * 更新 API 性能指標到 Cache
     */
    private function updateApiMetrics(float $executionTimeMs): void
    {
        try {
            // 獲取當前小時的鍵
            $currentHourKey = now()->format('YmdH');
            $responsesKey = "api_responses_{$currentHourKey}";
            $requestsKey = "api_requests_{$currentHourKey}";

            // 記錄響應時間（使用陣列存儲最近的響應時間）
            $responses = Cache::get($responsesKey, []);
            $responses[] = $executionTimeMs;
            
            // 只保留最近 1000 個請求的數據，避免佔用過多記憶體
            if (count($responses) > 1000) {
                $responses = array_slice($responses, -1000);
            }
            
            Cache::put($responsesKey, $responses, now()->addHours(2));

            // 計算平均響應時間
            $avgResponseTime = count($responses) > 0 
                ? round(array_sum($responses) / count($responses), 2) 
                : 0;
            
            Cache::put('api_avg_response_time', $avgResponseTime, now()->addHours(2));

            // 記錄請求數（增加計數器）
            $requestCount = Cache::get($requestsKey, 0) + 1;
            Cache::put($requestsKey, $requestCount, now()->addHours(2));
            
            // 更新每小時請求數
            Cache::put('api_requests_per_hour', $requestCount, now()->addHours(2));

        } catch (\Exception $e) {
            // 記錄錯誤但不中斷請求
            Log::warning('Failed to update API metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 過濾敏感資料
     */
    private function sanitizeData($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'access_token', 'channel_secret'];

        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***HIDDEN***';
            }
        }

        return $data;
    }
}
