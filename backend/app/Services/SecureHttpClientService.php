<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SecureHttpClientService
{
    /**
     * 安全的 HTTP 客戶端服務
     * 符合 OWASP A10:2021 建議，防護 SSRF 攻擊
     */

    private $allowedSchemes = ['http', 'https'];
    private $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',  // GCP metadata
        '169.254.169.254',           // AWS metadata
    ];
    private $blockedPorts = [22, 23, 25, 53, 110, 143, 993, 995];
    private $maxRedirects = 3;
    private $timeout = 30;

    /**
     * 安全的 HTTP GET 請求
     */
    public function safeGet(string $url, array $options = []): ?array
    {
        if (!$this->validateUrl($url)) {
            return null;
        }

        try {
            $client = new Client([
                'timeout' => $this->timeout,
                'allow_redirects' => [
                    'max' => $this->maxRedirects,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => $this->allowedSchemes
                ],
                'verify' => true, // 驗證 SSL 證書
                'headers' => [
                    'User-Agent' => 'LINE-Reservation-System/1.0',
                    'Accept' => 'application/json',
                ]
            ]);

            $response = $client->get($url, $options);
            
            // 記錄成功的外部請求
            Log::info('Successful external HTTP request', [
                'url' => $this->sanitizeUrlForLogging($url),
                'status_code' => $response->getStatusCode()
            ]);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents()
            ];

        } catch (GuzzleException $e) {
            Log::warning('External HTTP request failed', [
                'url' => $this->sanitizeUrlForLogging($url),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 安全的 HTTP POST 請求
     */
    public function safePost(string $url, array $data = [], array $options = []): ?array
    {
        if (!$this->validateUrl($url)) {
            return null;
        }

        try {
            $client = new Client([
                'timeout' => $this->timeout,
                'allow_redirects' => false, // POST 請求不允許重導向
                'verify' => true,
                'headers' => [
                    'User-Agent' => 'LINE-Reservation-System/1.0',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);

            $response = $client->post($url, array_merge([
                'json' => $data
            ], $options));

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents()
            ];

        } catch (GuzzleException $e) {
            Log::warning('External HTTP POST request failed', [
                'url' => $this->sanitizeUrlForLogging($url),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 驗證 URL 安全性
     */
    private function validateUrl(string $url): bool
    {
        // 解析 URL
        $parsed = parse_url($url);
        
        if ($parsed === false) {
            Log::warning('Invalid URL format', ['url' => $this->sanitizeUrlForLogging($url)]);
            return false;
        }

        // 檢查協議
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], $this->allowedSchemes)) {
            Log::warning('Blocked URL scheme', [
                'url' => $this->sanitizeUrlForLogging($url),
                'scheme' => $parsed['scheme'] ?? 'none'
            ]);
            return false;
        }

        // 檢查主機
        if (!isset($parsed['host'])) {
            Log::warning('Missing host in URL', ['url' => $this->sanitizeUrlForLogging($url)]);
            return false;
        }

        $host = strtolower($parsed['host']);
        
        // 檢查是否為被阻擋的主機
        if (in_array($host, $this->blockedHosts)) {
            Log::warning('Blocked host access attempt', [
                'url' => $this->sanitizeUrlForLogging($url),
                'host' => $host
            ]);
            return false;
        }

        // 檢查私有 IP 範圍
        if ($this->isPrivateIp($host)) {
            Log::warning('Private IP access attempt', [
                'url' => $this->sanitizeUrlForLogging($url),
                'host' => $host
            ]);
            return false;
        }

        // 檢查端口
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        if (in_array($port, $this->blockedPorts)) {
            Log::warning('Blocked port access attempt', [
                'url' => $this->sanitizeUrlForLogging($url),
                'port' => $port
            ]);
            return false;
        }

        return true;
    }

    /**
     * 檢查是否為私有 IP 地址
     */
    private function isPrivateIp(string $host): bool
    {
        // 如果不是 IP 地址，先嘗試解析
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                // 無法解析的主機名，允許通過（可能是有效的域名）
                return false;
            }
            $host = $ip;
        }

        // 檢查私有 IP 範圍
        return !filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * 清理 URL 用於日誌記錄
     */
    private function sanitizeUrlForLogging(string $url): string
    {
        $parsed = parse_url($url);
        
        if ($parsed === false) {
            return '[INVALID_URL]';
        }

        // 移除敏感查詢參數
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            $sensitiveParams = ['token', 'key', 'secret', 'password', 'auth'];
            
            foreach ($sensitiveParams as $param) {
                if (isset($params[$param])) {
                    $params[$param] = '[REDACTED]';
                }
            }
            
            $parsed['query'] = http_build_query($params);
        }

        // 重建 URL
        $scheme = $parsed['scheme'] ?? '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        
        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * 取得允許的外部 API 端點列表
     */
    public function getAllowedEndpoints(): array
    {
        return [
            'line_api' => 'https://api.line.me',
            'payment_gateway' => config('services.payment.endpoint'),
            // 添加其他允許的外部 API 端點
        ];
    }

    /**
     * 檢查 URL 是否在允許列表中
     */
    public function isAllowedEndpoint(string $url): bool
    {
        $allowedEndpoints = $this->getAllowedEndpoints();
        
        foreach ($allowedEndpoints as $endpoint) {
            if ($endpoint && strpos($url, $endpoint) === 0) {
                return true;
            }
        }
        
        return false;
    }
}
