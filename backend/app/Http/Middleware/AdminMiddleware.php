<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 檢查用戶是否已認證
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => '請先登入'
            ], 401);
        }

        // 檢查用戶角色是否為管理員（包含租戶管理員和系統管理員）
        if ($request->user()->role !== 'admin' && $request->user()->role !== 'system_admin') {
            return response()->json([
                'success' => false,
                'message' => '權限不足，僅限管理員操作'
            ], 403);
        }

        return $next($request);
    }
}
