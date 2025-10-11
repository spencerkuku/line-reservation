<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\AvailableTime;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 創建管理員用戶
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        // 創建服務項目（兩筆）
        $service1 = Service::create([
            'name' => '剪髮服務',
            'description' => '專業剪髮造型服務，包含洗髮、剪髮、造型',
            'duration' => 60, // 60分鐘
            'price' => 350.00,
            'image_url' => null,
            'is_active' => true,
        ]);

        $service2 = Service::create([
            'name' => '染髮服務',
            'description' => '專業染髮服務，包含諮詢、染髮、護髮',
            'duration' => 120, // 120分鐘
            'price' => 1200.00,
            'image_url' => null,
            'is_active' => true,
        ]);

        // 創建可預約時段（兩筆）
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $availableTime1 = AvailableTime::create([
            'title' => '上午時段',
            'description' => '上午 9:00-12:00 可預約時段',
            'start_time' => $today->copy()->setTime(9, 0),
            'end_time' => $today->copy()->setTime(12, 0),
            'max_capacity' => 3,
            'is_active' => true,
        ]);

        $availableTime2 = AvailableTime::create([
            'title' => '下午時段',
            'description' => '下午 14:00-18:00 可預約時段',
            'start_time' => $tomorrow->copy()->setTime(14, 0),
            'end_time' => $tomorrow->copy()->setTime(18, 0),
            'max_capacity' => 2,
            'is_active' => true,
        ]);

        // 創建系統設定（兩筆）
        Setting::create([
            'key' => 'line_channel_access_token',
            'value' => '',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'line_channel_secret',
            'value' => '',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'business_hours',
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
        ]);

        Setting::create([
            'key' => 'reservation_settings',
            'value' => json_encode([
                'advance_booking_days' => 30,
                'cancellation_hours' => 24,
                'max_reservations_per_day' => 10
            ]),
            'type' => 'json',
        ]);

        Setting::create([
            'key' => 'check_in_reminder_enabled',
            'value' => '0',
            'type' => 'boolean',
        ]);

        Setting::create([
            'key' => 'business_address',
            'value' => '',
            'type' => 'string',
        ]);

        $this->command->info('資料庫初始化完成！');
        $this->command->info('管理員帳號：admin@example.com / password');
        $this->command->info('客戶：2 筆');
        $this->command->info('服務項目：2 筆');
        $this->command->info('可預約時段：2 筆');
        $this->command->info('預約記錄：2 筆');
        $this->command->info('系統設定：6 筆');
    }
}
