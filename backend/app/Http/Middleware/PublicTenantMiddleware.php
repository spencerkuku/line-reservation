<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PublicTenantMiddleware
 * 
 * 此中間件用於公開 API 路由，通過 URL 參數識別租戶。
 * 用於顧客端查看服務和時段列表等功能。
 */
class PublicTenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 從 URL 路由參數中獲取租戶 slug
        $slug = $request->route('tenant_slug');

        if (!$slug) {
            return response()->json([
                'success' => false,
                'message' => '缺少租戶識別碼',
            ], 400);
        }

        // 查找租戶
        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => '租戶不存在',
            ], 404);
        }

        // 檢查租戶是否啟用
        if (!$tenant->isActive()) {
            return response()->json([
                'success' => false,
                'message' => '此租戶服務暫時無法使用',
            ], 403);
        }

        // 綁定當前租戶到容器
        app()->instance('currentTenant', $tenant);

        // 將租戶資訊存入 request 中
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
