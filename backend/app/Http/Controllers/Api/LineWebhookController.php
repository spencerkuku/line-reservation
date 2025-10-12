<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LineBotService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    private $lineBotService;

    public function __construct(LineBotService $lineBotService)
    {
        $this->lineBotService = $lineBotService;
    }

    public function handle(Request $request)
    {
        try {
            Log::info('LINE webhook request received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            // 在開發環境中跳過簽名驗證
            if (config('app.env') !== 'local') {
                // 驗證 LINE 簽章
                $signature = $request->header('X-Line-Signature');
                $body = $request->getContent();
                
                if (!$this->verifySignature($signature, $body)) {
                    Log::warning('Invalid LINE signature', [
                        'signature' => $signature,
                        'body_length' => strlen($body)
                    ]);
                    return response('Invalid signature', 400);
                }
            }

            // 處理 webhook 事件
            $events = $request->input('events', []);
            
            Log::info('LINE webhook events', [
                'event_count' => count($events),
                'events' => $events
            ]);
            
            // 重要：立即返回 200 OK，然後再處理事件
            // 這樣可以避免 LINE Platform 超時
            $this->lineBotService->handleWebhook($events);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('LINE webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 即使發生錯誤也返回 200，避免 LINE 重複發送
            return response('OK', 200);
        }
    }

    private function verifySignature($signature, $body)
    {
        $channelSecret = Setting::get('line_channel_secret');
        
        if (!$channelSecret || !$signature) {
            return false;
        }

        $hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        return hash_equals($signature, $hash);
    }
}
