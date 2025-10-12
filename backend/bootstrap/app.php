<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 全局安全中間件
        $middleware->web(append: [
            \App\Http\Middleware\EnhancedSecurityHeadersMiddleware::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\EnhancedSecurityHeadersMiddleware::class,
            \App\Http\Middleware\ApiRateLimitMiddleware::class,
            \App\Http\Middleware\ApiLoggingMiddleware::class,
            \App\Http\Middleware\ApiLogger::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'rate_limit' => \App\Http\Middleware\ApiRateLimitMiddleware::class,
            'access_control' => \App\Http\Middleware\EnhancedAccessControlMiddleware::class,
            'verify.line.signature' => \App\Http\Middleware\VerifyLineSignature::class,
        ]);

        // 禁用一些不安全的中間件（OWASP安全考量）
        $middleware->validateCsrfTokens(except: [
            '/api/*',            // 所有 API 路由排除CSRF檢查，使用 Sanctum token 認證
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 記錄所有異常到錯誤日誌
        $exceptions->report(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('error')->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
        });

        // API 認證異常處理
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '未經授權的訪問',
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }
        });

        // API 驗證異常處理
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證失敗',
                    'errors' => $e->errors(),
                    'timestamp' => now()->toIso8601String(),
                ], 422);
            }
        });

        // API 其他異常統一處理
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: '伺服器內部錯誤',
                    'error_code' => $e->getCode(),
                    'timestamp' => now()->toIso8601String(),
                ], $statusCode);
            }
        });
    })->create();
