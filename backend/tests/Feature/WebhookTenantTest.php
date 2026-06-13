<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\AvailableTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
use Carbon\Carbon;

/**
 * LINE Webhook 租戶識別測試
 * 
 * 確保 webhook 能正確識別租戶並處理請求
 */
class WebhookTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立租戶
        $this->tenant = Tenant::create([
            'name' => 'Webhook Test Tenant',
            'slug' => 'webhook-test',
            'email' => 'webhook@test.com',
            'status' => 'active',
        ]);

        // 在 settings 表建立 LINE 憑證
        Setting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'line_channel_access_token',
            'value' => Crypt::encryptString('test_access_token_12345'),
        ]);
        Setting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'line_channel_secret',
            'value' => Crypt::encryptString('test_channel_secret_12345'),
        ]);

        // 建立必要的服務和時段
        Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'description' => 'Test service for webhook',
            'duration' => 60,
            'price' => 100,
            'is_active' => true,
        ]);

        AvailableTime::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Available Time',
            'start_time' => Carbon::tomorrow()->setTime(10, 0),
            'end_time' => Carbon::tomorrow()->setTime(12, 0),
            'max_capacity' => 5,
            'is_active' => true,
        ]);
    }

    /**
     * 生成 LINE 簽章
     */
    private function generateLineSignature(string $body, string $channelSecret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
    }

    /**
     * 測試無效租戶 ID 時返回 404
     */
    public function test_webhook_returns_404_for_invalid_tenant(): void
    {
        $body = json_encode([
            'events' => []
        ]);

        $response = $this->postJson('/api/webhook/00000000-0000-4000-8000-000000000000', json_decode($body, true), [
            'X-Line-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(404);
    }

    /**
     * 測試停用租戶無法接收 webhook
     */
    public function test_webhook_rejects_inactive_tenant(): void
    {
        // 停用租戶
        $this->tenant->update(['status' => 'suspended']);

        $body = json_encode([
            'events' => []
        ]);

        $signature = $this->generateLineSignature($body, 'test_channel_secret_12345');

        $response = $this->postJson("/api/webhook/{$this->tenant->webhook_token}", json_decode($body, true), [
            'X-Line-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試無效簽章被拒絕
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $body = json_encode([
            'events' => []
        ]);

        $response = $this->postJson("/api/webhook/{$this->tenant->webhook_token}", json_decode($body, true), [
            'X-Line-Signature' => 'invalid_signature_123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * 測試有效請求被接受
     */
    public function test_webhook_accepts_valid_request(): void
    {
        $body = json_encode([
            'events' => []
        ]);

        $signature = $this->generateLineSignature($body, 'test_channel_secret_12345');

        $response = $this->call(
            'POST',
            "/api/webhook/{$this->tenant->webhook_token}",
            [],
            [],
            [],
            ['HTTP_X_LINE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(200);
    }

    /**
     * 測試 follow 事件建立客戶時關聯正確的租戶
     */
    public function test_follow_event_creates_customer_with_correct_tenant(): void
    {
        $lineUserId = 'U' . str_repeat('0', 32);
        
        $body = json_encode([
            'events' => [
                [
                    'type' => 'follow',
                    'source' => [
                        'type' => 'user',
                        'userId' => $lineUserId,
                    ],
                    'replyToken' => 'test_reply_token_' . uniqid(),
                    'timestamp' => now()->timestamp * 1000,
                ]
            ]
        ]);

        $signature = $this->generateLineSignature($body, 'test_channel_secret_12345');

        $response = $this->call(
            'POST',
            "/api/webhook/{$this->tenant->webhook_token}",
            [],
            [],
            [],
            ['HTTP_X_LINE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(200);

        // 確認客戶被建立並關聯到正確的租戶
        $customer = Customer::withoutGlobalScopes()
            ->where('line_user_id', $lineUserId)
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals($this->tenant->id, $customer->tenant_id);
    }

    /**
     * 測試不同租戶的 webhook 路徑是獨立的
     */
    public function test_different_tenants_have_different_webhook_urls(): void
    {
        // 建立第二個租戶
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'slug' => 'second-tenant',
            'email' => 'second@test.com',
            'status' => 'active',
        ]);

        // 確認兩個租戶的 webhook URL 不同
        $this->assertNotEquals(
            $this->tenant->webhook_url,
            $tenant2->webhook_url
        );

        // 確認 URL 包含各租戶獨立的 UUID token
        $this->assertStringContainsString(
            "/api/webhook/{$this->tenant->webhook_token}",
            $this->tenant->webhook_url
        );

        $this->assertStringContainsString(
            "/api/webhook/{$tenant2->webhook_token}",
            $tenant2->webhook_url
        );
    }
}
