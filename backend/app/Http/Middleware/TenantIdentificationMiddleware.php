<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantIdentificationMiddleware
 * 
 * 此中間件負責識別當前用戶的租戶，並將其綁定到容器中。
 * 系統管理員不受租戶限制。
 */
class TenantIdentificationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // 系統管理員不設定租戶（可以看到所有資料）
        if ($user->isSystemAdmin()) {
            // 清除任何可能存在的租戶綁定
            app()->forgetInstance('currentTenant');
            return $next($request);
        }

        // 普通用戶必須屬於某個租戶
        if (!$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => '用戶未關聯到任何租戶',
            ], 403);
        }

        // 獲取租戶
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => '租戶不存在',
            ], 403);
        }

        // 檢查租戶狀態
        if (!$tenant->isActive()) {
            $message = match($tenant->status) {
                'inactive' => '租戶帳號已停用',
                'suspended' => '租戶帳號已暫停',
                'trial' => '試用期已結束',
                default => '租戶帳號無法使用',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        // 綁定當前租戶到容器
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
