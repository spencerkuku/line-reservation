<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        // 不同類型的請求有不同的限制
        $limits = $this->getLimitsForRequest($request);
        
        foreach ($limits as $limitKey => $maxAttempts) {
            $executed = RateLimiter::attempt(
                $key . ':' . $limitKey,
                $maxAttempts,
                function () {},
                60 // 1分鐘窗口
            );

            if (!$executed) {
                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => '請求過於頻繁，請稍後再試'
                ], 429);
            }
        }

        return $next($request);
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }

    /**
     * Get rate limits for different request types.
     */
    protected function getLimitsForRequest(Request $request): array
    {
        // 登入請求限制更嚴格
        if ($request->is('api/auth/login')) {
            return ['login' => 5]; // 每分鐘5次
        }

        // API 一般請求
        if ($request->is('api/*')) {
            return ['api' => 100]; // 每分鐘100次
        }

        // LINE webhook
        if ($request->is('api/webhook/*')) {
            return ['webhook' => 1000]; // 每分鐘1000次
        }

        return ['general' => 60]; // 一般請求每分鐘60次
    }
}
