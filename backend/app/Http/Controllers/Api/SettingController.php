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

        return response()->json([
            'success' => true,
            'data' => [
                'channel_access_token' => $channelAccessToken,
                'channel_secret' => $channelSecret
            ]
        ]);
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
        $settings = Setting::all()->pluck('value', 'key');
        
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
