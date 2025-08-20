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
        // API 認證異常處理
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '未經授權的訪問'
                ], 401);
            }
        });
    })->create();
