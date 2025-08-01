<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyLineSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // 獲取 LINE 簽名
        $signature = $request->header('X-Line-Signature');
        
        if (!$signature) {
            Log::warning('LINE webhook received without signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            abort(401, 'Missing signature');
        }

        // 獲取請求內容
        $body = $request->getContent();
        
        // 從資料庫或配置文件獲取 secret
        $secret = \App\Models\Setting::get('line_channel_secret') ?? config('linebot.channel_secret');

        if (!$secret) {
            Log::error('LINE channel secret not configured');
            abort(500, 'Server configuration error');
        }

        // 計算預期簽名
        $hash = hash_hmac('sha256', $body, $secret, true);
        $expectedSignature = base64_encode($hash);

        // 安全比較簽名
        if (!hash_equals($signature, $expectedSignature)) {
            Log::warning('LINE webhook signature verification failed', [
                'ip' => $request->ip(),
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
            abort(401, 'Invalid signature');
        }

        Log::info('LINE webhook signature verified successfully');
        
        return $next($request);
    }
}
