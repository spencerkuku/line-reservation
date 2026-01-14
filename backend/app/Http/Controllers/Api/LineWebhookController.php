<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\LineBotService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    /**
     * 處理多租戶 LINE webhook
     * 路由: POST /api/webhook/{webhook_token}
     */
    public function handle(Request $request, string $webhook_token)
    {
        try {
            // 從中間件獲取租戶（已由 WebhookTenantMiddleware 處理）
            $tenant = $request->attributes->get('tenant');
            
            if (!$tenant) {
                // 備援：如果中間件沒有設定，直接查詢
                $tenant = Tenant::where('webhook_token', $webhook_token)->first();
                
                if (!$tenant) {
                    Log::warning('Webhook: Tenant not found', ['webhook_token' => $webhook_token]);
                    return response('OK', 200);
                }
            }

            Log::info('LINE webhook request received', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);

            // 驗證 LINE 簽章
            $signature = $request->header('X-Line-Signature');
            $body = $request->getContent();
            
            if (!$this->verifySignature($request, $signature, $body, $tenant)) {
                Log::warning('Invalid LINE signature for tenant', [
                    'tenant_id' => $tenant->id,
                    'signature' => $signature,
                    'body_length' => strlen($body)
                ]);
                return response('Invalid signature', 400);
            }

            // 處理 webhook 事件
            $events = $request->input('events', []);
            
            Log::info('LINE webhook events', [
                'tenant_id' => $tenant->id,
                'event_count' => count($events),
            ]);
            
            // 使用租戶專用的 LineBotService 處理事件
            $lineBotService = new LineBotService($tenant);
            $lineBotService->handleWebhook($events);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('LINE webhook error', [
                'webhook_token' => $webhook_token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 即使發生錯誤也返回 200，避免 LINE 重複發送
            return response('OK', 200);
        }
    }

    /**
     * 驗證 LINE 簽章（使用租戶憑證）
     * 優化：優先從 request attributes 取得已解密的 secret，避免重複查詢
     */
    private function verifySignature(Request $request, $signature, $body, Tenant $tenant)
    {
        if (!$signature) {
            return false;
        }

        // 優先從中間件傳遞的 attributes 取得已解密的 channel secret
        $channelSecret = $request->attributes->get('line_channel_secret');
        
        // 如果中間件沒有提供，則從資料庫查詢（備援方案）
        if (!$channelSecret) {
            $setting = Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('key', 'line_channel_secret')
                ->first();
            
            if (!$setting) {
                return false;
            }

            // 使用 Setting model 的解密邏輯
            $channelSecret = $setting->value;
            try {
                $channelSecret = \Illuminate\Support\Facades\Crypt::decryptString($channelSecret);
            } catch (\Exception $e) {
                // 如果解密失敗，使用原值（可能是未加密的舊數據）
                Log::warning('Failed to decrypt channel secret, using raw value', [
                    'tenant_id' => $tenant->id,
                ]);
            }
        }

        $hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        return hash_equals($signature, $hash);
    }
}
