<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 多租戶資料隔離測試
 * 
 * 確保租戶 A 的資料不會被租戶 B 看到
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminA;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立租戶 A
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'email' => 'a@tenant.com',
            'status' => 'active',
        ]);

        // 建立租戶 B
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'email' => 'b@tenant.com',
            'status' => 'active',
        ]);

        // 建立租戶 A 的管理員
        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin@tenant-a.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
            'tenant_id' => $this->tenantA->id,
        ]);

        // 建立租戶 B 的管理員
        $this->adminB = User::create([
            'name' => 'Admin B',
            'email' => 'admin@tenant-b.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * 測試客戶資料隔離
     * 租戶 A 不應能看到租戶 B 的客戶
     */
    public function test_customers_are_isolated_between_tenants(): void
    {
        // 設定當前租戶為 A
        app()->instance('currentTenant', $this->tenantA);

        // 建立租戶 A 的客戶
        $customerA = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Customer A',
            'line_user_id' => 'U_customer_a_' . uniqid(),
        ]);

        // 建立租戶 B 的客戶（繞過 scope）
        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Customer B',
            'line_user_id' => 'U_customer_b_' . uniqid(),
        ]);

        // 在租戶 A 的上下文中查詢客戶
        $customers = Customer::all();

        // 應只能看到租戶 A 的客戶
        $this->assertCount(1, $customers);
        $this->assertEquals('Customer A', $customers->first()->name);
    }

    /**
     * 測試服務項目資料隔離
     * 租戶 A 不應能看到租戶 B 的服務
     */
    public function test_services_are_isolated_between_tenants(): void
    {
        // 設定當前租戶為 A
        app()->instance('currentTenant', $this->tenantA);

        // 建立租戶 A 的服務
        Service::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Service A',
            'description' => 'Service for Tenant A',
            'duration' => 60,
            'price' => 100,
        ]);

        // 建立租戶 B 的服務（繞過 scope）
        Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Service B',
            'description' => 'Service for Tenant B',
            'duration' => 60,
            'price' => 200,
        ]);

        // 在租戶 A 的上下文中查詢服務
        $services = Service::all();

        // 應只能看到租戶 A 的服務
        $this->assertCount(1, $services);
        $this->assertEquals('Service A', $services->first()->name);
    }

    /**
     * 測試自動填入 tenant_id
     * 在租戶上下文中建立資料時應自動填入 tenant_id
     */
    public function test_tenant_id_is_auto_filled_when_creating_records(): void
    {
        // 設定當前租戶為 A
        app()->instance('currentTenant', $this->tenantA);

        // 建立客戶（不指定 tenant_id）
        $customer = Customer::create([
            'name' => 'Auto Tenant Customer',
            'line_user_id' => 'U_auto_' . uniqid(),
        ]);

        // 確認自動填入了正確的 tenant_id
        $this->assertEquals($this->tenantA->id, $customer->tenant_id);
    }

    /**
     * 測試 API 資料隔離
     * 透過 API 存取時，用戶只能看到自己租戶的資料
     */
    public function test_api_returns_only_tenant_data(): void
    {
        // 建立租戶 A 的客戶
        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Customer A',
            'line_user_id' => 'U_api_customer_a_' . uniqid(),
        ]);

        // 建立租戶 B 的客戶
        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Customer B',
            'line_user_id' => 'U_api_customer_b_' . uniqid(),
        ]);

        // 以租戶 A 管理員身份登入並請求客戶列表
        $response = $this->actingAs($this->adminA)
            ->getJson('/api/customers');

        $response->assertStatus(200);
        
        // 確認回傳的資料只包含租戶 A 的客戶
        $customers = $response->json('data') ?? $response->json();
        $customerNames = collect($customers)->pluck('name')->toArray();
        
        $this->assertContains('Customer A', $customerNames);
        $this->assertNotContains('Customer B', $customerNames);
    }
}
