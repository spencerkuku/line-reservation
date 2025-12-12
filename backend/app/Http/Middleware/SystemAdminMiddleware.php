<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SystemAdminMiddleware
 * 
 * 此中間件確保只有系統管理員可以訪問特定路由。
 */
class SystemAdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '未授權存取',
            ], 401);
        }

        if (!$user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => '僅系統管理員可執行此操作',
            ], 403);
        }

        return $next($request);
    }
}
