<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\AvailableTime;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class MultiTenantSeeder extends Seeder
{
    /**
     * Seed the application's database with multi-tenant data.
     */
    public function run(): void
    {
        // 1. 創建系統管理員（無 tenant_id）
        $systemAdmin = User::firstOrCreate(
            ['email' => 'sysadmin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('sysadmin123'),
                'role' => 'system_admin',
                'status' => 'Active',
                'email_verified_at' => now(),
                'tenant_id' => null,
                'must_change_password' => false,
            ]
        );
        
        // 確保現有用戶也被更新為系統管理員
        $systemAdmin->update(['role' => 'system_admin']);

        // 2. 創建示範租戶
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-salon'],
            [
                'name' => '示範美髮沙龍',
                'email' => 'contact@demo-salon.example.com',
                'phone' => '02-1234-5678',
                'address' => '台北市中山區示範路 123 號',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(30),
                'subscription_ends_at' => null,
            ]
        );

        // 3. 創建租戶管理員（需強制變更密碼）
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'admin@demo-salon.example.com'],
            [
                'name' => '租戶管理員',
                'password' => Hash::make('tenant123'),
                'role' => 'admin',
                'status' => 'Active',
                'email_verified_at' => now(),
                'tenant_id' => $tenant->id,
                'must_change_password' => true, // 首次登入需變更密碼
            ]
        );

        // 設置當前租戶上下文
        app()->instance('current_tenant', $tenant);

        // 4. 創建服務項目
        $service1 = Service::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => '剪髮服務'],
            [
                'description' => '專業剪髮造型服務，包含洗髮、剪髮、造型',
                'duration' => 60,
                'price' => 350.00,
                'image_url' => null,
                'is_active' => true,
            ]
        );

        $service2 = Service::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => '染髮服務'],
            [
                'description' => '專業染髮服務，包含諮詢、染髮、護髮',
                'duration' => 120,
                'price' => 1200.00,
                'image_url' => null,
                'is_active' => true,
            ]
        );

        // 5. 創建可預約時段
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $availableTime1 = AvailableTime::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '上午時段'],
            [
                'description' => '上午 9:00-12:00 可預約時段',
                'start_time' => $today->copy()->setTime(9, 0),
                'end_time' => $today->copy()->setTime(12, 0),
                'max_capacity' => 3,
                'is_active' => true,
            ]
        );

        $availableTime2 = AvailableTime::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '下午時段'],
            [
                'description' => '下午 14:00-18:00 可預約時段',
                'start_time' => $tomorrow->copy()->setTime(14, 0),
                'end_time' => $tomorrow->copy()->setTime(18, 0),
                'max_capacity' => 2,
                'is_active' => true,
            ]
        );

        // 6. 創建系統設定
        Setting::firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'business_hours'],
            [
                'value' => json_encode([
                    'monday' => ['09:00-18:00'],
                    'tuesday' => ['09:00-18:00'],
                    'wednesday' => ['09:00-18:00'],
                    'thursday' => ['09:00-18:00'],
                    'friday' => ['09:00-18:00'],
                    'saturday' => ['09:00-17:00'],
                    'sunday' => ['closed']
                ]),
                'type' => 'json',
            ]
        );

        Setting::firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'reservation_settings'],
            [
                'value' => json_encode([
                    'advance_booking_days' => 30,
                    'cancellation_hours' => 24,
                    'max_reservations_per_day' => 10
                ]),
                'type' => 'json',
            ]
        );

        Setting::firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'reservation_confirm_mode'],
            [
                'value' => 'auto',
                'type' => 'string',
            ]
        );

        // 7. 創建示範客戶
        $customer = Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'line_user_id' => 'U0000000000000000000000000000demo'],
            [
                'name' => '示範客戶',
                'line_display_name' => '示範客戶',
                'phone' => '0912-345-678',
                'notes' => '這是一個示範客戶帳號',
            ]
        );

        // 8. 創建示範預約（只有在沒有該租戶預約時才建立）
        if (!Reservation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            Reservation::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'service_id' => $service1->id,
                'available_time_id' => $availableTime1->id,
                'reservation_date' => $today->toDateString(),
                'reservation_time' => '10:00:00',
                'status' => 'confirmed',
                'notes' => '示範預約',
            ]);
        }

        // 清除租戶上下文
        app()->forgetInstance('current_tenant');

        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════╗');
        $this->command->info('║        多租戶資料庫初始化完成！                              ║');
        $this->command->info('╠════════════════════════════════════════════════════════════╣');
        $this->command->info('║  系統管理員帳號                                             ║');
        $this->command->info('║  Email: sysadmin@example.com                              ║');
        $this->command->info('║  Password: sysadmin123                                     ║');
        $this->command->info('╠════════════════════════════════════════════════════════════╣');
        $this->command->info('║  示範租戶管理員帳號（首次登入需變更密碼）                    ║');
        $this->command->info('║  Email: admin@demo-salon.example.com                      ║');
        $this->command->info('║  Password: tenant123                                       ║');
        $this->command->info('╠════════════════════════════════════════════════════════════╣');
        $this->command->info('║  示範租戶：' . $tenant->name . '                              ║');
        $this->command->info('║  Webhook URL: ' . $tenant->webhook_url . '  ║');
        $this->command->info('╚════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
