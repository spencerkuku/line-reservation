<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DataIntegrityService
{
    /**
     * 數據完整性服務
     * 符合 OWASP A08:2021 建議
     */

    /**
     * 計算數據完整性校驗碼
     */
    public static function calculateChecksum(array $data): string
    {
        // 移除敏感字段
        $cleanData = self::removeSensitiveFields($data);
        
        // 排序數據以確保一致性
        ksort($cleanData);
        
        // 生成校驗碼
        return hash('sha256', json_encode($cleanData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 驗證數據完整性
     */
    public static function verifyIntegrity(array $data, string $expectedChecksum): bool
    {
        $actualChecksum = self::calculateChecksum($data);
        $isValid = hash_equals($expectedChecksum, $actualChecksum);
        
        if (!$isValid) {
            Log::warning('Data integrity check failed', [
                'expected' => $expectedChecksum,
                'actual' => $actualChecksum,
                'data_keys' => array_keys($data)
            ]);
        }
        
        return $isValid;
    }

    /**
     * 移除敏感字段
     */
    private static function removeSensitiveFields(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'remember_token',
            'created_at',
            'updated_at'
        ];

        return array_diff_key($data, array_flip($sensitiveFields));
    }

    /**
     * 簽名數據
     */
    public static function signData(array $data, string $secretKey): string
    {
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $secretKey);
        
        return $payload . '.' . $signature;
    }

    /**
     * 驗證數據簽名
     */
    public static function verifySignature(string $signedData, string $secretKey): ?array
    {
        $parts = explode('.', $signedData);
        
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Data signature verification failed');
            return null;
        }

        try {
            return json_decode(base64_decode($payload), true);
        } catch (\Exception $e) {
            Log::error('Failed to decode signed data', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 生成版本控制雜湊
     */
    public static function generateVersionHash(array $data): string
    {
        $version = [
            'data' => self::removeSensitiveFields($data),
            'timestamp' => now()->timestamp,
            'app_version' => config('app.version', '1.0.0')
        ];

        return hash('sha256', json_encode($version, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 驗證檔案完整性
     */
    public static function verifyFileIntegrity(string $filePath, string $expectedHash = null): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $actualHash = hash_file('sha256', $filePath);

        if ($expectedHash) {
            return hash_equals($expectedHash, $actualHash);
        }

        // 如果沒有提供預期雜湊值，記錄當前雜湊值供參考
        Log::info('File hash calculated', [
            'file' => basename($filePath),
            'hash' => $actualHash
        ]);

        return true;
    }

    /**
     * 驗證上傳檔案的完整性和安全性
     */
    public static function validateUploadedFile(\Illuminate\Http\UploadedFile $file): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'hash' => null
        ];

        // 檢查檔案是否完整上傳
        if (!$file->isValid()) {
            $validation['is_valid'] = false;
            $validation['errors'][] = '檔案上傳不完整';
            return $validation;
        }

        // 計算檔案雜湊
        $validation['hash'] = hash_file('sha256', $file->getRealPath());

        // 檢查檔案類型
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            $validation['is_valid'] = false;
            $validation['errors'][] = '不支援的檔案類型';
        }

        // 檢查檔案大小
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            $validation['is_valid'] = false;
            $validation['errors'][] = '檔案大小超過限制';
        }

        // 檢查檔案名稱
        $originalName = $file->getClientOriginalName();
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', pathinfo($originalName, PATHINFO_FILENAME))) {
            $validation['is_valid'] = false;
            $validation['errors'][] = '檔案名稱包含非法字符';
        }

        return $validation;
    }

    /**
     * 建立數據審計追蹤
     */
    public static function createAuditTrail(string $action, array $data, $userId = null): void
    {
        $auditData = [
            'action' => $action,
            'user_id' => $userId,
            'data_hash' => self::calculateChecksum($data),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ];

        Log::channel('audit')->info('Data audit trail', $auditData);
    }
}
