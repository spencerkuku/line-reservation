<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Setting;
use App\Services\LineBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendCheckInReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-in:send-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send check-in reminder to customers 30 minutes before reservation (disabled by default)';

    protected $lineBotService;

    public function __construct(LineBotService $lineBotService)
    {
        parent::__construct();
        $this->lineBotService = $lineBotService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 檢查功能是否啟用
        $enabled = Setting::where('key', 'check_in_reminder_enabled')->value('value');
        
        if ($enabled !== '1' && $enabled !== 'true') {
            $this->info('Check-in reminder is disabled');
            Log::info('Check-in reminder job skipped - feature disabled');
            return 0;
        }

        $this->info('Starting check-in reminder job...');

        // 取得30分鐘後的時間範圍（±2分鐘容錯）
        $targetTime = Carbon::now()->addMinutes(30);
        $startTime = $targetTime->copy()->subMinutes(2);
        $endTime = $targetTime->copy()->addMinutes(2);

        // 查詢符合條件的預約
        $reservations = Reservation::with(['customer', 'service'])
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in_status', 'pending')
            ->whereDate('reservation_date', $targetTime->toDateString())
            ->get()
            ->filter(function ($reservation) use ($startTime, $endTime) {
                $reservationDateTime = $reservation->getReservationDateTime();
                return $reservationDateTime->between($startTime, $endTime);
            });

        $sentCount = 0;
        $failedCount = 0;

        foreach ($reservations as $reservation) {
            try {
                $customer = $reservation->customer;
                
                if (!$customer || !$customer->line_user_id) {
                    $this->warn("Skipping reservation #{$reservation->id} - No LINE user ID");
                    continue;
                }

                // 檢查客戶是否被封鎖
                if ($customer->status === 'blocked') {
                    $this->warn("Skipping reservation #{$reservation->id} - Customer blocked");
                    continue;
                }

                // 發送提醒訊息
                $this->sendReminderMessage($reservation);
                $sentCount++;
                
                $this->info("Sent reminder for reservation #{$reservation->id}");
                
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("Failed to send reminder for reservation #{$reservation->id}: {$e->getMessage()}");
                Log::error('Check-in reminder failed', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Check-in reminder job completed. Sent: {$sentCount}, Failed: {$failedCount}");
        Log::info('Check-in reminder job completed', [
            'sent' => $sentCount,
            'failed' => $failedCount
        ]);

        return 0;
    }

    private function sendReminderMessage($reservation)
    {
        $customer = $reservation->customer;
        $service = $reservation->service;
        $dateTime = $reservation->getReservationDateTime();

        // 取得商家資訊（位置）
        $businessAddress = Setting::where('key', 'business_address')->value('value') ?? '請洽詢商家';

        $message = [
            'type' => 'flex',
            'altText' => '預約提醒 - 30分鐘後',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '⏰ 預約提醒',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'xl',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '您的預約即將開始',
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#F39C12',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'none',
                            'spacing' => 'md',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '服務項目',
                                            'size' => 'sm',
                                            'color' => '#666666',
                                            'flex' => 2
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $service->name,
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'weight' => 'bold',
                                            'flex' => 3,
                                            'wrap' => true
                                        ]
                                    ]
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '預約時間',
                                            'size' => 'sm',
                                            'color' => '#666666',
                                            'flex' => 2
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $dateTime->format('m月d日 H:i'),
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'weight' => 'bold',
                                            'flex' => 3
                                        ]
                                    ]
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '服務地點',
                                            'size' => 'sm',
                                            'color' => '#666666',
                                            'flex' => 2
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $businessAddress,
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'weight' => 'bold',
                                            'flex' => 3,
                                            'wrap' => true
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '請提前5-10分鐘到達現場報到。',
                            'size' => 'sm',
                            'color' => '#666666',
                            'margin' => 'xl',
                            'wrap' => true,
                            'align' => 'center'
                        ]
                    ],
                    'paddingAll' => 'xl'
                ]
            ]
        ];

        $this->lineBotService->pushMessage($customer->line_user_id, $message);
    }
}
