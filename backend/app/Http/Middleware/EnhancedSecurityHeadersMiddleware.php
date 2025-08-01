<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnhancedSecurityHeadersMiddleware
{
    /**
     * 增強的安全標頭中間件
     * 符合 OWASP A05:2021 建議
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 基本安全標頭
        $this->setBasicSecurityHeaders($response);
        
        // 內容安全政策
        $this->setContentSecurityPolicy($response, $request);
        
        // HTTPS 相關標頭
        $this->setHttpsHeaders($response, $request);
        
        // 移除敏感資訊
        $this->removeSensitiveHeaders($response);
        
        // 快取控制
        $this->setCacheControlHeaders($response, $request);

        return $response;
    }

    /**
     * 設定基本安全標頭
     */
    private function setBasicSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin'
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
    }

    /**
     * 設定內容安全政策
     */
    private function setContentSecurityPolicy(Response $response, Request $request): void
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $isProduction = app()->environment('production');
        
        // 基礎 CSP 設定
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self'" . ($isProduction ? '' : " 'unsafe-inline' 'unsafe-eval'"),
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "object-src 'none'",
            "media-src 'self'",
            "frame-src 'none'",
            "child-src 'none'",
            "worker-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "manifest-src 'self'"
        ];

        // 連接來源設定
        $connectSrc = "'self'";
        if ($isProduction) {
            $connectSrc .= " " . $frontendUrl;
        } else {
            $connectSrc .= " http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:*";
        }
        $cspDirectives[] = "connect-src {$connectSrc}";

        // 升級不安全請求（僅生產環境）
        if ($isProduction && $request->secure()) {
            $cspDirectives[] = "upgrade-insecure-requests";
        }

        $csp = implode('; ', $cspDirectives);
        
        // CSP 違規報告端點
        if (env('CSP_REPORT_URI')) {
            $csp .= "; report-uri " . env('CSP_REPORT_URI');
        }

        $response->headers->set('Content-Security-Policy', $csp);
        
        // 同時設定 Report-Only 版本用於測試
        if (!$isProduction && env('CSP_REPORT_ONLY', false)) {
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }
    }

    /**
     * 設定 HTTPS 相關標頭
     */
    private function setHttpsHeaders(Response $response, Request $request): void
    {
        if (app()->environment('production') && $request->secure()) {
            // HTTP Strict Transport Security
            $response->headers->set(
                'Strict-Transport-Security', 
                'max-age=31536000; includeSubDomains; preload'
            );
        }
    }

    /**
     * 移除敏感的伺服器資訊
     */
    private function removeSensitiveHeaders(Response $response): void
    {
        $headersToRemove = [
            'Server',
            'X-Powered-By',
            'X-Served-By',
            'X-AspNet-Version',
            'X-AspNetMvc-Version'
        ];

        foreach ($headersToRemove as $header) {
            $response->headers->remove($header);
        }
    }

    /**
     * 設定快取控制標頭
     */
    private function setCacheControlHeaders(Response $response, Request $request): void
    {
        // 對於 API 回應，設定適當的快取控制
        if ($request->is('api/*')) {
            if ($request->isMethod('GET') && !str_contains($request->path(), 'auth')) {
                // 對於非敏感的 GET 請求，允許短時間快取
                $response->headers->set('Cache-Control', 'private, max-age=300, must-revalidate');
            } else {
                // 對於敏感或非 GET 請求，禁止快取
                $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }
        }
    }
}
