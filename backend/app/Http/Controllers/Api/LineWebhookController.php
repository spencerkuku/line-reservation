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
            
            if (!$this->verifySignature($signature, $body, $tenant)) {
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
     * 舊版 webhook 處理（向後兼容，不建議使用）
     * 路由: POST /api/line/webhook
     */
    public function handleLegacy(Request $request)
    {
        Log::warning('Legacy webhook endpoint called. Please use /api/webhook/{tenant_slug} instead.');
        
        try {
            // 嘗試使用預設設定
            $channelSecret = Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('key', 'line_channel_secret')
                ->whereNull('tenant_id')
                ->value('value');
            
            if (!$channelSecret) {
                Log::error('Legacy webhook: No default LINE configuration found');
                return response('OK', 200);
            }

            // 使用預設的 LineBotService
            $lineBotService = new LineBotService();
            
            $events = $request->input('events', []);
            $lineBotService->handleWebhook($events);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Legacy LINE webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('OK', 200);
        }
    }

    /**
     * 驗證 LINE 簽章（使用租戶憑證）
     */
    private function verifySignature($signature, $body, Tenant $tenant)
    {
        // 從 settings 表取得租戶的 LINE channel secret（需要解密）
        $setting = Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('key', 'line_channel_secret')
            ->first();
        
        if (!$setting || !$signature) {
            return false;
        }

        // 使用 Setting model 的解密邏輯
        $channelSecret = $setting->value;
        try {
            $channelSecret = \Illuminate\Support\Facades\Crypt::decryptString($channelSecret);
        } catch (\Exception $e) {
            // 如果解密失敗，使用原值（可能是未加密的舊數據）
        }

        $hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        return hash_equals($signature, $hash);
    }
}
