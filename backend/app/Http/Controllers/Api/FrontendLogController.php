<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FrontendLogController extends Controller
{
    /**
     * 接收前端日誌（單筆或批次）
     */
    public function store(Request $request)
    {
        try {
            // 檢查是否為批次日誌
            if ($request->has('logs') && is_array($request->input('logs'))) {
                return $this->storeBatch($request);
            }

            // 單筆日誌驗證
            $validated = $request->validate([
                'level' => 'required|in:error,warn,info,debug',
                'message' => 'required|string|max:1000',
                'category' => 'sometimes|string|max:50',
                'context' => 'sometimes|array',
            ]);

            $this->logEntry($validated, $request);

            return response()->json([
                'success' => true,
                'message' => 'Log recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::channel('error')->error('Failed to process frontend log', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process log'
            ], 500);
        }
    }

    /**
     * 批次接收前端日誌
     */
    private function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'logs' => 'required|array|max:50', // 最多一次 50 筆
            'logs.*.level' => 'required|in:error,warn,info,debug',
            'logs.*.message' => 'required|string|max:1000',
            'logs.*.category' => 'sometimes|string|max:50',
            'logs.*.context' => 'sometimes|array',
            'logs.*.timestamp' => 'sometimes|string',
        ]);

        $recordedCount = 0;

        foreach ($validated['logs'] as $log) {
            try {
                $this->logEntry($log, $request);
                $recordedCount++;
            } catch (\Exception $e) {
                // 繼續處理其他日誌
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Recorded {$recordedCount} logs successfully",
            'count' => $recordedCount
        ]);
    }

    /**
     * 記錄前端錯誤（帶完整堆疊資訊）
     */
    public function storeError(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'stack' => 'sometimes|string|max:5000',
                'component' => 'sometimes|string|max:255',
                'url' => 'sometimes|string|max:2000',
                'line' => 'sometimes|integer',
                'column' => 'sometimes|integer',
                'user_agent' => 'sometimes|string',
                'screen_resolution' => 'sometimes|string',
                'viewport' => 'sometimes|string',
                'context' => 'sometimes|array',
            ]);

            $errorContext = [
                'error_details' => [
                    'message' => $validated['message'],
                    'stack' => $validated['stack'] ?? null,
                    'component' => $validated['component'] ?? null,
                    'url' => $validated['url'] ?? null,
                    'line' => $validated['line'] ?? null,
                    'column' => $validated['column'] ?? null,
                ],
                'browser_info' => [
                    'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                    'screen_resolution' => $validated['screen_resolution'] ?? null,
                    'viewport' => $validated['viewport'] ?? null,
                ],
                'request_info' => [
                    'ip' => $request->ip(),
                    'user_id' => Auth::id(),
                    'referer' => $request->header('referer'),
                ],
                'additional_context' => $validated['context'] ?? [],
                'timestamp' => now()->toIso8601String(),
            ];

            // 記錄詳細錯誤到前端日誌
            Log::channel('frontend')->error(
                '[Frontend Error] ' . $validated['message'],
                $errorContext
            );

            // 如果是嚴重錯誤，也記錄到錯誤日誌
            if ($this->isCriticalError($validated['message'])) {
                Log::channel('error')->error(
                    '[Frontend Critical] ' . $validated['message'],
                    $errorContext
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Error recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::channel('error')->error('Failed to process frontend error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process error'
            ], 500);
        }
    }

    /**
     * 處理單筆日誌記錄
     */
    private function logEntry(array $log, Request $request)
    {
        $level = $log['level'];
        $message = $log['message'];
        $category = $log['category'] ?? 'general';
        $context = $log['context'] ?? [];

        // 建立日誌上下文
        $logContext = [
            'category' => $category,
            'frontend_context' => $context,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'referer' => $request->header('referer'),
            'frontend_timestamp' => $log['timestamp'] ?? null,
            'server_timestamp' => now()->toIso8601String(),
        ];

        // 根據類別選擇日誌頻道
        $channel = $this->getChannelByCategory($category);

        // 記錄日誌
        Log::channel($channel)->log(
            $level,
            "[Frontend:{$category}] {$message}",
            $logContext
        );
    }

    /**
     * 根據類別取得日誌頻道
     */
    private function getChannelByCategory(string $category): string
    {
        $categoryChannelMap = [
            'api_request' => 'api',
            'api_response' => 'api',
            'api_error' => 'api',
            'api_network' => 'api',
            'api_auth' => 'api',
            'api_rate_limit' => 'api',
            'api_security' => 'security',
            'vue_error' => 'frontend',
            'vue_warning' => 'frontend',
            'error' => 'frontend',
            'performance' => 'frontend',
            'user_action' => 'frontend',
            'page_view' => 'frontend',
            'component' => 'frontend',
        ];

        return $categoryChannelMap[$category] ?? 'frontend';
    }

    /**
     * 判斷是否為嚴重錯誤
     */
    private function isCriticalError(string $message): bool
    {
        $criticalKeywords = [
            'cannot read',
            'undefined is not',
            'null is not',
            'network error',
            'failed to fetch',
            'timeout',
            'security',
            'permission denied',
            'cors',
            'unauthorized',
        ];

        $lowerMessage = strtolower($message);

        foreach ($criticalKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
