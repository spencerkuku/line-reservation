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
            'email' => 'admin@line-reservation.com',
            'password' => Hash::make('admin123'),
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
            'current_bookings' => 0,
            'is_active' => true,
        ]);

        $availableTime2 = AvailableTime::create([
            'title' => '下午時段',
            'description' => '下午 14:00-18:00 可預約時段',
            'start_time' => $tomorrow->copy()->setTime(14, 0),
            'end_time' => $tomorrow->copy()->setTime(18, 0),
            'max_capacity' => 2,
            'current_bookings' => 0,
            'is_active' => true,
        ]);

        // 創建客戶（兩筆）
        $customer1 = Customer::create([
            'line_user_id' => 'U1234567890abcdef',
            'name' => '張小明',
            'line_display_name' => '小明',
            'line_picture_url' => 'https://profile.line-scdn.net/0h1234567890abcdef_large',
            'line_status_message' => '今天也要加油！',
            'phone' => '0912345678',
            'email' => 'ming@example.com',
            'gender' => 'male',
            'birthday' => '1990-05-15',
            'address' => '台北市中正區中山南路1號',
            'notes' => '偏好短髮造型',
            'status' => 'active',
            'preferences' => json_encode([
                'preferred_time' => 'morning',
                'notification' => true
            ]),
            'last_interaction_at' => now()->subDays(2),
            'referral_source' => 'LINE',
            'total_reservations' => 0,
            'total_spent' => 0,
        ]);

        $customer2 = Customer::create([
            'line_user_id' => 'U0987654321fedcba',
            'name' => '李小華',
            'line_display_name' => 'Hua Lin',
            'line_picture_url' => 'https://profile.line-scdn.net/0h0987654321fedcba_large',
            'line_status_message' => '生活就是要美美的~',
            'phone' => '0987654321',
            'email' => 'hua@example.com',
            'gender' => 'female',
            'birthday' => '1985-08-20',
            'address' => '台北市信義區市府路2號',
            'notes' => '對化學藥劑過敏，請使用天然產品',
            'status' => 'active',
            'preferences' => json_encode([
                'preferred_time' => 'afternoon',
                'notification' => true,
                'allergies' => ['chemical']
            ]),
            'last_interaction_at' => now()->subDays(1),
            'referral_source' => 'friend',
            'total_reservations' => 0,
            'total_spent' => 0,
        ]);

        // 創建預約（兩筆）
        $reservation1 = Reservation::create([
            'customer_id' => $customer1->id,
            'service_id' => $service1->id,
            'available_time_id' => $availableTime1->id,
            'customer_name' => $customer1->name,
            'customer_phone' => $customer1->phone,
            'customer_notes' => '希望剪短一點',
            'reservation_date' => $today->toDateString(),
            'reservation_time' => '10:00:00',
            'status' => 'confirmed',
            'notes' => '客戶偏好短髮',
            'confirmed_at' => now()->subHours(2),
        ]);

        $reservation2 = Reservation::create([
            'customer_id' => $customer2->id,
            'service_id' => $service2->id,
            'available_time_id' => $availableTime2->id,
            'customer_name' => $customer2->name,
            'customer_phone' => $customer2->phone,
            'customer_notes' => '想要棕色調染髮',
            'reservation_date' => $tomorrow->toDateString(),
            'reservation_time' => '15:00:00',
            'status' => 'pending',
            'notes' => '客戶對化學藥劑過敏',
        ]);

        // 更新客戶的預約統計
        $customer1->update([
            'total_reservations' => 1,
            'total_spent' => $service1->price,
        ]);

        $customer2->update([
            'total_reservations' => 1,
            'total_spent' => 0, // 尚未確認，所以還沒計算費用
        ]);

        // 更新可預約時段的當前預約數
        $availableTime1->update(['current_bookings' => 1]);
        $availableTime2->update(['current_bookings' => 1]);

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

        $this->command->info('資料庫初始化完成！');
        $this->command->info('管理員帳號：admin@line-reservation.com / admin123');
        $this->command->info('客戶：2 筆');
        $this->command->info('服務項目：2 筆');
        $this->command->info('可預約時段：2 筆');
        $this->command->info('預約記錄：2 筆');
        $this->command->info('系統設定：4 筆');
    }
}
