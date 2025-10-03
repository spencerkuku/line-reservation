<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
        $request->validate([
            'channel_access_token' => 'required|string',
            'channel_secret' => 'required|string'
        ]);

        // 只儲存到資料庫，不同步到 .env（基於資安考量）
        Setting::set('line_channel_access_token', $request->channel_access_token);
        Setting::set('line_channel_secret', $request->channel_secret);

        return response()->json([
            'success' => true,
            'message' => 'LINE 設定已更新並安全儲存'
        ]);
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
        $request->validate([
            'key' => 'required|string',
            'value' => 'required',
            'type' => 'sometimes|in:string,json,boolean,integer'
        ]);

        Setting::set(
            $request->key, 
            $request->value, 
            $request->type ?? 'string'
        );

        return response()->json([
            'success' => true,
            'message' => '設定已更新'
        ]);
    }
}
