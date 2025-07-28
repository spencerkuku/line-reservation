<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FrontendLogController extends Controller
{
    public function store(Request $request)
    {
        try {
            $logs = $request->input('logs', []);
            $sessionId = $request->input('session_id');
            
            if (empty($logs) || !is_array($logs)) {
                return response()->json(['success' => false, 'message' => 'No logs provided'], 400);
            }
            
            // 限制批量日誌數量以防止濫用
            $logs = array_slice($logs, 0, 50);
            
            foreach ($logs as $logEntry) {
                $this->processLogEntry($logEntry, $sessionId);
            }
            
            return response()->json([
                'success' => true,
                'processed' => count($logs)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to process frontend logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process logs'
            ], 500);
        }
    }
    
    private function processLogEntry(array $logEntry, string $sessionId = null)
    {
        $level = $logEntry['level'] ?? 'info';
        $message = $logEntry['message'] ?? 'Frontend log';
        $category = $logEntry['category'] ?? 'frontend';
        $data = $logEntry['data'] ?? [];
        
        // 添加前端特定的元數據
        $frontendData = [
            'frontend_log' => true,
            'session_id' => $sessionId,
            'timestamp' => $logEntry['timestamp'] ?? now()->toISOString(),
            'url' => $logEntry['url'] ?? null,
            'user_agent' => $logEntry['user_agent'] ?? null,
            'memory_usage' => $logEntry['memory_usage'] ?? null,
            'user_id' => $logEntry['user_id'] ?? null,
            'data' => $data
        ];
        
        switch ($category) {
            case 'api_request':
            case 'api_response':
            case 'api_error':
            case 'api_network':
            case 'api_auth':
            case 'api_rate_limit':
            case 'api_security':
                // 記錄到 API 日誌頻道
                $this->logToChannel('api', $level, $message, $frontendData);
                break;
                
            case 'user_action':
                // 記錄用戶操作
                LoggingService::logUserAction($data['action'] ?? 'unknown', array_merge($data, [
                    'source' => 'frontend',
                    'session_id' => $sessionId
                ]));
                break;
                
            case 'page_view':
                // 記錄頁面訪問
                $this->logToChannel('frontend', 'info', $message, $frontendData);
                break;
                
            case 'performance':
                // 記錄性能數據
                $this->logToChannel('frontend', 'info', $message, $frontendData);
                break;
                
            case 'component':
                // 記錄組件生命週期
                $this->logToChannel('frontend', $level, $message, $frontendData);
                break;
                
            case 'vue_error':
            case 'vue_warning':
            case 'error':
                // 記錄前端錯誤
                $this->logToChannel('frontend', 'error', $message, $frontendData);
                break;
                
            default:
                // 一般前端日誌
                $this->logToChannel('frontend', $level, $message, $frontendData);
                break;
        }
    }
    
    private function logToChannel(string $channel, string $level, string $message, array $data)
    {
        $logData = [
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
        
        switch ($level) {
            case 'error':
                Log::channel($channel)->error($message, $logData);
                break;
            case 'warning':
                Log::channel($channel)->warning($message, $logData);
                break;
            case 'info':
            default:
                Log::channel($channel)->info($message, $logData);
                break;
        }
    }
}
