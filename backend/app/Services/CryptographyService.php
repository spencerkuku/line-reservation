<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CryptographyService
{
    /**
     * 增強的密碼雜湊
     * 符合 OWASP A02:2021 建議
     */
    public static function hashPassword(string $password): string
    {
        // 使用 Argon2id 算法（Laravel 預設）
        return Hash::make($password);
    }

    /**
     * 驗證密碼
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }

    /**
     * 加密敏感數據
     */
    public static function encryptSensitiveData(string $data): string
    {
        return Crypt::encryptString($data);
    }

    /**
     * 解密敏感數據
     */
    public static function decryptSensitiveData(string $encryptedData): string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (\Exception $e) {
            throw new \RuntimeException('解密失敗');
        }
    }

    /**
     * 生成安全的隨機 token
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * 生成 API key
     */
    public static function generateApiKey(): string
    {
        return 'lr_' . Str::random(40);
    }

    /**
     * 安全的隨機數生成
     */
    public static function generateSecureRandom(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * 雜湊敏感識別碼（用於日誌）
     */
    public static function hashIdentifier(string $identifier): string
    {
        return hash('sha256', $identifier);
    }
}
