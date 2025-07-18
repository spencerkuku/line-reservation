<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Service;
use App\Models\Customer;
use App\Models\AvailableTime;
use App\Models\Reservation;
use App\Models\Setting;
use Carbon\Carbon;

class DemoReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 創建系統設定
        $this->createSettings();
        
        // 創建管理員用戶
        $admin = $this->createAdmin();
        
        // 創建服務項目
        $services = $this->createServices();
        
        // 創建 LINE 客戶
        $customers = $this->createCustomers();
        
        // 創建可預約時段
        $availableTimes = $this->createAvailableTimes();
        
        // 創建示例預約記錄
        $this->createReservations($services, $customers, $availableTimes);
        
        $this->command->info('Demo data created successfully!');
    }
    
    private function createSettings()
    {
        $settings = [
            'line_channel_access_token' => 'YOUR_CHANNEL_ACCESS_TOKEN_HERE',
            'line_channel_secret' => 'YOUR_CHANNEL_SECRET_HERE',
            'app_name' => '預約系統',
            'business_hours_start' => '09:00',
            'business_hours_end' => '18:00',
            'advance_booking_days' => '30',
            'booking_notice' => '請提前15分鐘到達，如需取消請至少提前2小時告知。'
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }
        
        $this->command->info('Settings created.');
    }
    
    private function createAdmin()
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '系統管理員',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
        
        $this->command->info('Admin user created: admin@example.com / password');
        return $admin;
    }
    
    private function createServices()
    {
        $services = [
            [
                'name' => '髮型設計',
                'description' => '專業髮型設計服務，包含洗髮、剪髮、造型',
                'duration' => 90,
                'price' => 1200,
                'is_active' => true,
            ],
            [
                'name' => '護髮療程',
                'description' => '深層護髮療程，修復受損髮質',
                'duration' => 60,
                'price' => 800,
                'is_active' => true,
            ],
            [
                'name' => '染髮服務',
                'description' => '專業染髮服務，多種顏色選擇',
                'duration' => 120,
                'price' => 2500,
                'is_active' => true,
            ],
            [
                'name' => '燙髮造型',
                'description' => '各式燙髮造型，打造完美捲度',
                'duration' => 150,
                'price' => 3000,
                'is_active' => true,
            ],
            [
                'name' => '頭皮護理',
                'description' => '專業頭皮護理，改善頭皮健康',
                'duration' => 45,
                'price' => 600,
                'is_active' => true,
            ]
        ];

        $createdServices = [];
        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
            $createdServices[] = $service;
        }
        
        $this->command->info('Services created: ' . count($createdServices));
        return $createdServices;
    }
    
    private function createCustomers()
    {
        $customers = [
            [
                'line_user_id' => 'U1234567890abcdef1234567890abcdef',
                'name' => '小美',
                'phone' => '0912345678',
                'email' => 'user1@example.com',
            ],
            [
                'line_user_id' => 'U2345678901bcdef12345678901bcdef1',
                'name' => '小王',
                'phone' => '0923456789',
                'email' => 'user2@example.com',
            ],
            [
                'line_user_id' => 'U3456789012cdef123456789012cdef12',
                'name' => '小李',
                'phone' => '0934567890',
                'email' => 'user3@example.com',
            ],
            [
                'line_user_id' => 'U4567890123def1234567890123def123',
                'name' => '小陳',
                'phone' => '0945678901',
                'email' => 'user4@example.com',
            ]
        ];

        $createdCustomers = [];
        foreach ($customers as $customerData) {
            $customer = Customer::updateOrCreate(
                ['line_user_id' => $customerData['line_user_id']],
                $customerData
            );
            $createdCustomers[] = $customer;
        }
        
        $this->command->info('Customers created: ' . count($createdCustomers));
        return $createdCustomers;
    }
    
    private function createAvailableTimes()
    {
        $availableTimes = [];
        $startDate = Carbon::today()->addDays(1);
        
        // 創建未來 14 天的可預約時段
        for ($i = 0; $i < 14; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // 跳過週日
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }
            
            // 每天創建時段：09:00, 10:30, 14:00, 15:30, 17:00
            $times = [
                ['09:00', '10:30'],
                ['10:30', '12:00'],
                ['14:00', '15:30'],
                ['15:30', '17:00'],
                ['17:00', '18:30']
            ];
            
            foreach ($times as $timeSlot) {
                $startTime = $date->copy()->setTimeFromTimeString($timeSlot[0]);
                $endTime = $date->copy()->setTimeFromTimeString($timeSlot[1]);
                
                $availableTime = AvailableTime::create([
                    'title' => '可預約時段',
                    'description' => '一般預約時段',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'max_capacity' => 1,
                    'current_bookings' => 0,
                    'is_active' => true,
                ]);
                
                $availableTimes[] = $availableTime;
            }
        }
        
        $this->command->info('Available times created: ' . count($availableTimes));
        return $availableTimes;
    }
    
    private function createReservations($services, $customers, $availableTimes)
    {
        $reservations = [];
        
        // 創建一些示例預約
        $reservationData = [
            [
                'service' => $services[0], // 髮型設計
                'customer' => $customers[0], // 小美
                'time' => $availableTimes[0],
                'status' => 'confirmed',
                'notes' => '希望剪短一點，層次感要明顯',
            ],
            [
                'service' => $services[1], // 護髮療程
                'customer' => $customers[1], // 小王
                'time' => $availableTimes[5],
                'status' => 'confirmed',
                'notes' => '頭髮比較乾燥，需要深層滋潤',
            ],
            [
                'service' => $services[2], // 染髮服務
                'customer' => $customers[2], // 小李
                'time' => $availableTimes[10],
                'status' => 'pending',
                'notes' => '想染棕色系，不要太深',
            ],
            [
                'service' => $services[0], // 髮型設計
                'customer' => $customers[3], // 小陳
                'time' => $availableTimes[15],
                'status' => 'confirmed',
                'notes' => '第一次來，想要清爽的造型',
            ],
            [
                'service' => $services[3], // 燙髮造型
                'customer' => $customers[0], // 小美
                'time' => $availableTimes[20],
                'status' => 'cancelled',
                'notes' => '想要微捲的感覺，不要太捲',
            ]
        ];

        foreach ($reservationData as $data) {
            $reservation = Reservation::create([
                'user_id' => 1, // 使用管理員 ID
                'service_id' => $data['service']->id,
                'customer_id' => $data['customer']->id,
                'available_time_id' => $data['time']->id,
                'reservation_date' => $data['time']->start_time->format('Y-m-d'),
                'reservation_time' => $data['time']->start_time->format('H:i:s'),
                'status' => $data['status'],
                'notes' => $data['notes'],
            ]);
            
            // 如果預約已確認或取消，則增加預約數量
            if (in_array($data['status'], ['confirmed', 'cancelled'])) {
                $data['time']->increment('current_bookings');
            }
            
            $reservations[] = $reservation;
        }
        
        $this->command->info('Reservations created: ' . count($reservations));
        return $reservations;
    }
}
