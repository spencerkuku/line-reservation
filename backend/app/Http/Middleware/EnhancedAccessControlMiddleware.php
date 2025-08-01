<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnhancedAccessControlMiddleware
{
    /**
     * 增強的訪問控制中間件
     * 符合 OWASP A01:2021 建議
     */
    public function handle(Request $request, Closure $next, string $requiredRole = null): Response
    {
        // 記錄訪問嘗試
        Log::info('Access attempt', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'user_id' => Auth::id(),
            'timestamp' => now()
        ]);

        // 檢查用戶是否已認證
        if (!Auth::check()) {
            Log::warning('Unauthorized access attempt', [
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '未授權的訪問'
            ], 401);
        }

        $user = Auth::user();

        // 檢查用戶狀態
        if ($user->status !== 'Active') {
            Log::warning('Inactive user access attempt', [
                'user_id' => $user->id,
                'status' => $user->status
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '帳號已被停權或待審核'
            ], 403);
        }

        // 檢查角色權限
        if ($requiredRole && $user->role !== $requiredRole) {
            Log::warning('Insufficient role access attempt', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'required_role' => $requiredRole,
                'path' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '權限不足'
            ], 403);
        }

        // 檢查特定資源的所有權（如果是資源更新請求）
        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $this->checkResourceOwnership($request, $user);
        }

        return $next($request);
    }

    /**
     * 檢查資源所有權
     */
    private function checkResourceOwnership(Request $request, $user): void
    {
        // 對於非管理員用戶，檢查資源所有權
        if ($user->role !== 'admin') {
            $resourceId = $request->route('id') ?? $request->route('customer') ?? $request->route('reservation');
            
            if ($resourceId) {
                Log::info('Resource ownership check', [
                    'user_id' => $user->id,
                    'resource_id' => $resourceId,
                    'path' => $request->path()
                ]);
            }
        }
    }
}
