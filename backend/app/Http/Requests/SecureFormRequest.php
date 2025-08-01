<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

abstract class SecureFormRequest extends FormRequest
{
    /**
     * 安全的表單請求基類
     * 符合 OWASP A03:2021 建議
     */
    
    /**
     * 準備驗證數據（清理輸入）
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeInputs();
    }

    /**
     * 清理所有輸入數據
     */
    private function sanitizeInputs(): void
    {
        $input = $this->all();
        $sanitized = $this->recursiveSanitize($input);
        $this->replace($sanitized);
    }

    /**
     * 遞歸清理數組數據
     */
    private function recursiveSanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            }
        }
        return $data;
    }

    /**
     * 清理字符串輸入
     */
    private function sanitizeString(string $value): string
    {
        // 移除 null 字符
        $value = str_replace("\0", '', $value);
        
        // 清理控制字符（除了常見的空白字符）
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // 清理 SQL 注入嘗試
        $value = $this->cleanSqlInjection($value);
        
        // 清理 XSS 嘗試
        $value = $this->cleanXss($value);
        
        return trim($value);
    }

    /**
     * 清理 SQL 注入嘗試
     */
    private function cleanSqlInjection(string $value): string
    {
        $patterns = [
            '/(\s*(union|select|insert|update|delete|drop|create|alter|exec|execute)\s+)/i',
            '/(\s*(or|and)\s+\d+\s*=\s*\d+)/i',
            '/(\s*[\'"`]\s*(or|and)\s*[\'"`]\s*=\s*[\'"`])/i',
        ];
        
        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        return $value;
    }

    /**
     * 清理 XSS 嘗試
     */
    private function cleanXss(string $value): string
    {
        // 移除可能的腳本標籤
        $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $value);
        
        // 移除事件處理器
        $value = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $value);
        
        // 移除 javascript: 協議
        $value = preg_replace('/javascript\s*:/i', '', $value);
        
        return $value;
    }

    /**
     * 通用驗證規則
     */
    protected function getBaseRules(): array
    {
        return [
            'name' => 'required|string|max:255|regex:/^[\p{L}\p{M}\p{N}\s\-\.]+$/u',
            'email' => 'nullable|email:rfc,dns|max:255',
            'phone' => 'nullable|string|max:20|regex:/^[\+]?[0-9\-\s\(\)]+$/',
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * 通用錯誤訊息
     */
    protected function getBaseMessages(): array
    {
        return [
            'required' => ':attribute 為必填項目',
            'string' => ':attribute 必須為文字',
            'max' => ':attribute 不得超過 :max 個字符',
            'email' => '請輸入有效的電子信箱格式',
            'regex' => ':attribute 格式不正確',
            'numeric' => ':attribute 必須為數字',
            'positive' => ':attribute 必須為正數',
        ];
    }
}
