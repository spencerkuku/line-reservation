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
        // 在開發環境中跳過簽名驗證
        if (config('app.env') !== 'local') {
            // 驗證 LINE 簽章
            $signature = $request->header('X-Line-Signature');
            $body = $request->getContent();
            
            if (!$this->verifySignature($signature, $body)) {
                Log::warning('Invalid LINE signature');
                return response('Invalid signature', 400);
            }
        }

        // 處理 webhook 事件
        $events = $request->input('events', []);
        
        // 記錄接收到的事件
        Log::info('LINE webhook received events: ' . json_encode($events));
        
        try {
            $this->lineBotService->handleWebhook($events);
            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('LINE webhook error: ' . $e->getMessage());
            return response('Error', 500);
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
