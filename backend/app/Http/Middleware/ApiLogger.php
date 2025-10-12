<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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

        // 記錄回應
        Log::channel('api')->info('API Response', [
            'status_code' => $response->getStatusCode(),
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'response_size' => strlen($response->getContent()) . ' bytes',
        ]);

        return $response;
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
