<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * WebhookTenantMiddleware
 * 
 * 此中間件用於 LINE Webhook 路由，根據 URL 中的 webhook_token 識別租戶。
 */
class WebhookTenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $webhookToken = $request->route('webhook_token');

        if (!$webhookToken) {
            Log::warning('Webhook request without token', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook token required',
            ], 400);
        }

        // 查找租戶（使用 UUID token）
        $tenant = Tenant::where('webhook_token', $webhookToken)->first();

        if (!$tenant) {
            Log::warning('Webhook request for unknown tenant', [
                'webhook_token' => substr($webhookToken, 0, 8) . '...', // 只記錄部分 token
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // 檢查租戶狀態（包含到期檢查）
        if (!$tenant->isActive()) {
            Log::warning('Webhook request for inactive/expired tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'status' => $tenant->status,
                'expiration_status' => $tenant->expiration_status,
            ]);

            // 對於 webhook，仍然返回 200 以避免 LINE 重複發送
            return response('OK', 200);
        }

        // 檢查租戶是否有 LINE 設定（從 settings 表取得）
        $hasLineConfig = \App\Models\Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->whereIn('key', ['line_channel_access_token', 'line_channel_secret'])
            ->whereNotNull('value')
            ->count() >= 2;
            
        if (!$hasLineConfig) {
            Log::warning('Webhook request for tenant without LINE configuration', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);

            // 返回 200 避免 LINE 重複發送
            return response('OK', 200);
        }

        // 綁定當前租戶到容器
        app()->instance('currentTenant', $tenant);

        // 將租戶資訊存入 request 中，方便後續使用
        $request->attributes->set('tenant', $tenant);

        Log::info('Webhook request processed', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        return $next($request);
    }
}
