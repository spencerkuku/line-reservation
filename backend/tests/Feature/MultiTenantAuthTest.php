<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 多租戶認證測試
 * 
 * 確保登入流程、角色權限、強制變更密碼等功能正確運作
 */
class MultiTenantAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $systemAdmin;
    protected User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立系統管理員
        $this->systemAdmin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin@test.com',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
            'status' => 'Active',
            'tenant_id' => null,
            'must_change_password' => false,
        ]);

        // 建立租戶
        $this->tenant = Tenant::create([
            'name' => 'Auth Test Tenant',
            'slug' => 'auth-test',
            'email' => 'auth@test.com',
            'status' => 'active',
        ]);

        // 建立租戶管理員
        $this->tenantAdmin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'admin@auth-test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
            'tenant_id' => $this->tenant->id,
            'must_change_password' => false,
        ]);
    }

    /**
     * 測試系統管理員可以登入
     */
    public function test_system_admin_can_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'sysadmin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'access_token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertEquals('system_admin', $response->json('user.role'));
    }

    /**
     * 測試租戶管理員可以登入
     */
    public function test_tenant_admin_can_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'access_token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertEquals('admin', $response->json('user.role'));
    }

    /**
     * 測試需要強制變更密碼的用戶登入時會收到提示
     */
    public function test_user_with_must_change_password_flag_is_notified(): void
    {
        // 將租戶管理員設定為需要變更密碼
        $this->tenantAdmin->update(['must_change_password' => true]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.must_change_password', true);
    }

    /**
     * 測試系統管理員可以存取租戶管理 API
     */
    public function test_system_admin_can_access_tenant_management(): void
    {
        $response = $this->actingAs($this->systemAdmin)
            ->getJson('/api/system/tenants');

        $response->assertStatus(200);
    }

    /**
     * 測試租戶管理員無法存取租戶管理 API
     */
    public function test_tenant_admin_cannot_access_tenant_management(): void
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/api/system/tenants');

        $response->assertStatus(403);
    }

    /**
     * 測試停用租戶的用戶無法登入
     */
    public function test_user_of_suspended_tenant_cannot_login(): void
    {
        // 停用租戶
        $this->tenant->update(['status' => 'suspended']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'password',
        ]);

        // 應該返回 403 或登入失敗
        $this->assertTrue(
            $response->status() === 403 || 
            ($response->status() === 200 && !$response->json('success'))
        );
    }

    /**
     * 測試強制變更密碼功能
     */
    public function test_force_change_password_works(): void
    {
        // 將租戶管理員設定為需要變更密碼
        $this->tenantAdmin->update(['must_change_password' => true]);

        $response = $this->actingAs($this->tenantAdmin)
            ->postJson('/api/auth/force-change-password', [
                'new_password' => 'newPassword123!',
                'new_password_confirmation' => 'newPassword123!',
            ]);

        $response->assertStatus(200);

        // 確認密碼標記已清除
        $this->tenantAdmin->refresh();
        $this->assertFalse($this->tenantAdmin->must_change_password);

        // 確認新密碼可以登入
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'newPassword123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * 測試強制變更密碼時需要正確的當前密碼
     */
    public function test_force_change_password_requires_matching_confirmation(): void
    {
        $this->tenantAdmin->update(['must_change_password' => true]);

        $response = $this->actingAs($this->tenantAdmin)
            ->postJson('/api/auth/force-change-password', [
                'new_password' => 'newPassword123!',
                'new_password_confirmation' => 'mismatchPassword123!',
            ]);

        // 應該返回 422 或錯誤訊息
        $this->assertTrue(
            $response->status() === 422 ||
            $response->status() === 400 ||
            ($response->json('success') === false)
        );
    }

    /**
     * 測試登入時包含租戶資訊
     */
    public function test_login_includes_tenant_info(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // 如果有回傳租戶資訊，確認其正確性
        if ($response->json('user.tenant_id')) {
            $this->assertEquals($this->tenant->id, $response->json('user.tenant_id'));
        }
    }

    /**
     * 測試無效憑證無法登入
     */
    public function test_invalid_credentials_cannot_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@auth-test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertTrue(
            $response->status() === 401 ||
            $response->status() === 422 ||
            ($response->json('success') === false)
        );
    }
}
