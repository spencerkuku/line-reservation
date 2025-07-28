<?php

namespace App\Http\Middleware;

use App\Services\LoggingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ApiLoggingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $requestId = LoggingService::generateRequestId();
        
        // 在請求頭中添加 requestId 以便其他組件使用
        $request->headers->set('X-Request-ID', $requestId);
        
        // 記錄請求開始
        LoggingService::logApiRequestStart($request, $requestId);
        
        try {
            $response = $next($request);
            
            // 記錄成功響應
            LoggingService::logApiRequestSuccess(
                $requestId, 
                $this->getResponseData($response),
                $response->status()
            );
            
            // 記錄性能數據
            LoggingService::logPerformance(
                'API Request: ' . $request->method() . ' ' . $request->path(),
                $startTime,
                [
                    'status_code' => $response->status(),
                    'user_id' => Auth::id(),
                    'ip' => $request->ip()
                ]
            );
            
            return $response;
            
        } catch (\Throwable $e) {
            // 記錄錯誤
            LoggingService::logApiRequestError($requestId, $e, $request->all());
            
            // 重新拋出異常以保持正常的錯誤處理流程
            throw $e;
        }
    }
    
    private function getResponseData($response)
    {
        try {
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            // 只記錄基本信息，避免敏感數據洩漏
            if (is_array($data)) {
                return [
                    'success' => $data['success'] ?? null,
                    'data_count' => isset($data['data']) && is_array($data['data']) ? count($data['data']) : null,
                    'message' => $data['message'] ?? null
                ];
            }
            
            return ['content_length' => strlen($content)];
        } catch (\Exception $e) {
            return ['error' => 'Failed to parse response'];
        }
    }
}
