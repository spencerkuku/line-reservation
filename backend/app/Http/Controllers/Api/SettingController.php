<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // 獲取 LINE 設定
    public function getLineSettings()
    {
        $channelAccessToken = Setting::get('line_channel_access_token', '');
        $channelSecret = Setting::get('line_channel_secret', '');

        // 為了安全，只返回部分遮蔽的 token 讓前端顯示
        $maskedToken = $channelAccessToken ? $this->maskToken($channelAccessToken) : '';
        $maskedSecret = $channelSecret ? $this->maskToken($channelSecret) : '';

        return response()->json([
            'success' => true,
            'data' => [
                'channel_access_token' => $maskedToken,
                'channel_secret' => $maskedSecret
            ]
        ]);
    }

    // 遮蔽敏感資訊的輔助方法
    private function maskToken($token)
    {
        if (!$token || strlen($token) <= 8) {
            return $token;
        }
        
        $start = substr($token, 0, 4);
        $end = substr($token, -4);
        $middle = str_repeat('*', strlen($token) - 8);
        
        return $start . $middle . $end;
    }

    // 更新 LINE 設定
    public function updateLineSettings(Request $request)
    {
        try {
            $request->validate([
                'channel_access_token' => 'required|string',
                'channel_secret' => 'required|string'
            ]);

            // 獲取舊值（遮蔽顯示）
            $oldToken = Setting::get('line_channel_access_token', '');
            $oldSecret = Setting::get('line_channel_secret', '');

            // 只儲存到資料庫，不同步到 .env（基於資安考量）
            Setting::set('line_channel_access_token', $request->channel_access_token);
            Setting::set('line_channel_secret', $request->channel_secret);

            // 記錄操作（遮蔽敏感資訊）
            ActivityLogger::custom(
                'updated',
                'settings',
                '更新 LINE Bot 設定',
                [
                    'old_token' => $oldToken ? $this->maskToken($oldToken) : 'empty',
                    'new_token' => $this->maskToken($request->channel_access_token),
                    'old_secret' => $oldSecret ? $this->maskToken($oldSecret) : 'empty',
                    'new_secret' => $this->maskToken($request->channel_secret),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'LINE 設定已更新並安全儲存'
            ]);
        } catch (\Exception $e) {
            ActivityLogger::failed('update', 'settings', '更新 LINE 設定失敗', $e);
            throw $e;
        }
    }

    // 獲取所有設定
    public function getAllSettings()
    {
        // 獲取所有非敏感設定
        $settings = Setting::whereNotIn('key', ['line_channel_access_token', 'line_channel_secret'])
            ->get()
            ->pluck('value', 'key');
        
        // 加入預約確認模式的預設值
        $settings['reservation_confirm_mode'] = Setting::get('reservation_confirm_mode', 'auto');
        
        // 加入報到提醒設定的預設值
        $settings['check_in_reminder_enabled'] = Setting::get('check_in_reminder_enabled', '0');
        $settings['business_address'] = Setting::get('business_address', '');
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    // 更新單一設定
    public function updateSetting(Request $request)
    {
        try {
            $request->validate([
                'key' => 'required|string',
                'value' => 'required',
                'type' => 'sometimes|in:string,json,boolean,integer'
            ]);

            // 獲取舊值
            $oldValue = Setting::get($request->key, null);

            Setting::set(
                $request->key, 
                $request->value, 
                $request->type ?? 'string'
            );

            // 記錄操作
            ActivityLogger::custom(
                'updated',
                'settings',
                "更新系統設定: {$request->key}",
                [
                    'key' => $request->key,
                    'old_value' => $oldValue,
                    'new_value' => $request->value,
                    'type' => $request->type ?? 'string',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '設定已更新'
            ]);
        } catch (\Exception $e) {
            ActivityLogger::failed('update', 'settings', "更新設定失敗: {$request->key}", $e);
            throw $e;
        }
    }
}
