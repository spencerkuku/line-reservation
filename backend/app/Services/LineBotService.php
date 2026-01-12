<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\User;
use App\Models\Service;
use App\Models\Reservation;
use App\Models\AvailableTime;
use App\Models\LineMessageLog;
use App\Services\LoggingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LineBotService
{
    private $channelAccessToken;
    private $channelSecret;
    private $tenant;
    private $apiUrl = 'https://api.line.me/v2/bot/message/reply';

    /**
     * 建構函式
     * 
     * @param Tenant|null $tenant 租戶實例（多租戶模式）
     */
    public function __construct(?Tenant $tenant = null)
    {
        try {
            Log::info('LineBotService constructor called', [
                'tenant_id' => $tenant?->id,
                'tenant_name' => $tenant?->name,
            ]);
            
            $this->tenant = $tenant;
            
            if ($tenant) {
                // 多租戶模式：使用租戶的 LINE 憑證（從 settings 表取得並解密）
                app()->instance('currentTenant', $tenant);
                
                $this->channelAccessToken = $this->getDecryptedSetting($tenant->id, 'line_channel_access_token');
                $this->channelSecret = $this->getDecryptedSetting($tenant->id, 'line_channel_secret');
            } else {
                // 向後兼容：使用全局設定
                $this->channelAccessToken = $this->getDecryptedSetting(null, 'line_channel_access_token') 
                    ?? config('linebot.channel_access_token');
                    
                $this->channelSecret = $this->getDecryptedSetting(null, 'line_channel_secret')
                    ?? config('linebot.channel_secret');
            }
            
            Log::info('LineBotService configuration loaded', [
                'has_access_token' => !empty($this->channelAccessToken),
                'has_secret' => !empty($this->channelSecret),
                'tenant_id' => $tenant?->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in LineBotService constructor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 使用預設值以防止服務完全失效
            $this->channelAccessToken = config('linebot.channel_access_token');
            $this->channelSecret = config('linebot.channel_secret');
        }
    }

    /**
     * 獲取當前租戶
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * 從 settings 表取得解密後的設定值
     */
    private function getDecryptedSetting(?int $tenantId, string $key): ?string
    {
        $query = Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('key', $key);
            
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        
        $setting = $query->first();
        
        if (!$setting || !$setting->value) {
            return null;
        }
        
        // 嘗試解密（LINE 憑證應該是加密存儲的）
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($setting->value);
        } catch (\Exception $e) {
            // 如果解密失敗，返回原值（可能是未加密的舊數據）
            return $setting->value;
        }
    }

    /**
     * 獲取預約確認模式
     */
    private function getReservationConfirmMode()
    {
        if ($this->tenant) {
            return $this->tenant->getSetting('reservation_confirm_mode', 'auto');
        }
        return Setting::get('reservation_confirm_mode', 'auto');
    }

    public function handleWebhook($events)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logLineBotEvent('webhook_received', 'system', ['event_count' => count($events)], $requestId);
        
        Log::info('LineBotService: handleWebhook called with ' . count($events) . ' events');
        
        if (!$this->channelAccessToken) {
            LoggingService::logLineBotError('webhook_config_error', 'system', 
                new \Exception('LINE Bot not configured - missing access token'), 
                ['events_count' => count($events)], $requestId);
            Log::error('LINE Bot not configured - missing access token');
            return;
        }

        foreach ($events as $event) {
            $userId = $event['source']['userId'] ?? 'unknown';
            LoggingService::logLineBotEvent('event_processing', $userId, [
                'event_type' => $event['type'],
                'event_data' => $event
            ], $requestId);
            
            Log::info('Processing event: ' . json_encode($event));
            $this->logMessage($event);

            try {
                switch ($event['type']) {
                    case 'message':
                        $this->handleMessage($event);
                        break;
                    case 'postback':
                        $this->handlePostback($event);
                        break;
                    case 'follow':
                        $this->handleFollow($event);
                        break;
                    case 'unfollow':
                        $this->handleUnfollow($event);
                        break;
                    default:
                        LoggingService::logLineBotEvent('unhandled_event', $userId, [
                            'event_type' => $event['type'],
                            'event' => $event
                        ], $requestId);
                        Log::info('Unhandled event type: ' . $event['type']);
                }
            } catch (\Exception $e) {
                LoggingService::logLineBotError('event_processing_error', $userId, $e, [
                    'event_type' => $event['type'],
                    'event' => $event
                ], $requestId);
                Log::error('Error processing event', [
                    'error' => $e->getMessage(),
                    'event' => $event
                ]);
            }
        }
    }

    private function handleMessage($event)
    {
        $message = $event['message'];
        $userId = $event['source']['userId'];
        $replyToken = $event['replyToken'];

        LoggingService::logLineBotEvent('message_received', $userId, [
            'message_type' => $message['type'],
            'text' => $message['text'] ?? null,
            'reply_token' => $replyToken
        ]);

        Log::info('Handling message', [
            'userId' => $userId,
            'replyToken' => $replyToken,
            'messageType' => $message['type'],
            'text' => $message['text'] ?? 'N/A'
        ]);

        // 確保客戶資料存在
        $customer = $this->getOrCreateCustomer($userId, $event);
        
        // 如果無法建立或取得客戶，發送錯誤訊息
        if (!$customer) {
            LoggingService::logLineBotError('customer_creation_failed', $userId, 
                new \Exception('Unable to create or retrieve customer'), 
                ['event' => $event]);
                
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '系統忙碌中，請稍後再試。'
            ]);
            return;
        }

        // 檢查客戶是否被封鎖
        if ($customer->status === 'blocked') {
            LoggingService::logLineBotEvent('blocked_customer_attempt', $userId, [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'message_text' => $message['text'] ?? null
            ]);
            
            Log::warning('Blocked customer attempted to use bot', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'line_user_id' => $userId
            ]);
            
            // 發送帳號停用的 Flex Message
            $this->sendBlockedAccountMessage($replyToken);
            return;
        }

        if ($message['type'] === 'text') {
            $text = trim($message['text']);
            LoggingService::logLineBotEvent('text_message_processing', $userId, [
                'text' => $text,
                'customer_id' => $customer->id ?? null
            ]);
            Log::info('Processing text message: ' . $text);

            // 只回覆關鍵字
            // 注意：需要先檢查更具體的關鍵字（如「取消預約」）再檢查通用關鍵字（如「取消」）
            if ($this->isCancelReservationKeyword($text)) {
                Log::info('Cancel reservation keyword detected, showing cancellable reservations');
                $this->sendCancelReservationQuery($replyToken, $userId);
            } elseif ($this->isQueryReservationKeyword($text)) {
                Log::info('Query reservation keyword detected, showing reservations');
                $this->sendReservationQuery($replyToken, $userId);
            } elseif ($this->isCancelKeyword($text)) {
                LoggingService::logLineBotEvent('cancel_command', $userId, ['text' => $text]);
                Log::info('Cancel keyword detected, clearing reservation context');
                $this->handleCancelCommand($replyToken, $userId);
            } elseif ($this->isCustomerInfoMessage($text, $replyToken, $userId)) {
                LoggingService::logLineBotEvent('customer_info_processing', $userId, [
                    'text' => $text,
                    'customer_id' => $customer->id ?? null
                ]);
                Log::info('Customer info message detected, processing reservation', [
                    'text' => $text,
                    'replyToken' => $replyToken,
                    'userId' => $userId
                ]);
                $this->processCustomerInfo($text, $replyToken, $userId);
            } elseif ($this->isReservationKeyword($text)) {
                Log::info('Reservation keyword detected, sending service selection');
                $this->sendServiceSelection($replyToken);
            } else {
                Log::info('Message not a keyword, no reply sent', [
                    'text' => $text,
                    'replyToken' => $replyToken
                ]);
                // 不回覆
                return;
            }
        } else {
            Log::info('Non-text message, no reply sent');
            // 不回覆
            return;
        }
    }

    private function handlePostback($event)
    {
        $postbackData = $event['postback']['data'];
        $userId = $event['source']['userId'];
        $replyToken = $event['replyToken'];
        
        // 檢查客戶是否被封鎖
        $customer = Customer::where('line_user_id', $userId)->first();
        if ($customer && $customer->status === 'blocked') {
            LoggingService::logLineBotEvent('blocked_customer_postback_attempt', $userId, [
                'customer_id' => $customer->id,
                'postback_data' => $postbackData
            ]);
            
            Log::warning('Blocked customer attempted postback action', [
                'customer_id' => $customer->id,
                'line_user_id' => $userId,
                'action' => $postbackData
            ]);
            
            $this->sendBlockedAccountMessage($replyToken);
            return;
        }
        
        // 解析 postback 資料
        parse_str($postbackData, $data);
        
        switch ($data['action']) {
            case 'select_service':
                $this->handleServiceSelection($replyToken, $data['service_id']);
                break;
            case 'select_date':
                $this->handleDateSelection($replyToken, $data['service_id'], $data['date']);
                break;
            case 'select_time':
                $this->handleTimeSelection($replyToken, $data['service_id'], $data['time_id'], $userId);
                break;
            case 'start_info_collection':
                $this->startInfoCollection($replyToken, $data['service_id'], $data['time_id'], $userId);
                break;
            case 'confirm_reservation':
                $this->handleReservationConfirmation($replyToken, $data, $userId);
                break;
            case 'confirm_final_reservation':
                $this->handleFinalReservationConfirmation($replyToken, $data['reservation_id']);
                break;
            case 'restart_info_collection':
                $this->handleRestartInfoCollection($replyToken, $data, $userId);
                break;
            case 'edit_reservation':
                $this->handleEditReservation($replyToken, $data['reservation_id'], $userId);
                break;
            case 'edit_service':
                $this->handleEditService($replyToken, $data['reservation_id'], $userId);
                break;
            case 'edit_time':
                $this->handleEditTime($replyToken, $data['reservation_id'], $data['service_id'], $userId);
                break;
            case 'edit_date_selected':
                $this->handleEditTimeSelection($replyToken, $data['reservation_id'], $data['service_id'], $data['selected_date'], $userId);
                break;
            case 'update_service':
                $this->handleUpdateService($replyToken, $data['reservation_id'], $data['new_service_id'], $userId);
                break;
            case 'update_time':
                $this->handleUpdateTime($replyToken, $data['reservation_id'], $data['new_time_id'], $userId);
                break;
            case 'cancel_reservation':
                if (isset($data['reservation_id'])) {
                    $this->handleCancelReservation($replyToken, $data['reservation_id'], $userId);
                } else {
                    $this->sendServiceSelection($replyToken);
                }
                break;
            case 'confirm_cancel':
                $this->handleConfirmCancel($replyToken, $data['reservation_id'], $userId);
                break;
        }
    }

    private function handleFollow($event)
    {
        $userId = $event['source']['userId'];
        $replyToken = $event['replyToken'];
        
        // 創建新客戶
        $this->getOrCreateCustomer($userId, $event);
        
        $this->sendWelcomeMessage($replyToken);
    }

    private function handleUnfollow($event)
    {
        $userId = $event['source']['userId'];
        
        // 可以選擇將客戶標記為 inactive 而不是刪除
        $customer = Customer::where('line_user_id', $userId)->first();
        if ($customer) {
            $customer->update(['status' => 'inactive']);
        }
        
        Log::info('User unfollowed: ' . $userId);
    }

    private function isReservationKeyword($text)
    {
        // 要求使用 / 前綴來觸發命令，避免與聊天內容衝突
        $keywords = ['/預約', '/我要預約'];
        // 向後兼容：如果文字是「預約」或「我要預約」且沒有其他文字
        $simpleKeywords = ['預約', '我要預約'];
        
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        
        // 只有在完全匹配簡單關鍵字時才觸發（向後兼容）
        foreach ($simpleKeywords as $keyword) {
            if ($text === $keyword) {
                return true;
            }
        }
        
        return false;
    }

    private function isQueryReservationKeyword($text)
    {
        // 要求使用 / 前綴來觸發命令，避免與聊天內容衝突
        $keywords = ['/查詢預約', '/我的預約', '/預約查詢', '/預約記錄', '/預約紀錄', '/預約情況'];
        // 向後兼容：如果文字是關鍵字且沒有其他文字
        $simpleKeywords = ['查詢預約', '我的預約', '預約查詢', '預約記錄', '預約紀錄', '預約情況'];
        
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        
        // 只有在完全匹配簡單關鍵字時才觸發（向後兼容）
        foreach ($simpleKeywords as $keyword) {
            if ($text === $keyword) {
                return true;
            }
        }
        
        return false;
    }

    private function isCancelReservationKeyword($text)
    {
        // 要求使用 / 前綴來觸發命令，避免與聊天內容衝突
        $keywords = ['/取消預約', '/我要取消', '/預約取消', '/取消訂單'];
        // 向後兼容：如果文字是關鍵字且沒有其他文字
        $simpleKeywords = ['取消預約', '我要取消', '預約取消', '取消訂單'];
        
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        
        // 只有在完全匹配簡單關鍵字時才觸發（向後兼容）
        foreach ($simpleKeywords as $keyword) {
            if ($text === $keyword) {
                return true;
            }
        }
        
        return false;
    }

    private function isCancelKeyword($text)
    {
        $keywords = ['重新開始', '停止', '中止', '退出', '結束'];
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function sendBlockedAccountMessage($replyToken)
    {
        $message = [
            'type' => 'flex',
            'altText' => '帳號已停用',
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '⛔ 帳號已停用',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'xl',
                            'align' => 'center'
                        ]
                    ],
                    'backgroundColor' => '#E74C3C',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '很抱歉',
                                    'size' => 'lg',
                                    'weight' => 'bold',
                                    'color' => '#333333',
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '您的帳號已被停用',
                                    'size' => 'md',
                                    'color' => '#666666',
                                    'align' => 'center',
                                    'margin' => 'md'
                                ],
                                [
                                    'type' => 'separator',
                                    'margin' => 'xl'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '目前無法使用預約服務',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'align' => 'center',
                                    'margin' => 'xl',
                                    'wrap' => true
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '如有任何疑問，請聯繫客服人員',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'align' => 'center',
                                    'margin' => 'md',
                                    'wrap' => true
                                ]
                            ],
                            'spacing' => 'sm',
                            'margin' => 'lg'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '📞',
                                            'size' => 'sm',
                                            'flex' => 0
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => '請透過其他管道聯繫我們',
                                            'size' => 'xs',
                                            'color' => '#666666',
                                            'flex' => 1,
                                            'margin' => 'md'
                                        ]
                                    ]
                                ]
                            ],
                            'backgroundColor' => '#F8F9FA',
                            'cornerRadius' => 'md',
                            'paddingAll' => 'md',
                            'margin' => 'xl'
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'styles' => [
                    'footer' => [
                        'separator' => true
                    ]
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function handleCancelCommand($replyToken, $userId)
    {
        // 清除所有進行中的預約上下文
        $allKeys = Cache::get('active_reservation_keys', []);
        $clearedCount = 0;
        
        foreach ($allKeys as $key) {
            $context = Cache::get($key);
            if ($context) {
                // 標記為已取消而不是直接刪除，避免殘留問題
                $context['cancelled'] = true;
                $context['cancelled_at'] = now();
                Cache::put($key, $context, now()->addMinutes(5)); // 短時間保留已取消狀態
                $clearedCount++;
                
                // 如果這個上下文有 reply_token，也清理相關的 Cache
                if (isset($context['reply_token'])) {
                    Cache::forget('reservation_context_' . $context['reply_token']);
                }
                
                // 如果這個上下文有 user_id，也清理相關的 Cache
                if (isset($context['user_id'])) {
                    Cache::forget('user_reservation_context_' . $context['user_id']);
                }
            }
        }
        
        // 清理當前 replyToken 相關的 Cache
        Cache::forget('reservation_context_' . $replyToken);
        
        // 清理當前 userId 相關的 Cache
        if ($userId) {
            Cache::forget('user_reservation_context_' . $userId);
        }
        
        // 清空 active_reservation_keys
        Cache::forget('active_reservation_keys');

        Log::info('Cleared all reservation contexts after cancel command', [
            'cleared_keys' => $clearedCount,
            'reply_token' => $replyToken,
            'total_keys' => count($allKeys)
        ]);

        // 發送確認訊息
        $message = [
            'type' => 'flex',
            'altText' => '預約已取消',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '操作完成',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'xl',
                            'align' => 'center'
                        ]
                    ],
                    'backgroundColor' => '#E74C3C',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '預約流程已中止',
                            'size' => 'xl',
                            'color' => '#333333',
                            'align' => 'center',
                            'weight' => 'bold'
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'xl'
                        ],
                        [
                            'type' => 'text',
                            'text' => '所有進行中的預約流程已清除',
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'xl',
                            'align' => 'center',
                            'wrap' => true
                        ],
                        [
                            'type' => 'text',
                            'text' => '您可以重新開始預約',
                            'size' => 'md',
                            'color' => '#27AE60',
                            'margin' => 'md',
                            'align' => 'center',
                            'weight' => 'bold'
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'height' => 'md',
                            'color' => '#27AE60',
                            'action' => [
                                'type' => 'message',
                                'label' => '開始預約',
                                'text' => '我要預約'
                            ]
                        ]
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function sendReservationQuery($replyToken, $userId)
    {
        $customer = Customer::where('line_user_id', $userId)->first();
        
        if (!$customer) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '找不到您的客戶資料，請先完成一次預約。'
            ]);
            return;
        }

        // 獲取客戶的預約記錄（最近60天）
        $allReservations = Reservation::where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'pending', 'cancelled', 'completed'])
            ->where('reservation_date', '>=', now()->subDays(60))
            ->with(['service', 'availableTime'])
            ->get();

        // 分成兩組：有效預約和歷史記錄
        $activeReservations = collect();
        $historyReservations = collect();
        
        foreach ($allReservations as $reservation) {
            $reservationDateTime = $reservation->getReservationDateTime();
            $isExpired = $reservationDateTime->isPast();
            $isCancelled = $reservation->status === 'cancelled';
            $isCompleted = $reservation->status === 'completed';
            
            // 分類邏輯：未過期且未取消且未完成的是有效預約，其他都是歷史記錄
            if (!$isExpired && !$isCancelled && !$isCompleted) {
                $activeReservations->push($reservation);
            } else {
                $historyReservations->push($reservation);
            }
        }
        
        // 排序：有效預約按時間由近到遠，歷史記錄也按時間由近到遠
        $activeReservations = $activeReservations->sortBy(function ($reservation) {
            $reservationDateTime = $reservation->getReservationDateTime();
            // 待確認優先，然後按時間排序
            if ($reservation->status === 'pending') {
                return $reservationDateTime->timestamp - 100000; // 給待確認更高優先級
            }
            return $reservationDateTime->timestamp;
        });
        
        $historyReservations = $historyReservations->sortByDesc(function ($reservation) {
            return $reservation->getReservationDateTime()->timestamp;
        });

        // 如果沒有任何記錄，顯示空狀態
        if ($activeReservations->isEmpty() && $historyReservations->isEmpty()) {
            $this->replyMessage($replyToken, [
                'type' => 'flex',
                'altText' => '查詢結果',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '查詢結果',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '目前沒有預約記錄',
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => '您目前沒有任何預約記錄。',
                                'size' => 'md',
                                'color' => '#666666',
                                'margin' => 'lg',
                                'align' => 'center',
                                'wrap' => true
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '立即預約',
                                    'text' => '我要預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ]);
            return;
        }

        // 建立預約記錄的 Carousel
        $bubbles = [];
        
        // 首先添加有效預約的分類標題（如果有有效預約）
        if ($activeReservations->isNotEmpty()) {
            $bubbles[] = $this->createCategoryHeaderBubble('有效預約', $activeReservations->count(), '#27AE60');
        }
        
        // 添加有效預約（最多5筆）
        foreach ($activeReservations->take(5) as $reservation) {
            $bubbles[] = $this->createReservationBubble($reservation, false);
        }
        
        // 然後添加歷史記錄的分類標題（如果有歷史記錄）
        if ($historyReservations->isNotEmpty()) {
            $bubbles[] = $this->createCategoryHeaderBubble('歷史記錄', $historyReservations->count(), '#95A5A6');
        }
        
        // 添加歷史記錄（最多5筆）
        foreach ($historyReservations->take(5) as $reservation) {
            $bubbles[] = $this->createReservationBubble($reservation, true);
        }

        // 如果總共超過10筆，添加提示
        $totalCount = $activeReservations->count() + $historyReservations->count();
        if ($totalCount > 10) {
            $bubbles[] = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '查看更多記錄',
                            'size' => 'lg',
                            'color' => '#333333',
                            'align' => 'center',
                            'weight' => 'bold'
                        ],
                        [
                            'type' => 'text',
                            'text' => "您還有 " . ($totalCount - 10) . " 筆預約記錄",
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'lg',
                            'align' => 'center',
                            'wrap' => true
                        ]
                    ],
                    'paddingAll' => 'xl'
                ]
            ];
        }

        $message = [
            'type' => 'flex',
            'altText' => '預約記錄查詢結果',
            'contents' => [
                'type' => 'carousel',
                'contents' => $bubbles
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    /**
     * 建立分類標題泡泡
     */
    private function createCategoryHeaderBubble($title, $count, $bgColor)
    {
        return [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => "📋 {$title}",
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'xl',
                        'align' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => "共 {$count} 筆記錄",
                        'color' => '#ffffff',
                        'size' => 'md',
                        'align' => 'center',
                        'margin' => 'md'
                    ]
                ],
                'paddingAll' => 'xl',
                'backgroundColor' => $bgColor
            ]
        ];
    }

    /**
     * 建立預約資訊泡泡
     */
    private function createReservationBubble($reservation, $isHistory)
    {
        $service = $reservation->service;
        $availableTime = $reservation->availableTime;
        $customer = $reservation->customer;
        
        // 總是使用實際的預約時間，而不是 available_time 的時間
        // 使用模型的輔助方法獲取完整的預約日期時間
        $dateTime = $reservation->getReservationDateTime();
        
        // 根據報到狀態、收款狀態和預約狀態決定顯示的狀態文字
        $statusText = '';
        $statusColor = '';
        
        // 優先顯示報到和完成狀態
        if ($reservation->status === 'completed') {
            $statusText = '已完成';
            $statusColor = '#8E44AD'; // 紫色
        } elseif ($reservation->check_in_status === 'no_show') {
            $statusText = '爽約未到';
            $statusColor = '#E74C3C'; // 紅色
        } elseif ($reservation->check_in_status === 'late') {
            $statusText = '已報到（遲到）';
            $statusColor = '#E67E22'; // 橘色
        } elseif ($reservation->check_in_status === 'checked_in') {
            $statusText = '已報到';
            $statusColor = '#27AE60'; // 綠色
        } elseif ($reservation->status === 'cancelled') {
            $statusText = '已取消';
            $statusColor = '#E74C3C'; // 紅色
        } elseif ($reservation->status === 'confirmed') {
            $statusText = '已確認';
            $statusColor = '#27AE60'; // 綠色
        } elseif ($reservation->status === 'pending') {
            $statusText = '待確認';
            $statusColor = '#F39C12'; // 黃色
        } else {
            $statusText = '未知狀態';
            $statusColor = '#95A5A6'; // 灰色
        }
        
        // 檢查預約是否已過期
        $isExpired = $dateTime->isPast();

        // 建立基本資訊內容
        $infoContents = [
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
                        'text' => '預約日期',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $dateTime->format('Y年m月d日'),
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
                        'text' => '預約時間',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $dateTime->format('H:i'),
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ]
        ];
        
        // 顯示預約時填寫的姓名，使用預約快照資料
        $displayName = $reservation->reservation_name ?: ($customer ? ($customer->line_display_name ?: $customer->name) : '');
        if ($displayName) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '預約姓名',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayName,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3,
                        'wrap' => true
                    ]
                ]
            ];
        }
        
        // 顯示預約時填寫的電話，使用預約快照資料
        $displayPhone = $reservation->reservation_phone ?: ($customer ? $customer->phone : '');
        if ($displayPhone) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '聯絡電話',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayPhone,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ];
        }
        
        // 顯示預約時填寫的備註，使用預約快照資料
        $displayNotes = $reservation->reservation_notes ?: $reservation->notes;
        if ($displayNotes && trim($displayNotes)) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '備註事項',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayNotes,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3,
                        'wrap' => true
                    ]
                ]
            ];
        }
        
        // 如果有報到時間且不是爽約，顯示報到時間
        if ($reservation->check_in_time && $reservation->check_in_status !== 'no_show') {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '報到時間',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $reservation->check_in_time->format('Y-m-d H:i'),
                        'size' => 'sm',
                        'color' => '#27AE60',
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ];
        }
        
        // 顯示收款狀態
        if ($reservation->payment_status && $reservation->payment_status !== 'unpaid') {
            $paymentStatusText = match($reservation->payment_status) {
                'paid' => '已付清',
                'partial' => '部分付款',
                default => '未付款'
            };
            $paymentColor = match($reservation->payment_status) {
                'paid' => '#27AE60',
                'partial' => '#F39C12',
                default => '#95A5A6'
            };
            
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '收款狀態',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $paymentStatusText,
                        'size' => 'sm',
                        'color' => $paymentColor,
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ];
            
            // 如果有付款金額，顯示付款金額
            if ($reservation->payment_amount > 0) {
                $infoContents[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '已付金額',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => 'NT$ ' . number_format($reservation->payment_amount),
                            'size' => 'sm',
                            'color' => '#27AE60',
                            'weight' => 'bold',
                            'flex' => 3
                        ]
                    ]
                ];
            }
        }

        // 根據實際狀態決定標題文字和顏色
        // 優先顯示更具體的狀態
        if ($reservation->status === 'completed') {
            $headerText = '已完成';
            $headerColor = '#8E44AD'; // 紫色
        } elseif ($reservation->check_in_status === 'no_show') {
            $headerText = '爽約未到';
            $headerColor = '#E74C3C'; // 紅色
        } elseif ($reservation->check_in_status === 'late') {
            $headerText = '已報到（遲到）';
            $headerColor = '#E67E22'; // 橘色
        } elseif ($reservation->check_in_status === 'checked_in') {
            $headerText = '已報到';
            $headerColor = '#27AE60'; // 綠色
        } elseif ($reservation->status === 'cancelled') {
            $headerText = '已取消';
            $headerColor = '#E74C3C'; // 紅色
        } elseif ($isHistory) {
            $headerText = '歷史記錄';
            $headerColor = '#95A5A6'; // 灰色
        } elseif ($reservation->status === 'pending') {
            $headerText = '待確認';
            $headerColor = '#F39C12'; // 黃色
        } else {
            $headerText = '已確認';
            $headerColor = '#27AE60'; // 綠色
        }

        $bubble = [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $headerText,
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'lg',
                        'align' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => "#{$reservation->id}",
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'align' => 'center',
                        'margin' => 'xs'
                    ]
                ],
                'backgroundColor' => $headerColor,
                'paddingAll' => 'lg'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '狀態',
                                'size' => 'sm',
                                'color' => '#999999',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => $statusText,
                                'size' => 'lg',
                                'color' => $statusColor,
                                'weight' => 'bold',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#F8F9FA',
                        'cornerRadius' => '8px',
                        'paddingAll' => 'md',
                        'margin' => 'none'
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'xl'
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'xl',
                        'spacing' => 'md',
                        'contents' => $infoContents
                    ]
                ],
                'paddingAll' => 'xl'
            ]
        ];
        
        // 只有有效預約（非歷史記錄）才顯示操作按鈕
        if (!$isHistory) {
            $footerContents = [];
            $reservationDateTime = $reservation->getReservationDateTime();
            $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);
            
            // 編輯按鈕 - 只有待確認狀態可以編輯
            if ($reservation->status === 'pending') {
                $footerContents[] = [
                    'type' => 'button',
                    'style' => 'secondary',
                    'height' => 'sm',
                    'flex' => 1,
                    'action' => [
                        'type' => 'postback',
                        'label' => '編輯預約',
                        'data' => "action=edit_reservation&reservation_id={$reservation->id}"
                    ]
                ];
                
                // 取消按鈕 - 只有待確認狀態可以取消
                $footerContents[] = [
                    'type' => 'button',
                    'style' => 'secondary',
                    'height' => 'sm',
                    'flex' => 1,
                    'action' => [
                        'type' => 'postback',
                        'label' => '取消預約',
                        'data' => "action=cancel_reservation&reservation_id={$reservation->id}"
                    ]
                ];
            } elseif ($reservation->status === 'confirmed') {
                // 已確認的預約 - 根據時間判斷是否可以修改
                if ($hoursUntilReservation <= 24) {
                    // 24小時內 - 顯示聯絡客服說明
                    $footerContents[] = [
                        'type' => 'text',
                        'text' => '已確認預約如需修改或取消，請聯絡客服',
                        'size' => 'sm',
                        'color' => '#666666',
                        'align' => 'center',
                        'wrap' => true
                    ];
                } else {
                    // 超過24小時 - 提供修改取消按鈕
                    $footerContents[] = [
                        'type' => 'button',
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                        'action' => [
                            'type' => 'postback',
                            'label' => '編輯',
                            'data' => "action=edit_reservation&reservation_id={$reservation->id}"
                        ]
                    ];
                    
                    $footerContents[] = [
                        'type' => 'button',
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                        'action' => [
                            'type' => 'postback',
                            'label' => '取消',
                            'data' => "action=cancel_reservation&reservation_id={$reservation->id}"
                        ]
                    ];
                }
            }
            
            if (!empty($footerContents)) {
                $bubble['footer'] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => count($footerContents) > 1 ? [
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'contents' => $footerContents
                        ]
                    ] : $footerContents,
                    'paddingAll' => 'lg'
                ];
            }
        }

        return $bubble;
    }

    /**
     * 發送可取消的預約查詢結果
     * 只顯示有效預約（未過期且未取消），按鈕只有取消預約
     */
    private function sendCancelReservationQuery($replyToken, $userId)
    {
        $customer = Customer::where('line_user_id', $userId)->first();
        
        if (!$customer) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '找不到您的客戶資料，請先完成一次預約。'
            ]);
            return;
        }

        // 獲取客戶的有效預約記錄（未過期、未取消、未完成）
        $reservations = Reservation::where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with(['service', 'availableTime'])
            ->get();

        // 篩選未過期的預約
        $activeReservations = collect();
        foreach ($reservations as $reservation) {
            $reservationDateTime = $reservation->getReservationDateTime();
            if (!$reservationDateTime->isPast()) {
                $activeReservations->push($reservation);
            }
        }
        
        // 排序：待確認優先，然後按時間排序
        $activeReservations = $activeReservations->sortBy(function ($reservation) {
            $reservationDateTime = $reservation->getReservationDateTime();
            if ($reservation->status === 'pending') {
                return $reservationDateTime->timestamp - 100000;
            }
            return $reservationDateTime->timestamp;
        });

        // 如果沒有可取消的預約
        if ($activeReservations->isEmpty()) {
            $this->replyMessage($replyToken, [
                'type' => 'flex',
                'altText' => '查詢結果',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '取消預約',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ]
                        ],
                        'backgroundColor' => '#E74C3C',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '目前沒有可取消的預約',
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => '您目前沒有任何可取消的預約記錄。',
                                'size' => 'md',
                                'color' => '#666666',
                                'margin' => 'lg',
                                'align' => 'center',
                                'wrap' => true
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '立即預約',
                                    'text' => '我要預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ]);
            return;
        }

        // 建立預約記錄的 Carousel
        $bubbles = [];
        
        // 添加標題泡泡
        $bubbles[] = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '📋 可取消的預約',
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'xl',
                        'align' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => "共 {$activeReservations->count()} 筆預約",
                        'color' => '#ffffff',
                        'size' => 'md',
                        'align' => 'center',
                        'margin' => 'md'
                    ],
                    [
                        'type' => 'text',
                        'text' => '請選擇要取消的預約',
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'align' => 'center',
                        'margin' => 'sm'
                    ]
                ],
                'paddingAll' => 'xl',
                'backgroundColor' => '#E74C3C'
            ]
        ];
        
        // 添加預約記錄（最多10筆）
        foreach ($activeReservations->take(10) as $reservation) {
            $bubbles[] = $this->createCancelReservationBubble($reservation);
        }

        // 如果超過10筆，添加提示
        if ($activeReservations->count() > 10) {
            $bubbles[] = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '查看更多預約',
                            'size' => 'lg',
                            'color' => '#333333',
                            'align' => 'center',
                            'weight' => 'bold'
                        ],
                        [
                            'type' => 'text',
                            'text' => "您還有 " . ($activeReservations->count() - 10) . " 筆可取消的預約",
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'lg',
                            'align' => 'center',
                            'wrap' => true
                        ]
                    ],
                    'paddingAll' => 'xl'
                ]
            ];
        }

        $message = [
            'type' => 'flex',
            'altText' => '可取消的預約列表',
            'contents' => [
                'type' => 'carousel',
                'contents' => $bubbles
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    /**
     * 建立可取消預約的資訊泡泡
     * 只顯示取消預約按鈕
     */
    private function createCancelReservationBubble($reservation)
    {
        $service = $reservation->service;
        $customer = $reservation->customer;
        
        $dateTime = $reservation->getReservationDateTime();
        
        // 根據預約狀態決定顯示的狀態文字
        $statusText = '';
        $statusColor = '';
        
        if ($reservation->status === 'confirmed') {
            $statusText = '已確認';
            $statusColor = '#27AE60';
        } elseif ($reservation->status === 'pending') {
            $statusText = '待確認';
            $statusColor = '#F39C12';
        } else {
            $statusText = '未知狀態';
            $statusColor = '#95A5A6';
        }

        // 建立基本資訊內容
        $infoContents = [
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
                        'text' => '預約日期',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $dateTime->format('Y年m月d日'),
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
                        'text' => '預約時間',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $dateTime->format('H:i'),
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ]
        ];
        
        // 顯示預約姓名
        $displayName = $reservation->reservation_name ?: ($customer ? ($customer->line_display_name ?: $customer->name) : '');
        if ($displayName) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '預約姓名',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayName,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3,
                        'wrap' => true
                    ]
                ]
            ];
        }
        
        // 顯示聯絡電話
        $displayPhone = $reservation->reservation_phone ?: ($customer ? $customer->phone : '');
        if ($displayPhone) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '聯絡電話',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayPhone,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3
                    ]
                ]
            ];
        }
        
        // 顯示備註
        $displayNotes = $reservation->reservation_notes ?: $reservation->notes;
        if ($displayNotes && trim($displayNotes)) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '備註事項',
                        'size' => 'sm',
                        'color' => '#666666',
                        'flex' => 2
                    ],
                    [
                        'type' => 'text',
                        'text' => $displayNotes,
                        'size' => 'sm',
                        'color' => '#333333',
                        'weight' => 'bold',
                        'flex' => 3,
                        'wrap' => true
                    ]
                ]
            ];
        }

        $bubble = [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $statusText,
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'lg',
                        'align' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => "#{$reservation->id}",
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'align' => 'center',
                        'margin' => 'xs'
                    ]
                ],
                'backgroundColor' => $statusColor,
                'paddingAll' => 'lg'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '狀態',
                                'size' => 'sm',
                                'color' => '#999999',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => $statusText,
                                'size' => 'lg',
                                'color' => $statusColor,
                                'weight' => 'bold',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#F8F9FA',
                        'cornerRadius' => '8px',
                        'paddingAll' => 'md',
                        'margin' => 'none'
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'xl'
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'xl',
                        'spacing' => 'md',
                        'contents' => $infoContents
                    ]
                ],
                'paddingAll' => 'xl'
            ]
        ];

        // 根據預約時間和狀態決定是否顯示取消按鈕
        $reservationDateTime = $reservation->getReservationDateTime();
        $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);
        
        $footerContents = [];
        
        if ($reservation->status === 'pending') {
            // 待確認狀態 - 隨時可以取消
            $footerContents[] = [
                'type' => 'button',
                'style' => 'primary',
                'height' => 'sm',
                'color' => '#E74C3C',
                'action' => [
                    'type' => 'postback',
                    'label' => '取消預約',
                    'data' => "action=cancel_reservation&reservation_id={$reservation->id}"
                ]
            ];
        } elseif ($reservation->status === 'confirmed') {
            // 已確認的預約 - 根據時間判斷是否可以取消
            if ($hoursUntilReservation <= 24) {
                // 24小時內 - 顯示聯絡客服說明
                $footerContents[] = [
                    'type' => 'text',
                    'text' => '已確認預約如需取消，請聯絡客服',
                    'size' => 'sm',
                    'color' => '#666666',
                    'align' => 'center',
                    'wrap' => true
                ];
            } else {
                // 超過24小時 - 提供取消按鈕
                $footerContents[] = [
                    'type' => 'button',
                    'style' => 'primary',
                    'height' => 'sm',
                    'color' => '#E74C3C',
                    'action' => [
                        'type' => 'postback',
                        'label' => '取消預約',
                        'data' => "action=cancel_reservation&reservation_id={$reservation->id}"
                    ]
                ];
            }
        }
        
        if (!empty($footerContents)) {
            $bubble['footer'] = [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $footerContents,
                'paddingAll' => 'lg'
            ];
        }

        return $bubble;
    }


    private function sendServiceSelection($replyToken)
    {
        $services = Service::where('is_active', true)->take(10)->get(); // 限制最多10個服務，留2個位置給其他內容
        
        if ($services->isEmpty()) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '目前沒有可用的服務項目，請稍後再試。'
            ]);
            return;
        }

        // 建立 Flex Message 的服務選擇卡片
        $contents = [];
        foreach ($services as $service) {
            $priceText = $service->price ? "NT$ " . number_format((float)$service->price) : "免費";
            
            // 建立服務卡片基本結構
            $bubble = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => []
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => [
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'height' => 'sm',
                            'action' => [
                                'type' => 'postback',
                                'label' => '選擇這個服務',
                                'data' => 'action=select_service&service_id=' . $service->id
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'sm'
                        ]
                    ]
                ]
            ];

            // 如果有圖片且 URL 有效，添加 hero 區塊
            if ($service->full_image_url && $this->isValidImageUrl($service->full_image_url)) {
                $bubble['hero'] = [
                    'type' => 'image',
                    'url' => $service->full_image_url,
                    'size' => 'full',
                    'aspectRatio' => '20:13',
                    'aspectMode' => 'cover'
                ];
            }

            // 添加服務資訊到 body
            $bubble['body']['contents'] = [
                [
                    'type' => 'text',
                    'text' => $service->name,
                    'weight' => 'bold',
                    'size' => 'lg'
                ],
                [
                    'type' => 'text',
                    'text' => $service->description,
                    'size' => 'sm',
                    'color' => '#666666',
                    'wrap' => true,
                    'margin' => 'md'
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'margin' => 'lg',
                    'spacing' => 'sm',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'baseline',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '時間',
                                    'color' => '#aaaaaa',
                                    'size' => 'sm',
                                    'flex' => 1
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $service->duration . ' 分鐘',
                                    'wrap' => true,
                                    'color' => '#666666',
                                    'size' => 'sm',
                                    'flex' => 2
                                ]
                            ]
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'baseline',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '價格',
                                    'color' => '#aaaaaa',
                                    'size' => 'sm',
                                    'flex' => 1
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $priceText,
                                    'wrap' => true,
                                    'color' => '#666666',
                                    'size' => 'sm',
                                    'flex' => 2
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $contents[] = $bubble;
        }

        $message = [
            'type' => 'flex',
            'altText' => '請選擇服務項目',
            'contents' => [
                'type' => 'carousel',
                'contents' => $contents
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function handleServiceSelection($replyToken, $serviceId)
    {
        try {
            Log::info('handleServiceSelection called', [
                'service_id' => $serviceId,
                'reply_token' => $replyToken
            ]);
            
            $service = Service::find($serviceId);
            if (!$service) {
                Log::warning('Service not found', ['service_id' => $serviceId]);
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到指定的服務項目，請重新選擇。'
                ]);
                return;
            }

            Log::info('Service found', [
                'service_name' => $service->name,
                'service_duration' => $service->duration
            ]);

            // 使用新的邏輯獲取適合該服務的可用時段
            $availableTimes = $this->getAvailableTimeSlotsForService($serviceId, null);

            Log::info('Available times retrieved', [
                'count' => $availableTimes->count()
            ]);

            if ($availableTimes->isEmpty()) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => "目前沒有適合「{$service->name}」（{$service->duration}分鐘）的可用時段，請稍後再試。"
                ]);
                return;
            }

        // 建立日期選擇的 Flex Message - 使用分頁carousel方式
        $availableDates = [];
        foreach ($availableTimes as $time) {
            $date = $time->date; // 使用新格式的date屬性
            if (!isset($availableDates[$date])) {
                $availableDates[$date] = Carbon::parse($date);
            }
        }
        
        // 如果沒有可用日期，直接返回錯誤訊息
        if (empty($availableDates)) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => "目前沒有適合「{$service->name}」（{$service->duration}分鐘）的可用時段，請稍後再試。"
            ]);
            return;
        }
        
        // 按日期排序
        ksort($availableDates);
        
        // 將日期分組，每組最多12個日期（每頁6行，每行2個）
        $dateChunks = array_chunk($availableDates, 12, true);
        $bubbles = [];
        
        foreach ($dateChunks as $chunkIndex => $dateChunk) {
            $dateButtons = [];
            $buttonRows = [];
            $buttonsPerRow = 2; // 每行2個日期按鈕
            
            foreach ($dateChunk as $dateStr => $dateObj) {
                $displayDate = $dateObj->format('m/d');
                $dayMapping = [
                    'Monday' => '一', 'Tuesday' => '二', 'Wednesday' => '三', 
                    'Thursday' => '四', 'Friday' => '五', 'Saturday' => '六', 'Sunday' => '日'
                ];
                $dayOfWeek = $dayMapping[$dateObj->format('l')] ?? $dateObj->format('l');
                
                $dateButtons[] = [
                    'type' => 'button',
                    'height' => 'sm',
                    'color' => '#3498DB',
                    'action' => [
                        'type' => 'postback',
                        'label' => "{$displayDate} ({$dayOfWeek})",
                        'data' => "action=select_date&service_id={$serviceId}&date={$dateStr}"
                    ],
                    'style' => 'primary',
                    'flex' => 1,
                    'margin' => 'sm'
                ];
                
                // 每2個按鈕組成一行
                if (count($dateButtons) == $buttonsPerRow) {
                    $buttonRows[] = [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'spacing' => 'md',
                        'contents' => array_values($dateButtons)
                    ];
                    $dateButtons = [];
                }
            }
            
            // 處理剩餘的按鈕
            if (!empty($dateButtons)) {
                $buttonRows[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'spacing' => 'md',
                    'contents' => array_values($dateButtons)
                ];
            }
            
            $durationText = $service->duration . '分鐘';
            $totalPages = count($dateChunks);
            $currentPage = $chunkIndex + 1;
            
            // 建立當前頁面的bubble
            $bubble = [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '選擇日期',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'xl',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => "服務：{$service->name} ({$durationText})",
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ],
                        [
                            'type' => 'text',
                            'text' => "第 {$currentPage} 頁 / 共 {$totalPages} 頁",
                            'color' => '#ffffff',
                            'size' => 'xs',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#3498DB',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '可預約日期',
                            'size' => 'lg',
                            'color' => '#333333',
                            'weight' => 'bold',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'text',
                            'text' => '請選擇您希望的預約日期',
                            'size' => 'sm',
                            'color' => '#666666',
                            'margin' => 'xs',
                            'wrap' => true
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'lg'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'spacing' => 'sm',
                            'margin' => 'lg',
                            'contents' => $buttonRows
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => []
                ]
            ];
            
            // 如果有多頁，添加導航提示
            if ($totalPages > 1) {
                $bubble['footer']['contents'][] = [
                    'type' => 'text',
                    'text' => '左右滑動查看更多日期',
                    'size' => 'xs',
                    'color' => '#999999',
                    'align' => 'center',
                    'margin' => 'sm'
                ];
            }
            
            // 添加取消提示
            $bubble['footer']['contents'][] = [
                'type' => 'text',
                'text' => '輸入「取消」可中止預約流程',
                'size' => 'xs',
                'color' => '#E74C3C',
                'align' => 'center',
                'margin' => 'sm'
            ];
            
            $bubble['footer']['paddingAll'] = 'md';
            
            $bubbles[] = $bubble;
        }
        
        // 根據頁面數量決定使用單個bubble還是carousel
        if (count($bubbles) == 1) {
            $message = [
                'type' => 'flex',
                'altText' => '選擇預約日期',
                'contents' => $bubbles[0]
            ];
        } else {
            $message = [
                'type' => 'flex',
                'altText' => '選擇預約日期',
                'contents' => [
                    'type' => 'carousel',
                    'contents' => $bubbles
                ]
            ];
        }

        $this->replyMessage($replyToken, $message);
        
        } catch (\Exception $e) {
            Log::error('Error in handleServiceSelection', [
                'service_id' => $serviceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '選擇服務時發生錯誤，請稍後再試。'
            ]);
        }
    }

    private function handleDateSelection($replyToken, $serviceId, $selectedDate)
    {
        $service = Service::find($serviceId);
        if (!$service) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '找不到指定的服務項目，請重新選擇。'
            ]);
            return;
        }

        // 從 available_times 表獲取指定日期的可用時段
        $date = Carbon::parse($selectedDate);
        $availableTimeSlots = $this->getAvailableTimeSlotsForService($serviceId, null);
        
        // 過濾出指定日期的時段
        $filteredTimes = $availableTimeSlots->filter(function($slot) use ($date) {
            return Carbon::parse($slot->start_time)->isSameDay($date);
        });

        if ($filteredTimes->isEmpty()) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '該日期沒有可用時段，請選擇其他日期。'
            ]);
            return;
        }

        // 建立時間選擇的 Flex Message
        $timeButtons = [];
        $buttonRows = [];
        $buttonsPerRow = 1; // 改為每行1個時間按鈕，讓時間更清楚可見
        
        foreach ($filteredTimes as $index => $time) {
            $timeButtons[] = [
                'type' => 'button',
                'height' => 'sm',
                'color' => '#3498DB',
                'action' => [
                    'type' => 'postback',
                    'label' => $time->display_time,
                    'data' => "action=select_time&service_id={$serviceId}&time_id={$time->id}"
                ],
                'style' => 'primary',
                'margin' => 'sm'
            ];
            
            // 每1個按鈕組成一行
            if (count($timeButtons) == $buttonsPerRow || $index == count($filteredTimes) - 1) {
                $buttonRows[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'md',
                    'contents' => $timeButtons
                ];
                $timeButtons = [];
            }
        }

        $selectedDateObj = Carbon::parse($selectedDate);
        $displayDate = $selectedDateObj->format('m/d');
        $dayMapping = [
            'Monday' => '一', 'Tuesday' => '二', 'Wednesday' => '三', 
            'Thursday' => '四', 'Friday' => '五', 'Saturday' => '六', 'Sunday' => '日'
        ];
        $dayOfWeek = $dayMapping[$selectedDateObj->format('l')] ?? $selectedDateObj->format('l');
        $durationText = $service->duration . '分鐘';

        $message = [
            'type' => 'flex',
            'altText' => '選擇預約時間',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '選擇時間',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'xl',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => "{$displayDate} ({$dayOfWeek}) - {$service->name}",
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#3498DB',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '可預約時段',
                            'size' => 'lg',
                            'color' => '#333333',
                            'weight' => 'bold',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'text',
                            'text' => "服務時長：{$durationText}",
                            'size' => 'sm',
                            'color' => '#666666',
                            'margin' => 'xs'
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'xl'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'spacing' => 'lg',
                            'margin' => 'xl',
                            'contents' => $buttonRows
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'button',
                            'height' => 'sm',
                            'color' => '#95A5A6',
                            'action' => [
                                'type' => 'postback',
                                'label' => '重新選擇日期',
                                'data' => "action=select_service&service_id={$serviceId}"
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'md'
                        ]
                    ],
                    'paddingAll' => 'md'
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function handleTimeSelection($replyToken, $serviceId, $timeId, $userId = null)
    {
        $service = Service::find($serviceId);
        
        // 解析虛擬時段 ID
        $virtualTimeSlot = $this->findVirtualTimeSlot($serviceId, $timeId);
        
        if (!$service || !$virtualTimeSlot) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '選擇的服務或時間無效，請重新開始。'
            ]);
            return;
        }

        // 虛擬時段已經過可預約性檢查，如果能找到就表示可預約
        
        $dateTime = Carbon::parse($virtualTimeSlot->start_time);
        $serviceEndTime = Carbon::parse($virtualTimeSlot->end_time);
        
        // 將預約資訊暫存並開始收集客戶資訊
        $this->saveReservationContext($replyToken, $serviceId, $timeId, 'waiting_name', $userId);
        
        // 使用 Flex Message 來顯示預約資訊並請求姓名
        $message = [
            'type' => 'flex',
            'altText' => '請填寫預約資訊',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '預約資訊',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'lg'
                        ]
                    ],
                    'backgroundColor' => '#27AE60'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '您選擇了：',
                            'weight' => 'bold',
                            'size' => 'md',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'lg',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'spacing' => 'sm',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '服務',
                                            'color' => '#aaaaaa',
                                            'size' => 'sm',
                                            'flex' => 2
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $service->name,
                                            'wrap' => true,
                                            'color' => '#666666',
                                            'size' => 'sm',
                                            'flex' => 5
                                        ]
                                    ]
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'spacing' => 'sm',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '時間',
                                            'color' => '#aaaaaa',
                                            'size' => 'sm',
                                            'flex' => 2
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $dateTime->format('Y年m月d日 H:i'),
                                            'wrap' => true,
                                            'color' => '#666666',
                                            'size' => 'sm',
                                            'flex' => 5
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'lg'
                        ],
                        [
                            'type' => 'text',
                            'text' => '請提供您的聯絡資訊',
                            'weight' => 'bold',
                            'size' => 'md',
                            'margin' => 'lg',
                            'color' => '#27AE60'
                        ],
                        [
                            'type' => 'text',
                            'text' => '我們將逐步收集您的預約資訊',
                            'size' => 'sm',
                            'color' => '#666666',
                            'margin' => 'sm'
                        ]
                    ]
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => [
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'height' => 'sm',
                            'color' => '#27AE60',
                            'action' => [
                                'type' => 'postback',
                                'label' => '點我開始填寫資訊',
                                'data' => "action=start_info_collection&service_id={$serviceId}&time_id={$timeId}"
                            ]
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'margin' => 'md',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '需要填寫：姓名、電話、備註(選填)',
                                    'size' => 'xs',
                                    'color' => '#999999',
                                    'wrap' => true,
                                    'flex' => 1
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'sm'
                        ]
                    ]
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function startInfoCollection($replyToken, $serviceId, $timeId, $userId = null)
    {
        $service = Service::find($serviceId);
        $availableTime = AvailableTime::find($timeId);
        
        if (!$service || !$availableTime) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '選擇的服務或時間無效，請重新開始。'
            ]);
            return;
        }

        // 檢查時段是否還有空位
        if ($availableTime->current_bookings >= $availableTime->max_capacity) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '該時段已額滿，請選擇其他時間。'
            ]);
            return;
        }

        $dateTime = Carbon::parse($availableTime->start_time);
        
        // 將預約資訊暫存並開始收集客戶資訊
        $this->saveReservationContext($replyToken, $serviceId, $timeId, 'waiting_name', $userId);
        
        // 發送第一步：收集姓名
        $message = [
            'type' => 'flex',
            'altText' => '第1步：請輸入姓名',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '預約資訊填寫',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'lg',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '第 1 步 / 共 3 步',
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#27AE60',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '請輸入您的姓名',
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#333333',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '請直接回覆您的姓名',
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'md',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'xl'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'xl',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '範例格式',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'weight' => 'bold'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '王小明、李美麗、陳大華',
                                    'size' => 'sm',
                                    'color' => '#27AE60',
                                    'margin' => 'sm'
                                ]
                            ]
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '進度指示',
                            'size' => 'xs',
                            'color' => '#999999',
                            'align' => 'center',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'md',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#E8E8E8',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#E8E8E8',
                                    'cornerRadius' => '6px'
                                ]
                            ]
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '姓名',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '電話',
                                    'size' => 'xs',
                                    'color' => '#999999',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '備註',
                                    'size' => 'xs',
                                    'color' => '#999999',
                                    'flex' => 1,
                                    'align' => 'center'
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'md'
                        ]
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function saveReservationContext($replyToken, $serviceId, $timeId, $step = 'waiting_customer_info', $userId = null)
    {
        // 使用更可靠的 key 生成方式
        $contextKey = 'reservation_context_' . md5($replyToken . microtime(true) . rand(1000, 9999));
        
        $contextData = [
            'service_id' => $serviceId,
            'time_id' => $timeId,
            'step' => $step,
            'customer_data' => [],
            'created_at' => now(),
            'reply_token' => $replyToken,
            'user_id' => $userId
        ];
        
        // 保存上下文數據
        Cache::put($contextKey, $contextData, now()->addMinutes(30));
        
        // 記錄活躍的預約 keys（用於管理和清理）
        $activeKeys = Cache::get('active_reservation_keys', []);
        if (!in_array($contextKey, $activeKeys)) {
            $activeKeys[] = $contextKey;
            Cache::put('active_reservation_keys', $activeKeys, now()->addHour());
        }
        
        // 建立 replyToken 到 contextKey 的映射（短期）
        Cache::put('reservation_context_' . $replyToken, $contextKey, now()->addMinutes(30));
        
        // 建立 userId 到 contextKey 的映射（用於跨消息維持上下文）
        if ($userId) {
            Cache::put('user_reservation_context_' . $userId, $contextKey, now()->addMinutes(30));
        }
        
        Log::info('Saved reservation context', [
            'contextKey' => $contextKey,
            'replyToken' => $replyToken,
            'userId' => $userId,
            'step' => $step,
            'serviceId' => $serviceId,
            'timeId' => $timeId
        ]);
    }

    private function removeFromActiveKeys($contextKey)
    {
        $activeKeys = Cache::get('active_reservation_keys', []);
        $activeKeys = array_filter($activeKeys, function($key) use ($contextKey) {
            return $key !== $contextKey;
        });
        Cache::put('active_reservation_keys', array_values($activeKeys), now()->addHour());
    }

    private function clearAllReservationContexts()
    {
        // 清除所有進行中的預約上下文
        $allKeys = Cache::get('active_reservation_keys', []);
        $clearedCount = 0;
        
        foreach ($allKeys as $key) {
            $context = Cache::get($key);
            if ($context) {
                // 徹底刪除已完成的預約上下文
                Cache::forget($key);
                $clearedCount++;
                
                // 如果這個上下文有 reply_token，也清理相關的 Cache
                if (isset($context['reply_token'])) {
                    Cache::forget('reservation_context_' . $context['reply_token']);
                }
                
                // 如果這個上下文有 user_id，也清理相關的 Cache
                if (isset($context['user_id'])) {
                    Cache::forget('user_reservation_context_' . $context['user_id']);
                }
            }
        }
        Cache::forget('active_reservation_keys');
        
        Log::info('Cleared all reservation contexts after successful reservation', [
            'cleared_contexts' => $clearedCount,
            'total_keys' => count($allKeys)
        ]);
    }

    private function handleReservationConfirmation($replyToken, $data, $userId)
    {
        $service = Service::find($data['service_id']);
        $customer = Customer::where('line_user_id', $userId)->first();
        
        if (!$service || !$customer) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約失敗：資料不完整，請重新開始。'
            ]);
            return;
        }

        // 檢查客戶是否被封鎖
        if ($customer->status === 'blocked') {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '很抱歉，您的帳號已被停用，無法進行預約。如有疑問，請聯繫客服人員。'
            ]);
            
            Log::warning('Blocked customer attempted to make reservation', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'line_user_id' => $userId
            ]);
            
            return;
        }

        // 使用虛擬時段邏輯進行檢查
        $virtualTimeSlot = $this->findVirtualTimeSlot($data['service_id'], $data['time_id']);
        
        if (!$virtualTimeSlot) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約失敗：時段資訊無效，請重新開始。'
            ]);
            return;
        }

        // 再次檢查虛擬時段是否可以預約
        if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約失敗：該時段已無法容納此服務，請選擇其他時間。'
            ]);
            return;
        }

        try {
            // 使用資料庫事務來防止並發問題
            $reservation = DB::transaction(function () use ($virtualTimeSlot, $service, $customer) {
                // 在事務內再次檢查虛擬時段是否可以預約（防止並發問題）
                if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
                    throw new \Exception('該時段已無法容納此服務');
                }
                
                // 根據設定決定預約狀態
                $confirmMode = $this->getReservationConfirmMode();
                $status = $confirmMode === 'auto' ? 'confirmed' : 'pending';
                
                // 創建預約記錄，使用基礎時段 ID
                $reservation = Reservation::create([
                    'user_id' => null, // LINE Bot 預約不需要管理員 ID
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'available_time_id' => $virtualTimeSlot->base_time_slot_id, // 使用基礎時段 ID
                    'reservation_date' => Carbon::parse($virtualTimeSlot->start_time)->toDateString(),
                    'reservation_time' => Carbon::parse($virtualTimeSlot->start_time)->format('H:i:s'),
                    'status' => $status,
                    'notes' => '無',
                ]);
                
                return $reservation;
            });

            // 注意：不更新 current_bookings，因為我們基於實際預約記錄計算重疊

            $dateTime = Carbon::parse($virtualTimeSlot->start_time);
            $priceText = $service->price ? "NT$ " . number_format((float)$service->price) : "免費";

            // 根據預約狀態決定訊息內容
            $isAutoConfirmed = $reservation->status === 'confirmed';
            $headerText = $isAutoConfirmed ? '預約成功' : '預約提交成功';
            $subHeaderText = $isAutoConfirmed ? '預約已確認' : '等待確認中';
            $headerColor = $isAutoConfirmed ? '#27AE60' : '#F39C12';
            $statusText = $isAutoConfirmed ? '已確認' : '待確認';
            $bottomText = $isAutoConfirmed 
                ? '感謝您的預約，我們將在預約時間為您提供服務。'
                : '您的預約已提交，我們將盡快確認並通知您。';

            // 使用 Flex Message 呈現預約結果資訊
            $message = [
                'type' => 'flex',
                'altText' => $headerText,
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $headerText,
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => $subHeaderText,
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => $headerColor,
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約編號',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "#{$reservation->id}",
                                        'size' => 'lg',
                                        'color' => '#27AE60',
                                        'weight' => 'bold',
                                        'margin' => 'xs'
                                    ]
                                ],
                                'backgroundColor' => '#F8F9FA',
                                'cornerRadius' => '8px',
                                'paddingAll' => 'md',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
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
                                                'text' => '預約日期',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $dateTime->format('Y年m月d日'),
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
                                                'text' => '預約時間',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $dateTime->format('H:i'),
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
                                                'text' => '服務費用',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $priceText,
                                                'size' => 'sm',
                                                'color' => '#27AE60',
                                                'weight' => 'bold',
                                                'flex' => 3
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約狀態',
                                        'size' => 'sm',
                                        'color' => '#666666',
                                        'flex' => 2
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $statusText,
                                        'size' => 'sm',
                                        'color' => $headerColor,
                                        'weight' => 'bold',
                                        'flex' => 3
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'xl'
                    ],
                    [
                        'type' => 'text',
                        'text' => $bottomText,
                        'size' => 'sm',
                        'color' => '#666666',
                        'margin' => 'xl',
                        'wrap' => true,
                        'align' => 'center'
                    ]
                ],
                'paddingAll' => 'xl'
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('預約創建失敗: ' . $e->getMessage(), [
                'service_id' => $service->id ?? 'N/A',
                'virtual_time_slot' => $virtualTimeSlot->id ?? 'N/A',
                'customer_id' => $customer->id ?? 'N/A',
                'error_trace' => $e->getTraceAsString()
            ]);
            
            // 如果是時段衝突，提供更具體的錯誤訊息
            if (strpos($e->getMessage(), '該時段已無法容納') !== false) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '抱歉，該時段剛好被其他客戶預約了，請選擇其他時間。'
                ]);
            } else {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約失敗：系統錯誤，請稍後再試或聯繫我們。'
                ]);
            }
        }
    }

    private function sendWelcomeMessage($replyToken)
    {
        $message = [
            'type' => 'text',
            'text' => "歡迎使用預約系統！\n\n您可以輸入「我要預約」開始預約流程，或直接說明您想要的服務。\n\n我們提供專業的服務，期待為您服務！",
            'quickReply' => [
                'items' => [
                    [
                        'type' => 'action',
                        'action' => [
                            'type' => 'message',
                            'label' => '我要預約',
                            'text' => '我要預約'
                        ]
                    ]
                ]
            ]
        ];
        
        $this->replyMessage($replyToken, $message);
    }

    private function getOrCreateCustomer($userId, $event)
    {
        try {
            // 首先嘗試查找現有客戶（包括軟刪除的）
            $customer = Customer::withTrashed()->where('line_user_id', $userId)->first();
            
            if ($customer) {
                // 如果客戶被軟刪除，恢復它
                if ($customer->trashed()) {
                    $customer->restore();
                    LoggingService::logCustomerEvent('customer_restored', [
                        'customer_id' => $customer->id,
                        'line_user_id' => $userId
                    ]);
                }
                
                // 更新 LINE 資訊，但不要覆蓋 blocked 狀態
                $profile = $this->getUserProfile($userId);
                $updateData = [
                    'line_display_name' => $profile['displayName'] ?? $customer->line_display_name,
                    'line_picture_url' => $profile['pictureUrl'] ?? $customer->line_picture_url,
                    'line_status_message' => $profile['statusMessage'] ?? $customer->line_status_message,
                    'last_interaction_at' => now()
                ];
                
                // 只有在客戶不是 blocked 狀態時，才將狀態設為 active
                if ($customer->status !== 'blocked') {
                    $updateData['status'] = 'active';
                }
                
                $customer->update($updateData);

                LoggingService::logCustomerEvent('customer_info_updated', [
                    'customer_id' => $customer->id,
                    'line_user_id' => $userId,
                    'status' => $customer->status,
                    'has_display_name' => !empty($profile['displayName']),
                    'has_picture' => !empty($profile['pictureUrl'])
                ]);
                
                return $customer;
            }
            
            // 如果不存在，嘗試建立新客戶
            $profile = $this->getUserProfile($userId);
            
            try {
                $customer = Customer::create([
                    'line_user_id' => $userId,
                    'name' => $profile['displayName'] ?? '未知用戶',
                    'line_display_name' => $profile['displayName'] ?? null,
                    'line_picture_url' => $profile['pictureUrl'] ?? null,
                    'line_status_message' => $profile['statusMessage'] ?? null,
                    'status' => 'active'
                ]);

                LoggingService::logCustomerEvent('customer_created', [
                    'customer_id' => $customer->id,
                    'line_user_id' => $userId,
                    'name' => $customer->name,
                    'has_display_name' => !empty($profile['displayName']),
                    'has_picture' => !empty($profile['pictureUrl'])
                ]);
                
                return $customer;
            } catch (\Illuminate\Database\QueryException $e) {
                // 如果建立時發生重複鍵值錯誤，重新查詢（包括軟刪除的）
                if ($e->getCode() == 23000) { // Integrity constraint violation
                    $customer = Customer::withTrashed()->where('line_user_id', $userId)->first();
                    if ($customer) {
                        if ($customer->trashed()) {
                            $customer->restore();
                            // 只有在客戶不是 blocked 狀態時，才將狀態設為 active
                            if ($customer->status !== 'blocked') {
                                $customer->update(['status' => 'active']);
                            }
                        }
                        return $customer;
                    }
                }
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to get or create customer', [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            
            // 如果發生任何錯誤，再次嘗試查詢現有客戶（包括軟刪除的）
            $customer = Customer::withTrashed()->where('line_user_id', $userId)->first();
            
            if ($customer) {
                if ($customer->trashed()) {
                    try {
                        $customer->restore();
                        // 只有在客戶不是 blocked 狀態時，才將狀態設為 active
                        if ($customer->status !== 'blocked') {
                            $customer->update(['status' => 'active']);
                        }
                    } catch (\Exception $restoreError) {
                        Log::error('Failed to restore customer', [
                            'error' => $restoreError->getMessage(),
                            'userId' => $userId
                        ]);
                    }
                }
                return $customer;
            }
            
            // 如果還是找不到，建立一個預設客戶
            try {
                $profile = $this->getUserProfile($userId);
                $customer = Customer::firstOrCreate(
                    ['line_user_id' => $userId],
                    [
                        'name' => $profile['displayName'] ?? '未知用戶',
                        'line_display_name' => $profile['displayName'] ?? null,
                        'line_picture_url' => $profile['pictureUrl'] ?? null,
                        'line_status_message' => $profile['statusMessage'] ?? null,
                        'status' => 'active'
                    ]
                );
                return $customer;
            } catch (\Exception $createError) {
                Log::error('Failed to create fallback customer', [
                    'error' => $createError->getMessage(),
                    'userId' => $userId
                ]);
                return null;
            }
        }
    }

    private function getUserProfile($userId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->channelAccessToken,
            ])->get("https://api.line.me/v2/bot/profile/{$userId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to get LINE user profile: ' . $e->getMessage());
        }
        
        return [];
    }

    private function replyMessage($replyToken, $message)
    {
        Log::info('Attempting to send LINE reply', [
            'replyToken' => $replyToken,
            'message' => $message,
            'accessToken' => substr($this->channelAccessToken, 0, 20) . '...'
        ]);

        $data = [
            'replyToken' => $replyToken,
            'messages' => [$message]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
        ])->post($this->apiUrl, $data);

        Log::info('LINE API response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'successful' => $response->successful()
        ]);

        if (!$response->successful()) {
            Log::error('LINE reply message failed: ' . $response->body());
        } else {
            Log::info('LINE reply message sent successfully');
            
            // 記錄發送的訊息
            try {
                LineMessageLog::create([
                    'tenant_id' => $this->tenant?->id,
                    'line_user_id' => 'reply', // reply token 無法獲取 userId，使用 'reply'
                    'message_type' => $message['type'] ?? 'unknown',
                    'message_content' => json_encode($message),
                    'direction' => 'outgoing'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log outgoing LINE message: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send a push message to a LINE user
     */
    public function pushMessage($userId, $message)
    {
        Log::info('Attempting to send LINE push message', [
            'userId' => $userId,
            'message' => $message,
            'accessToken' => substr($this->channelAccessToken, 0, 20) . '...'
        ]);

        $data = [
            'to' => $userId,
            'messages' => [$message]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
        ])->post('https://api.line.me/v2/bot/message/push', $data);

        Log::info('LINE push API response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'successful' => $response->successful()
        ]);

        if (!$response->successful()) {
            Log::error('LINE push message failed: ' . $response->body());
            throw new \Exception('Failed to send LINE push message');
        } else {
            Log::info('LINE push message sent successfully');
            
            // 記錄發送的訊息
            try {
                LineMessageLog::create([
                    'tenant_id' => $this->tenant?->id,
                    'line_user_id' => $userId,
                    'message_type' => $message['type'] ?? 'unknown',
                    'message_content' => json_encode($message),
                    'direction' => 'outgoing'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log outgoing LINE message: ' . $e->getMessage());
            }
        }

        return true;
    }

    private function logMessage($event)
    {
        try {
            $userId = $event['source']['userId'] ?? 'unknown';
            $messageType = $event['type'] ?? 'unknown';
            $content = json_encode($event);

            LineMessageLog::create([
                'tenant_id' => $this->tenant?->id,
                'line_user_id' => $userId,
                'message_type' => $messageType,
                'message_content' => $content,
                'direction' => 'incoming'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log LINE message: ' . $e->getMessage());
        }
    }

    private function isCustomerInfoMessage($text, $replyToken, $userId = null)
    {
        Log::info('Checking if customer info message', [
            'text' => $text,
            'replyToken' => $replyToken,
            'userId' => $userId
        ]);
        
        // 首先檢查是否為查詢預約關鍵字，如果是就直接返回 false
        if ($this->isQueryReservationKeyword($text)) {
            Log::info('Text matches query keyword, returning false');
            return false;
        }
        
        // 檢查是否為預約關鍵字，如果是就直接返回 false
        if ($this->isReservationKeyword($text)) {
            Log::info('Text matches reservation keyword, returning false');
            return false;
        }
        
        // 檢查是否為取消關鍵字，如果是就直接返回 false
        if ($this->isCancelKeyword($text)) {
            Log::info('Text matches cancel keyword, returning false');
            return false;
        }
        
        // 優先檢查 userId 對應的上下文（跨消息持續性）
        $contextKeyRef = null;
        $context = null;
        
        if ($userId) {
            $contextKeyRef = Cache::get('user_reservation_context_' . $userId);
            Log::info('Checking userId context', [
                'userId' => $userId,
                'contextKeyRef' => $contextKeyRef
            ]);
            
            if ($contextKeyRef) {
                $context = Cache::get($contextKeyRef);
                if ($context) {
                    Log::info('Found valid userId context', [
                        'contextKey' => $contextKeyRef,
                        'context' => $context
                    ]);
                }
            }
        }
        
        // 如果沒有找到 userId 上下文，嘗試 replyToken 上下文（向後兼容）
        if (!$context) {
            $contextKeyRef = Cache::get('reservation_context_' . $replyToken);
            Log::info('Fallback to replyToken context check', [
                'replyToken' => $replyToken,
                'contextKeyRef' => $contextKeyRef
            ]);
            
            if (!$contextKeyRef) {
                Log::info('No context key reference found, returning false');
                return false;
            }
            
            $context = Cache::get($contextKeyRef);
            Log::info('Context data check', [
                'contextKey' => $contextKeyRef,
                'context' => $context
            ]);
        }
        
        if (!$context) {
            Log::info('No context data found, returning false');
            return false;
        }
        
        if (!$context) {
            Log::info('No context data found, returning false');
            return false;
        }
        
        // 檢查上下文是否有效且沒有被標記為已取消
        if (isset($context['cancelled']) && $context['cancelled'] === true) {
            Log::info('Context is cancelled, returning false');
            return false;
        }
        
        // 檢查上下文是否過期（超過30分鐘）
        if (isset($context['created_at'])) {
            $createdAt = Carbon::parse($context['created_at']);
            if ($createdAt->diffInMinutes(now()) > 30) {
                Log::info('Context is expired, returning false', [
                    'created_at' => $context['created_at'],
                    'minutes_passed' => $createdAt->diffInMinutes(now())
                ]);
                return false;
            }
        }
        
        // 只有在明確的資料收集步驟中才處理客戶資訊
        $validSteps = ['waiting_customer_info', 'waiting_name', 'waiting_phone', 'waiting_notes'];
        if (in_array($context['step'], $validSteps)) {
            Log::info('Valid customer info context detected', [
                'step' => $context['step'],
                'replyToken' => $replyToken,
                'text' => $text
            ]);
            return true;
        }
        
        // 處理完整格式的客戶資訊（姓名：xxx 電話：xxx 格式）
        if ($context['step'] === 'waiting_customer_info' && 
            strpos($text, '姓名：') !== false && strpos($text, '電話：') !== false) {
            Log::info('Full format customer info detected');
            return true;
        }
        
        Log::info('No valid condition met, returning false', [
            'step' => $context['step'],
            'validSteps' => $validSteps
        ]);
        
        return false;
    }

    private function processCustomerInfo($text, $replyToken, $userId)
    {
        // 優先檢查 userId 對應的上下文
        $contextKeyRef = null;
        $context = null;
        
        if ($userId) {
            $contextKeyRef = Cache::get('user_reservation_context_' . $userId);
            if ($contextKeyRef) {
                $context = Cache::get($contextKeyRef);
            }
        }
        
        // 如果沒有找到 userId 上下文，嘗試 replyToken 上下文（向後兼容）
        if (!$context) {
            $contextKeyRef = Cache::get('reservation_context_' . $replyToken);
            if ($contextKeyRef) {
                $context = Cache::get($contextKeyRef);
            }
        }
        
        if (!$contextKeyRef || !$context) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約資訊已過期，請重新開始預約流程。'
            ]);
            return;
        }
        
        $contextKey = $contextKeyRef;
        
        // 檢查上下文是否被標記為已取消
        if (isset($context['cancelled']) && $context['cancelled'] === true) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約流程已被取消，請重新開始預約。'
            ]);
            return;
        }
        
        // 檢查上下文是否過期
        if (isset($context['created_at'])) {
            $createdAt = Carbon::parse($context['created_at']);
            if ($createdAt->diffInMinutes(now()) > 30) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約資訊已過期，請重新開始預約流程。'
                ]);
                // 清理過期的上下文
                Cache::forget($contextKey);
                $this->removeFromActiveKeys($contextKey);
                return;
            }
        }
        
        // 處理完整格式的資訊
        if (strpos($text, '姓名：') !== false && strpos($text, '電話：') !== false) {
            $customerData = $this->parseCustomerInfo($text);
            
            if (!$customerData) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '資料格式不正確，請按照以下格式重新輸入：\n姓名：王小明\n電話：0912345678\n備註：(選填)'
                ]);
                return;
            }
            
            // 確保 LINE 客戶記錄存在，並在第一次預約時更新客戶資料
            $customer = $this->getOrCreateCustomer($userId, null);
            
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '建立客戶資料時發生錯誤，請稍後再試。'
                ]);
                return;
            }
            
            // 如果是第一次預約，更新客戶的電話和姓名資訊
            if ($customer->total_reservations == 0) {
                $updateData = [];
                
                // 如果客戶表中沒有電話，更新預約時填寫的電話
                if (empty($customer->phone) && !empty($customerData['phone'])) {
                    $updateData['phone'] = $customerData['phone'];
                }
                
                // 如果客戶表中的姓名是預設值或為空，更新預約時填寫的姓名
                if ((empty($customer->name) || $customer->name === '未知用戶') && !empty($customerData['name'])) {
                    $updateData['name'] = $customerData['name'];
                }
                
                // 執行更新
                if (!empty($updateData)) {
                    $customer->update($updateData);
                    Log::info('Updated customer info from first reservation (legacy flow)', [
                        'customer_id' => $customer->id,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }
            }
            
            // 將預約時填寫的資料保存到上下文中，稍後存到 reservations 表
            $context['customer_data'] = $customerData;
            Cache::put($contextKey, $context, now()->addMinutes(30));
            
            // 完成預約
            $this->completeReservation($replyToken, $context, $customer);
            
            // 清除預約上下文
            Cache::forget($contextKey);
            $this->removeFromActiveKeys($contextKey);
            return;
        }
        
        // 處理分步驟收集
        switch ($context['step']) {
            case 'waiting_customer_info':
                // 當步驟是 waiting_customer_info 時，將其轉換為 waiting_name 步驟
                Log::info('Converting waiting_customer_info to waiting_name step');
                $this->processNameStep($text, $replyToken, $contextKey, $context);
                break;
                
            case 'waiting_name':
                $this->processNameStep($text, $replyToken, $contextKey, $context);
                break;
                
            case 'waiting_phone':
                $this->processPhoneStep($text, $replyToken, $contextKey, $context, $userId);
                break;
                
            case 'waiting_notes':
                $this->processNotesStep($text, $replyToken, $contextKey, $context, $userId);
                break;
                
            default:
                Log::warning('Unknown step in customer info processing', [
                    'step' => $context['step'],
                    'replyToken' => $replyToken
                ]);
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約流程發生錯誤，請重新開始。'
                ]);
                break;
        }
    }

    private function parseCustomerInfo($text)
    {
        $data = [];
        
        // 解析姓名
        if (preg_match('/姓名：(.+)/u', $text, $matches)) {
            $data['name'] = trim($matches[1]);
        } else {
            return null;
        }
        
        // 解析電話
        if (preg_match('/電話：([0-9\-\+\(\)\s]+)/u', $text, $matches)) {
            $data['phone'] = trim($matches[1]);
        } else {
            return null;
        }
        
        // 解析備註（選填）
        if (preg_match('/備註：(.+)/u', $text, $matches)) {
            $data['notes'] = trim($matches[1]);
        }
        
        return $data;
    }

    private function completeReservation($replyToken, $context, $customer)
    {
        LoggingService::logReservationEvent('reservation_completion_start', [
            'customer_id' => $customer->id,
            'service_id' => $context['service_id'] ?? null,
            'time_id' => $context['time_id'] ?? null
        ]);

        // Debug log to check context data
        Log::info('completeReservation called', [
            'context' => $context,
            'customer_id' => $customer->id
        ]);
        
        $service = Service::find($context['service_id']);
        $virtualTimeSlot = $this->findVirtualTimeSlot($context['service_id'], $context['time_id']);
        
        if (!$service || !$virtualTimeSlot) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約資訊錯誤，請重新開始。'
            ]);
            return;
        }
        
        // 最後檢查虛擬時段是否可以預約
        if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '很抱歉，該時段已無法容納此服務，請選擇其他時間。'
            ]);
            return;
        }
        
        try {
            // 更新客戶資料（如果有提供的話）
            if (!empty($context['customer_data'])) {
                $updateData = [];
                
                // 更新客戶姓名（如果為空或預設值）
                if (!empty($context['customer_data']['name']) && 
                    (empty($customer->name) || $customer->name === '未知用戶')) {
                    $updateData['name'] = $context['customer_data']['name'];
                }
                
                // 更新客戶電話（如果為空）
                if (!empty($context['customer_data']['phone']) && empty($customer->phone)) {
                    $updateData['phone'] = $context['customer_data']['phone'];
                }
                
                // 更新客戶備註
                if (!empty($context['customer_data']['notes'])) {
                    $updateData['notes'] = $context['customer_data']['notes'];
                }
                
                // 執行更新
                if (!empty($updateData)) {
                    $customer->update($updateData);
                    Log::info('Updated customer data in completeReservation', [
                        'customer_id' => $customer->id,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }
            }
            
            // 使用資料庫事務來防止並發問題
            $reservation = DB::transaction(function () use ($virtualTimeSlot, $service, $customer, $context) {
                // 在事務內再次檢查虛擬時段是否可以預約（防止並發問題）
                if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
                    throw new \Exception('該時段已無法容納此服務');
                }
                
                // 獲取基礎時段
                $baseTimeSlot = AvailableTime::find($virtualTimeSlot->base_time_slot_id);
                
                // Debug log for customer data
                Log::info('Creating reservation with customer data', [
                    'customer_data' => $context['customer_data'] ?? 'NOT_SET',
                    'customer_name' => $context['customer_data']['name'] ?? 'NOT_SET',
                    'customer_phone' => $context['customer_data']['phone'] ?? 'NOT_SET',
                    'customer_notes' => $context['customer_data']['notes'] ?? 'NOT_SET'
                ]);
                
                // 根據設定決定預約狀態
                $confirmMode = $this->getReservationConfirmMode();
                $status = $confirmMode === 'auto' ? 'confirmed' : 'pending';
                
                // 建立預約，使用虛擬時段的具體時間
                return Reservation::create([
                    'user_id' => null, // LINE Bot 預約不需要管理員 ID
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'available_time_id' => $baseTimeSlot->id,
                    'reservation_date' => Carbon::parse($virtualTimeSlot->start_time)->toDateString(),
                    'reservation_time' => Carbon::parse($virtualTimeSlot->start_time)->format('H:i:s'),
                    'reservation_name' => $context['customer_data']['name'] ?? '',
                    'reservation_phone' => $context['customer_data']['phone'] ?? '',
                    'reservation_notes' => $context['customer_data']['notes'] ?? '',
                    'status' => $status,
                    'notes' => '無',
                ]);
            });
            
            // 注意：不需要更新 current_bookings，因為我們的邏輯是基於實際預約記錄來計算重疊
            // 這樣可以確保時間重疊檢查的準確性
            
            $dateTime = Carbon::parse($virtualTimeSlot->start_time);
            
            // 根據預約狀態決定訊息內容
            $isAutoConfirmed = $reservation->status === 'confirmed';
            $headerText = $isAutoConfirmed ? '預約資料確定' : '預約資料提交';
            $subHeaderText = $isAutoConfirmed ? '請確認以下預約資訊' : '等待管理員確認';
            $headerColor = $isAutoConfirmed ? '#27AE60' : '#F39C12';
            
            // 建構基本的預約資訊欄位
            $infoContents = [
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
                            'text' => '預約日期',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $dateTime->format('Y年m月d日'),
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
                            'text' => '預約時間',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $dateTime->format('H:i'),
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
                            'text' => '客戶姓名',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $context['customer_data']['name'] ?? '',
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
                            'text' => '聯絡電話',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $context['customer_data']['phone'] ?? '',
                            'size' => 'sm',
                            'color' => '#333333',
                            'weight' => 'bold',
                            'flex' => 3
                        ]
                    ]
                ]
            ];

            // 如果有備註，添加備註欄位
            if (!empty($context['customer_data']['notes'])) {
                $infoContents[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '備註事項',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $context['customer_data']['notes'],
                            'size' => 'sm',
                            'color' => '#333333',
                            'weight' => 'bold',
                            'flex' => 3,
                            'wrap' => true
                        ]
                    ]
                ];
            }

            // 使用 Flex Message 呈現預約結果資訊
            $message = [
                'type' => 'flex',
                'altText' => $headerText,
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $headerText,
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => $subHeaderText,
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => $headerColor,
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約編號',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "#{$reservation->id}",
                                        'size' => 'lg',
                                        'color' => '#27AE60',
                                        'weight' => 'bold',
                                        'margin' => 'xs'
                                    ]
                                ],
                                'backgroundColor' => '#F8F9FA',
                                'cornerRadius' => '8px',
                                'paddingAll' => 'md',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
                                'spacing' => 'md',
                                'contents' => $infoContents
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '請選擇操作',
                                'size' => 'sm',
                                'color' => '#999999',
                                'align' => 'center',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'spacing' => 'sm',
                                'margin' => 'lg',
                                'contents' => [
                                    [
                                        'type' => 'button',
                                        'style' => 'primary',
                                        'height' => 'sm',
                                        'color' => '#27AE60',
                                        'flex' => 1,
                                        'action' => [
                                            'type' => 'postback',
                                            'label' => '確定預約',
                                            'data' => "action=confirm_final_reservation&reservation_id={$reservation->id}"
                                        ]
                                    ],
                                    [
                                        'type' => 'button',
                                        'style' => 'secondary',
                                        'height' => 'sm',
                                        'flex' => 1,
                                        'action' => [
                                            'type' => 'postback',
                                            'label' => '重新填寫',
                                            'data' => "action=restart_info_collection&service_id={$service->id}&time_id={$virtualTimeSlot->id}&reservation_id={$reservation->id}"
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => '感謝您的預約，我們將在預約時間前與您確認。',
                                'size' => 'xs',
                                'color' => '#999999',
                                'align' => 'center',
                                'margin' => 'lg',
                                'wrap' => true
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];
            
            $this->replyMessage($replyToken, $message);
            
            // 清理所有相關的預約上下文，避免後續輸入被誤判為客戶資訊
            $this->clearAllReservationContexts();

            LoggingService::logReservationEvent('reservation_completed', [
                'reservation_id' => $reservation->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'reservation_name' => $reservation->reservation_name,
                'reservation_phone' => $reservation->reservation_phone
            ]);
            
        } catch (\Exception $e) {
            LoggingService::logLineBotError('reservation_creation_failed', $customer->line_user_id, $e, [
                'context' => $context,
                'customer_id' => $customer->id,
                'service_id' => $context['service_id'] ?? null,
                'time_id' => $context['time_id'] ?? null
            ]);

            Log::error('Failed to create reservation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => $context,
                'customer_id' => $customer->id ?? 'N/A',
                'service_id' => $context['service_id'] ?? 'N/A',
                'time_id' => $context['time_id'] ?? 'N/A'
            ]);
            
            // 如果是時段衝突，提供更具體的錯誤訊息
            if (strpos($e->getMessage(), '該時段已無法容納') !== false) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '抱歉，該時段剛好被其他客戶預約了，請選擇其他時間。'
                ]);
            } else {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約建立失敗，請稍後再試或聯絡客服。'
                ]);
            }
        }
    }

    private function processNameStep($text, $replyToken, $contextKey, $context)
    {
        // 儲存姓名並要求電話
        $context['customer_data']['name'] = trim($text);
        $context['step'] = 'waiting_phone';
        Cache::put($contextKey, $context, now()->addMinutes(30));
        
        // 同步更新 userId 映射（如果有 userId）
        if (isset($context['user_id']) && $context['user_id']) {
            Cache::put('user_reservation_context_' . $context['user_id'], $contextKey, now()->addMinutes(30));
        }
        
        // 發送第二步：收集電話
        $message = [
            'type' => 'flex',
            'altText' => '第2步：請輸入電話',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '預約資訊填寫',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'lg',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '第 2 步 / 共 3 步',
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#27AE60',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '已完成',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'weight' => 'bold'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'margin' => 'sm',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '姓名：',
                                            'size' => 'sm',
                                            'color' => '#27AE60',
                                            'weight' => 'bold',
                                            'flex' => 0
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $text,
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'margin' => 'sm',
                                            'flex' => 1
                                        ]
                                    ]
                                ]
                            ],
                            'backgroundColor' => '#F8F9FA',
                            'cornerRadius' => '8px',
                            'paddingAll' => 'md'
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'xl'
                        ],
                        [
                            'type' => 'text',
                            'text' => '請輸入您的電話',
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#333333',
                            'margin' => 'xl',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '請直接回覆您的聯絡電話',
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'md',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'xl',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '範例格式',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'weight' => 'bold'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '0912345678、02-12345678',
                                    'size' => 'sm',
                                    'color' => '#27AE60',
                                    'margin' => 'sm'
                                ]
                            ]
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '進度指示',
                            'size' => 'xs',
                            'color' => '#999999',
                            'align' => 'center',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'md',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#E8E8E8',
                                    'cornerRadius' => '6px'
                                ]
                            ]
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '完成',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '電話',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '備註',
                                    'size' => 'xs',
                                    'color' => '#999999',
                                    'flex' => 1,
                                    'align' => 'center'
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'md'
                        ]
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function processPhoneStep($text, $replyToken, $contextKey, $context, $userId)
    {
        // 檢查是否要跳過備註
        if ($text === 'skip_notes') {
            $context['customer_data']['notes'] = ''; // 使用空字串而不是 null
            $this->completeStepByStepReservation($replyToken, $context, $userId);
            Cache::forget($contextKey);
            $this->removeFromActiveKeys($contextKey);
            return;
        }
        
        // 改進的電話格式驗證 - 更寬容但仍有基本檢查
        $cleanText = trim($text);
        
        // 檢查是否包含數字 - 電話號碼應該包含數字
        if (!preg_match('/\d/', $cleanText)) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => "請輸入有效的電話號碼\n\n電話號碼應包含數字\n\n範例格式：\n• 0912345678\n• 02-12345678\n• +886-912345678"
            ]);
            return;
        }
        
        // 檢查長度 - 台灣電話號碼通常至少8位數
        if (strlen(preg_replace('/[^0-9]/', '', $cleanText)) < 8) {
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => "電話號碼長度不足\n\n請輸入完整的電話號碼\n\n範例格式：\n• 0912345678\n• 02-12345678\n• +886-912345678"
            ]);
            return;
        }
        
        // 儲存電話並詢問備註
        $context['customer_data']['phone'] = $cleanText;
        $context['step'] = 'waiting_notes';
        Cache::put($contextKey, $context, now()->addMinutes(30));
        
        // 同步更新 userId 映射（如果有 userId）
        if (isset($context['user_id']) && $context['user_id']) {
            Cache::put('user_reservation_context_' . $context['user_id'], $contextKey, now()->addMinutes(30));
        }
        
        // 發送第三步：收集備註
        $message = [
            'type' => 'flex',
            'altText' => '第3步：備註(選填)',
            'contents' => [
                'type' => 'bubble',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '預約資訊填寫',
                            'weight' => 'bold',
                            'color' => '#ffffff',
                            'size' => 'lg',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '第 3 步 / 最後一步',
                            'color' => '#ffffff',
                            'size' => 'sm',
                            'align' => 'center',
                            'margin' => 'xs'
                        ]
                    ],
                    'backgroundColor' => '#27AE60',
                    'paddingAll' => 'lg'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '已完成資訊',
                                    'size' => 'sm',
                                    'color' => '#999999',
                                    'weight' => 'bold'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'margin' => 'sm',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '姓名：',
                                            'size' => 'sm',
                                            'color' => '#27AE60',
                                            'weight' => 'bold',
                                            'flex' => 0
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $context['customer_data']['name'],
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'margin' => 'sm',
                                            'flex' => 1
                                        ]
                                    ]
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'margin' => 'xs',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '電話：',
                                            'size' => 'sm',
                                            'color' => '#27AE60',
                                            'weight' => 'bold',
                                            'flex' => 0
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $text,
                                            'size' => 'sm',
                                            'color' => '#333333',
                                            'margin' => 'sm',
                                            'flex' => 1
                                        ]
                                    ]
                                ]
                            ],
                            'backgroundColor' => '#F8F9FA',
                            'cornerRadius' => '8px',
                            'paddingAll' => 'md'
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'xl'
                        ],
                        [
                            'type' => 'text',
                            'text' => '特殊需求或備註',
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#333333',
                            'margin' => 'xl',
                            'align' => 'center'
                        ],
                        [
                            'type' => 'text',
                            'text' => '有什麼特殊需求嗎？(選填)',
                            'size' => 'md',
                            'color' => '#666666',
                            'margin' => 'md',
                            'align' => 'center'
                        ]
                    ],
                    'paddingAll' => 'xl'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'md',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '進度指示',
                            'size' => 'xs',
                            'color' => '#999999',
                            'align' => 'center',
                            'margin' => 'none'
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'md',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'contents' => [
                                        [
                                            'type' => 'filler'
                                        ]
                                    ],
                                    'width' => '12px',
                                    'height' => '12px',
                                    'backgroundColor' => '#27AE60',
                                    'cornerRadius' => '6px'
                                ]
                            ]
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'spacing' => 'sm',
                            'margin' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '完成',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '完成',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '備註',
                                    'size' => 'xs',
                                    'color' => '#27AE60',
                                    'weight' => 'bold',
                                    'flex' => 1,
                                    'align' => 'center'
                                ]
                            ]
                        ],
                        [
                            'type' => 'button',
                            'style' => 'secondary',
                            'height' => 'sm',
                            'margin' => 'lg',
                            'action' => [
                                'type' => 'message',
                                'label' => '沒有備註，點擊我完成預約',
                                'text' => 'no_notes'
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => '輸入「取消」可中止預約流程',
                            'size' => 'xs',
                            'color' => '#E74C3C',
                            'align' => 'center',
                            'margin' => 'md'
                        ]
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];

        $this->replyMessage($replyToken, $message);
    }

    private function processNotesStep($text, $replyToken, $contextKey, $context, $userId)
    {
        // 如果沒有上下文，嘗試用 userId 重新檢索
        if (!$context) {
            Log::warning('No context provided to processNotesStep, attempting to retrieve', [
                'userId' => $userId,
                'contextKey' => $contextKey
            ]);
            
            if ($userId) {
                $contextKeyRef = Cache::get('user_reservation_context_' . $userId);
                if ($contextKeyRef) {
                    $context = Cache::get($contextKeyRef);
                    $contextKey = $contextKeyRef;
                }
            }
            
            // 如果還是沒有，嘗試從活動鍵中搜索
            if (!$context) {
                $allKeys = Cache::get('active_reservation_keys', []);
                foreach ($allKeys as $key) {
                    $tempContext = Cache::get($key);
                    if ($tempContext && $tempContext['step'] === 'waiting_notes' && 
                        isset($tempContext['user_id']) && $tempContext['user_id'] === $userId) {
                        $context = $tempContext;
                        $contextKey = $key;
                        break;
                    }
                }
            }
        }
        
        if (!$context) {
            Log::error('No context found in processNotesStep', [
                'userId' => $userId,
                'contextKey' => $contextKey,
                'replyToken' => $replyToken
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '預約資訊已過期，請重新開始預約流程。'
            ]);
            return;
        }
        
        Log::info('Processing notes step', [
            'text' => $text,
            'userId' => $userId,
            'contextKey' => $contextKey,
            'step' => $context['step'] ?? 'unknown'
        ]);
        
        // 處理備註
        if ($text === 'no_notes') {
            $context['customer_data']['notes'] = ''; // 使用空字串而不是 null
            Log::info('User selected no notes');
        } else {
            $context['customer_data']['notes'] = trim($text);
            Log::info('User provided notes', ['notes' => trim($text)]);
        }
        
        // 完成預約
        $this->completeStepByStepReservation($replyToken, $context, $userId);
        Cache::forget($contextKey);
        $this->removeFromActiveKeys($contextKey);
    }

    private function completeStepByStepReservation($replyToken, $context, $userId)
    {
        try {
            // 確保 LINE 客戶記錄存在
            $customer = $this->getOrCreateCustomer($userId, null);
            
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '建立客戶資料時發生錯誤，請稍後再試或聯絡客服。'
                ]);
                return;
            }
            
            // 如果是第一次預約，更新客戶的電話和姓名資訊
            if ($customer->total_reservations == 0) {
                $updateData = [];
                
                // 如果客戶表中沒有電話，更新預約時填寫的電話
                if (empty($customer->phone) && !empty($context['customer_data']['phone'])) {
                    $updateData['phone'] = $context['customer_data']['phone'];
                }
                
                // 如果客戶表中的姓名是預設值或為空，更新預約時填寫的姓名
                if ((empty($customer->name) || $customer->name === '未知用戶') && !empty($context['customer_data']['name'])) {
                    $updateData['name'] = $context['customer_data']['name'];
                }
                
                // 執行更新
                if (!empty($updateData)) {
                    $customer->update($updateData);
                    Log::info('Updated customer info from first reservation', [
                        'customer_id' => $customer->id,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }
            }
            
            // 獲取服務和虛擬時段資訊
            $service = Service::find($context['service_id']);
            $virtualTimeSlot = $this->findVirtualTimeSlot($context['service_id'], $context['time_id']);
            
            if (!$service || !$virtualTimeSlot) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約資訊錯誤，請重新開始。'
                ]);
                return;
            }
            
            // 最後檢查虛擬時段是否可以預約
            if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '很抱歉，該時段已無法容納此服務，請選擇其他時間。'
                ]);
                return;
            }
            
            // Debug log for customer data
            Log::info('Creating step-by-step reservation with customer data', [
                'customer_data' => $context['customer_data'] ?? 'NOT_SET',
                'customer_name' => $context['customer_data']['name'] ?? 'NOT_SET',
                'customer_phone' => $context['customer_data']['phone'] ?? 'NOT_SET',
                'customer_notes' => $context['customer_data']['notes'] ?? 'NOT_SET'
            ]);
            
            // 使用資料庫事務來防止並發問題
            $reservation = DB::transaction(function () use ($virtualTimeSlot, $service, $customer, $context) {
                // 在事務內再次檢查虛擬時段是否可以預約（防止並發問題）
                if (!$this->canBookVirtualTimeSlot($virtualTimeSlot, $service)) {
                    throw new \Exception('該時段已無法容納此服務');
                }
                
                // 獲取基礎時段
                $baseTimeSlot = AvailableTime::find($virtualTimeSlot->base_time_slot_id);
                
                // 根據設定決定預約狀態
                $confirmMode = $this->getReservationConfirmMode();
                $status = $confirmMode === 'auto' ? 'confirmed' : 'pending';
                
                // 建立預約記錄，使用虛擬時段的具體時間和預約時填寫的客戶資料快照
                return Reservation::create([
                    'user_id' => null, // LINE Bot 預約不需要管理員 ID
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'available_time_id' => $baseTimeSlot->id,
                    'reservation_date' => Carbon::parse($virtualTimeSlot->start_time)->toDateString(),
                    'reservation_time' => Carbon::parse($virtualTimeSlot->start_time)->format('H:i:s'),
                    'reservation_name' => $context['customer_data']['name'] ?? '',
                    'reservation_phone' => $context['customer_data']['phone'] ?? '',
                    'reservation_notes' => $context['customer_data']['notes'] ?? '',
                    'status' => $status,
                    'notes' => '無',
                ]);
            });
            
            // 注意：不需要更新 current_bookings，因為我們的邏輯是基於實際預約記錄來計算重疊
            
            $dateTime = Carbon::parse($virtualTimeSlot->start_time);
            
            // 根據預約狀態決定訊息內容
            $isAutoConfirmed = $reservation->status === 'confirmed';
            $headerText = $isAutoConfirmed ? '預約成功！' : '預約提交成功！';
            $subHeaderText = $isAutoConfirmed ? '您的預約已確認' : '等待確認中';
            $headerColor = $isAutoConfirmed ? '#27AE60' : '#F39C12';
            
            // 發送預約結果的訊息
            $message = [
                'type' => 'flex',
                'altText' => $headerText,
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $headerText,
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => $subHeaderText,
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => $headerColor,
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約編號',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "#{$reservation->id}",
                                        'size' => 'lg',
                                        'color' => '#27AE60',
                                        'weight' => 'bold',
                                        'margin' => 'xs'
                                    ]
                                ],
                                'backgroundColor' => '#F8F9FA',
                                'cornerRadius' => '8px',
                                'paddingAll' => 'md',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
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
                                                'text' => '預約日期',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $dateTime->format('Y年m月d日'),
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
                                                'text' => '預約時間',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $dateTime->format('H:i'),
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
                                                'text' => '客戶姓名',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $context['customer_data']['name'] ?? $customer->name,
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
                                                'text' => '聯絡電話',
                                                'size' => 'sm',
                                                'color' => '#666666',
                                                'flex' => 2
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $context['customer_data']['phone'] ?? $customer->phone,
                                                'size' => 'sm',
                                                'color' => '#333333',
                                                'weight' => 'bold',
                                                'flex' => 3
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'text',
                                'text' => '感謝您的預約！我們會在預約時間前與您確認。如需要修改或取消，請輸入「查詢預約」查看您的預約記錄。',
                                'size' => 'sm',
                                'color' => '#666666',
                                'margin' => 'xl',
                                'wrap' => true,
                                'align' => 'center'
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '查看我的預約',
                                    'text' => '查詢預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];
            
            // 如果有備註，添加備註欄位
            if (!empty($context['customer_data']['notes'])) {
                // 在聯絡電話後面插入備註欄位
                $notesBox = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '備註事項',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => $context['customer_data']['notes'],
                            'size' => 'sm',
                            'color' => '#333333',
                            'weight' => 'bold',
                            'flex' => 3,
                            'wrap' => true
                        ]
                    ]
                ];
                
                // 插入到預約詳細資訊中
                array_splice($message['contents']['body']['contents'][2]['contents'], 5, 0, [$notesBox]);
            }
            
            $this->replyMessage($replyToken, $message);
            
            // 清理所有相關的預約上下文，避免預約成功後繼續進入填寫流程
            $this->clearAllReservationContexts();
            
        } catch (\Exception $e) {
            Log::error('Failed to complete step-by-step reservation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => $context,
                'userId' => $userId
            ]);
            
            // 如果是時段衝突，提供更具體的錯誤訊息
            if (strpos($e->getMessage(), '該時段已無法容納') !== false) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '抱歉，該時段剛好被其他客戶預約了，請選擇其他時間。'
                ]);
            } else {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約處理失敗，請稍後再試或聯絡客服。'
                ]);
            }
        }
    }

    private function handleFinalReservationConfirmation($replyToken, $reservationId)
    {
        try {
            $reservation = Reservation::find($reservationId);
            
            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到預約記錄，請重新開始預約流程。'
                ]);
                return;
            }

            // 可以在這裡更新預約狀態為已確認
            $reservation->update(['status' => 'confirmed']);

            // 使用 Flex Message 呈現確認完成訊息
            $message = [
                'type' => 'flex',
                'altText' => '預約確認完成',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '預約確認完成',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => '感謝您的確認',
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約編號',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "#{$reservation->id}",
                                        'size' => 'lg',
                                        'color' => '#27AE60',
                                        'weight' => 'bold',
                                        'margin' => 'xs'
                                    ]
                                ],
                                'backgroundColor' => '#F8F9FA',
                                'cornerRadius' => '8px',
                                'paddingAll' => 'md',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'text',
                                'text' => '我們已收到您的預約確認，將在預約時間前再次與您聯絡確認。',
                                'size' => 'md',
                                'color' => '#333333',
                                'margin' => 'xl',
                                'wrap' => true,
                                'align' => 'center'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'text',
                                'text' => '如需取消或修改預約，請聯繫我們的客服。',
                                'size' => 'sm',
                                'color' => '#666666',
                                'margin' => 'lg',
                                'wrap' => true,
                                'align' => 'center'
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

            // 清理所有相關的預約上下文，確保預約確認後不會再進入填寫流程
            $this->clearAllReservationContexts();

        } catch (\Exception $e) {
            Log::error('Failed to confirm final reservation', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '確認預約時發生錯誤，請聯繫客服處理。'
            ]);
        }
    }

    private function handleRestartInfoCollection($replyToken, $data, $userId)
    {
        try {
            // 如果有舊的預約記錄，先刪除或標記為取消
            if (isset($data['reservation_id'])) {
                $oldReservation = Reservation::find($data['reservation_id']);
                if ($oldReservation) {
                    // 注意：不需要調整 current_bookings，因為我們的邏輯基於實際預約記錄
                    
                    // 刪除預約記錄
                    $oldReservation->delete();
                }
            }

            // 清除可能存在的上下文
            $activeKeys = Cache::get('active_reservation_keys', []);
            foreach ($activeKeys as $key) {
                Cache::forget($key);
            }
            Cache::put('active_reservation_keys', [], now()->addHour());

            // 重新開始資訊收集流程
            $this->startInfoCollection($replyToken, $data['service_id'], $data['time_id']);

        } catch (\Exception $e) {
            Log::error('Failed to restart info collection', [
                'error' => $e->getMessage(),
                'data' => $data,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '重新填寫時發生錯誤，請稍後再試或重新開始預約。'
            ]);
        }
    }

    private function handleEditReservation($replyToken, $reservationId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service', 'availableTime'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 檢查預約是否可以編輯（例如：預約時間至少在24小時後）
            // 使用模型的輔助方法獲取完整的預約日期時間
            $reservationDateTime = $reservation->getReservationDateTime();
            if ($reservationDateTime->isBefore(now()->addHours(24))) {
                $this->replyMessage($replyToken, [
                    'type' => 'flex',
                    'altText' => '編輯限制',
                    'contents' => [
                        'type' => 'bubble',
                        'header' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '無法編輯',
                                    'weight' => 'bold',
                                    'color' => '#ffffff',
                                    'size' => 'xl',
                                    'align' => 'center'
                                ]
                            ],
                            'backgroundColor' => '#E74C3C',
                            'paddingAll' => 'lg'
                        ],
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '預約時間過近',
                                    'size' => 'lg',
                                    'color' => '#333333',
                                    'align' => 'center',
                                    'weight' => 'bold'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '預約時間在24小時內無法編輯，如需更改請聯繫客服。',
                                    'size' => 'md',
                                    'color' => '#666666',
                                    'margin' => 'lg',
                                    'align' => 'center',
                                    'wrap' => true
                                ]
                            ],
                            'paddingAll' => 'xl'
                        ]
                    ]
                ]);
                return;
            }

            // 顯示編輯選項
            $message = [
                'type' => 'flex',
                'altText' => '編輯預約',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '編輯預約',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "預約編號 #{$reservation->id}",
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '請選擇要編輯的項目',
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
                                'spacing' => 'md',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '目前預約資訊：',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "服務：{$reservation->service->name}",
                                        'size' => 'sm',
                                        'color' => '#666666',
                                        'margin' => 'sm'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "時間：{$reservationDateTime->format('Y年m月d日 H:i')}",
                                        'size' => 'sm',
                                        'color' => '#666666'
                                    ]
                                ]
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '更改服務項目',
                                    'data' => "action=edit_service&reservation_id={$reservation->id}"
                                ]
                            ],
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '更改預約時間',
                                    'data' => "action=edit_time&reservation_id={$reservation->id}&service_id={$reservation->service_id}"
                                ]
                            ],
                            [
                                'type' => 'button',
                                'style' => 'secondary',
                                'height' => 'sm',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '返回預約查詢',
                                    'text' => '查詢預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to handle edit reservation', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '編輯預約時發生錯誤，請稍後再試。'
            ]);
        }
    }

    private function handleCancelReservation($replyToken, $reservationId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service', 'availableTime'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 檢查預約狀態和時間限制
            if ($reservation->status === 'cancelled') {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '此預約已被取消。'
                ]);
                return;
            }
            
            // 計算距離預約時間的小時數
            $reservationDateTime = $reservation->getReservationDateTime();
            $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);
            
            Log::info('Cancel reservation time check', [
                'reservation_id' => $reservationId,
                'reservation_datetime' => $reservationDateTime->format('Y-m-d H:i:s'),
                'current_time' => now()->format('Y-m-d H:i:s'),
                'hours_until_reservation' => $hoursUntilReservation,
                'status' => $reservation->status
            ]);
            
            // 檢查是否可以取消
            if ($reservation->status === 'confirmed' && $hoursUntilReservation <= 24) {
                $this->replyMessage($replyToken, [
                    'type' => 'flex',
                    'altText' => '無法取消預約',
                    'contents' => [
                        'type' => 'bubble',
                        'header' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '無法取消預約',
                                    'weight' => 'bold',
                                    'color' => '#ffffff',
                                    'size' => 'xl',
                                    'align' => 'center'
                                ]
                            ],
                            'backgroundColor' => '#E74C3C',
                            'paddingAll' => 'lg'
                        ],
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '預約時間在24小時內，無法自行取消',
                                    'size' => 'lg',
                                    'color' => '#333333',
                                    'align' => 'center',
                                    'weight' => 'bold',
                                    'margin' => 'md'
                                ],
                                [
                                    'type' => 'separator',
                                    'margin' => 'xl'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '距離預約時間不足24小時的已確認預約如需修改或取消，請聯絡客服處理。我們會盡快為您安排。',
                                    'size' => 'sm',
                                    'color' => '#666666',
                                    'margin' => 'xl',
                                    'wrap' => true,
                                    'align' => 'center'
                                ]
                            ],
                            'paddingAll' => 'xl'
                        ],
                        'footer' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'button',
                                    'style' => 'secondary',
                                    'height' => 'sm',
                                    'action' => [
                                        'type' => 'message',
                                        'label' => '返回預約查詢',
                                        'text' => '查詢預約'
                                    ]
                                ]
                            ],
                            'paddingAll' => 'lg'
                        ]
                    ]
                ]);
                return;
            }

            // 使用模型的輔助方法獲取完整的預約日期時間
            $reservationDateTime = $reservation->getReservationDateTime();

            // 顯示取消確認
            $message = [
                'type' => 'flex',
                'altText' => '取消預約確認',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '取消預約',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "預約編號 #{$reservation->id}",
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#E74C3C',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '確定要取消此預約嗎？',
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
                                'spacing' => 'md',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '預約資訊：',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "服務：{$reservation->service->name}",
                                        'size' => 'sm',
                                        'color' => '#666666',
                                        'margin' => 'sm'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "時間：{$reservationDateTime->format('Y年m月d日 H:i')}",
                                        'size' => 'sm',
                                        'color' => '#666666'
                                    ]
                                ]
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'text',
                                'text' => '取消後將無法復原',
                                'size' => 'sm',
                                'color' => '#E74C3C',
                                'margin' => 'lg',
                                'align' => 'center',
                                'weight' => 'bold'
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'spacing' => 'sm',
                                'contents' => [
                                    [
                                        'type' => 'button',
                                        'style' => 'primary',
                                        'height' => 'sm',
                                        'color' => '#E74C3C',
                                        'flex' => 1,
                                        'action' => [
                                            'type' => 'postback',
                                            'label' => '確定取消',
                                            'data' => "action=confirm_cancel&reservation_id={$reservation->id}"
                                        ]
                                    ],
                                    [
                                        'type' => 'button',
                                        'style' => 'secondary',
                                        'height' => 'sm',
                                        'flex' => 1,
                                        'action' => [
                                            'type' => 'message',
                                            'label' => '不要取消',
                                            'text' => '查詢預約'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to handle cancel reservation', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '取消預約時發生錯誤，請稍後再試。'
            ]);
        }
    }

    private function handleConfirmCancel($replyToken, $reservationId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service', 'availableTime'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 檢查預約狀態和時間限制
            if ($reservation->status === 'cancelled') {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '此預約已被取消。'
                ]);
                return;
            }
            
            // 計算距離預約時間的小時數
            $reservationDateTime = $reservation->getReservationDateTime();
            $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);
            
            Log::info('Confirm cancel time check', [
                'reservation_id' => $reservationId,
                'reservation_datetime' => $reservationDateTime->format('Y-m-d H:i:s'),
                'current_time' => now()->format('Y-m-d H:i:s'),
                'hours_until_reservation' => $hoursUntilReservation,
                'status' => $reservation->status
            ]);
            
            // 檢查是否可以取消
            if ($reservation->status === 'confirmed' && $hoursUntilReservation <= 24) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '預約時間在24小時內的已確認預約無法自行取消，請聯絡客服處理。'
                ]);
                return;
            }

            // 注意：不需要調整 current_bookings，因為我們的邏輯基於實際預約記錄
            // 取消的預約會被標記為 'cancelled' 狀態，在計算重疊時會被排除

            // 更新預約狀態為已取消
            $reservation->update(['status' => 'cancelled']);

            // 使用模型的輔助方法獲取完整的預約日期時間
            $reservationDateTime = $reservation->getReservationDateTime();

            // 使用 Flex Message 顯示取消成功訊息
            $message = [
                'type' => 'flex',
                'altText' => '預約已取消',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '預約已取消',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "預約編號 #{$reservation->id}",
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '您的預約已成功取消',
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'xl',
                                'spacing' => 'md',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '已取消的預約：',
                                        'size' => 'sm',
                                        'color' => '#999999',
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "服務：{$reservation->service->name}",
                                        'size' => 'sm',
                                        'color' => '#666666',
                                        'margin' => 'sm'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "時間：{$reservationDateTime->format('Y年m月d日 H:i')}",
                                        'size' => 'sm',
                                        'color' => '#666666'
                                    ]
                                ]
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'xl'
                            ],
                            [
                                'type' => 'text',
                                'text' => '感謝您的使用，如需要可以重新預約。',
                                'size' => 'sm',
                                'color' => '#666666',
                                'margin' => 'lg',
                                'align' => 'center',
                                'wrap' => true
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'height' => 'sm',
                                'color' => '#27AE60',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '重新預約',
                                    'text' => '我要預約'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'style' => 'secondary',
                                'height' => 'sm',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '查看其他預約',
                                    'text' => '查詢預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to confirm cancel reservation', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '取消預約時發生錯誤，請稍後再試或聯繫客服。'
            ]);
        }
    }

    /**
     * 計算服務需要佔用的所有時段
     * 根據服務時長和開始時段，計算需要佔用的連續時段
     */
    private function calculateRequiredTimeSlots($service, $startAvailableTime)
    {
        $serviceDurationMinutes = $service->duration; // 服務時長（分鐘）
        $startTime = Carbon::parse($startAvailableTime->start_time);
        $endTime = $startTime->copy()->addMinutes($serviceDurationMinutes);
        
        // 查找所有可能被影響的時段
        $affectedTimeSlots = AvailableTime::where('is_active', true)
            ->where(function($query) use ($startTime, $endTime) {
                // 時段開始時間在服務時間範圍內
                $query->whereBetween('start_time', [$startTime, $endTime])
                    // 或者時段結束時間在服務時間範圍內  
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    // 或者時段完全包含服務時間
                    ->orWhere(function($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                          ->where('end_time', '>=', $endTime);
                    });
            })
            ->orderBy('start_time')
            ->get();
            
        return $affectedTimeSlots;
    }
    
    /**
     * 檢查是否可以預約服務（舊版邏輯，建議使用虛擬時段系統）
     * @deprecated 請使用 canBookVirtualTimeSlot 方法
     */
    private function canBookService($service, $startAvailableTime)
    {
        // 這個函數已廢棄，請使用虛擬時段邏輯
        return false;
    }
    
    /**
     * 預約服務時更新所有相關時段的預約數量（舊版邏輯）
     * @deprecated 請使用虛擬時段系統
     */
    private function bookServiceTimeSlots($service, $startAvailableTime)
    {
        // 這個函數已廢棄，不再使用 current_bookings 更新
        return [];
    }
    
    /**
     * 取消預約時恢復所有相關時段的預約數量（舊版邏輯）
     * @deprecated 預約狀態的變更不需要手動調整 current_bookings
     */
    private function releaseServiceTimeSlots($reservation)
    {
        // 這個函數已廢棄，不再使用 current_bookings 更新
        return [];
    }
    
    /**
     * 獲取適合特定服務的可用時段
     * 從 available_times 表讀取時段，根據服務時長檢查是否可容納
     */
    private function getAvailableTimeSlotsForService($serviceId, $lastEndTime = null)
    {
        try {
            Log::info('getAvailableTimeSlotsForService called', [
                'service_id' => $serviceId,
                'last_end_time' => $lastEndTime
            ]);
            
            $service = Service::find($serviceId);
            if (!$service) {
                Log::warning('Service not found in getAvailableTimeSlotsForService', [
                    'service_id' => $serviceId
                ]);
                return collect();
            }
            
            // 首先檢查所有 available_times 記錄
            $allRecords = AvailableTime::all();
            Log::info('Total available_times records in database', [
                'total_count' => $allRecords->count(),
                'records' => $allRecords->map(function($record) {
                    return [
                        'id' => $record->id,
                        'start_time' => $record->start_time,
                        'end_time' => $record->end_time,
                        'is_active' => $record->is_active,
                        'created_at' => $record->created_at
                    ];
                })->toArray()
            ]);
            
            // 檢查當前時間
            $currentTime = now();
            Log::info('Current time check', [
                'current_time' => $currentTime->toDateTimeString(),
                'timezone' => $currentTime->getTimezone()->getName()
            ]);
            
            // 從 available_times 表獲取可用時段
            $query = AvailableTime::where('is_active', true)
                ->where('start_time', '>=', now());
            
            // 如果有 lastEndTime，只獲取該時間之後的時段
            if ($lastEndTime) {
                $query->where('start_time', '>=', $lastEndTime);
            }
            
            $availableTimeSlots = $query->orderBy('start_time')->get();
            
            Log::info('Available time slots from database', [
                'slots_count' => $availableTimeSlots->count(),
                'sql_query' => $query->toSql(),
                'query_bindings' => $query->getBindings()
            ]);
            
            $availableSlots = collect();
            
            foreach ($availableTimeSlots as $timeSlot) {
                // 檢查該時段是否能容納指定的服務
                $virtualSlots = $this->generateVirtualSlotsFromTimeSlot($timeSlot, $service);
                $availableSlots = $availableSlots->merge($virtualSlots);
            }
            
            Log::info('Total virtual slots generated', [
                'total_count' => $availableSlots->count()
            ]);
            
            return $availableSlots;
            
        } catch (\Exception $e) {
            Log::error('Error in getAvailableTimeSlotsForService', [
                'service_id' => $serviceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect();
        }
    }
    
    /**
     * 獲取連續預約的可用時段
     * 用於支援連續多個服務的預約
     */
    public function getAvailableTimeSlotsForContinuousBooking($serviceId, $lastEndTime)
    {
        $service = Service::find($serviceId);
        if (!$service || !$lastEndTime) {
            return collect();
        }
        
        // 只查找從 lastEndTime 開始的當天可用時段
        $date = $lastEndTime->copy()->startOfDay();
        return $this->getDailyAvailableSlots($date, $service, $lastEndTime);
    }
    
    /**
     * 從 AvailableTime 時段生成虛擬時段
     * 根據服務時長，在 available_time 的時間範圍內生成可能的預約時段
     */
    private function generateVirtualSlotsFromTimeSlot($timeSlot, $service)
    {
        $virtualSlots = collect();
        $interval = 30; // 30分鐘間隔
        
        $slotStart = Carbon::parse($timeSlot->start_time);
        $slotEnd = Carbon::parse($timeSlot->end_time);
        $serviceDuration = $service->duration;
        
        Log::info('Generating virtual slots from time slot', [
            'time_slot_id' => $timeSlot->id,
            'slot_start' => $slotStart->format('Y-m-d H:i:s'),
            'slot_end' => $slotEnd->format('Y-m-d H:i:s'),
            'service_duration' => $serviceDuration
        ]);
        
        $currentTime = $slotStart->copy();
        
        // 以30分鐘為間隔生成可能的開始時間
        while ($currentTime->copy()->addMinutes($serviceDuration) <= $slotEnd) {
            $serviceEndTime = $currentTime->copy()->addMinutes($serviceDuration);
            
            // 檢查這個時段是否已被預約
            if ($this->isTimeSlotAvailable($currentTime, $serviceEndTime, $timeSlot)) {
                // 創建虛擬時段對象
                $virtualSlot = new \stdClass();
                $virtualSlot->id = $timeSlot->id . '_' . $currentTime->format('Hi');
                $virtualSlot->base_time_slot_id = $timeSlot->id;
                $virtualSlot->title = $timeSlot->title;
                $virtualSlot->description = $timeSlot->description;
                $virtualSlot->start_time = $currentTime->toDateTimeString();
                $virtualSlot->end_time = $serviceEndTime->toDateTimeString();
                $virtualSlot->display_time = $currentTime->format('H:i') . ' - ' . $serviceEndTime->format('H:i');
                $virtualSlot->date = $currentTime->format('Y-m-d');
                $virtualSlot->max_capacity = $timeSlot->max_capacity;
                $virtualSlot->is_active = $timeSlot->is_active;
                
                $virtualSlots->push($virtualSlot);
                
                Log::info('Generated virtual slot', [
                    'virtual_slot_id' => $virtualSlot->id,
                    'start_time' => $virtualSlot->start_time,
                    'end_time' => $virtualSlot->end_time
                ]);
            }
            
            $currentTime->addMinutes($interval);
        }
        
        return $virtualSlots;
    }
    
    /**
     * 檢查指定時間段是否可用（沒有重疊的預約）
     */
    private function isTimeSlotAvailable($startTime, $endTime, $baseTimeSlot)
    {
        $dateStr = $startTime->format('Y-m-d');
        
        // 獲取該基礎時段內的所有預約
        $existingReservations = Reservation::whereHas('availableTime', function($query) use ($baseTimeSlot) {
                $query->where('id', $baseTimeSlot->id);
            })
            ->where('reservation_date', $dateStr)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();
        
        // 檢查是否有時間重疊
        foreach ($existingReservations as $reservation) {
            // 使用模型的輔助方法獲取完整的預約日期時間
            try {
                $reservationStart = $reservation->getReservationDateTime();
                $reservationEnd = $reservationStart->copy()->addMinutes($reservation->service->duration);
                
                // 檢查時間重疊：新時段開始 < 現有結束 AND 新時段結束 > 現有開始
                if ($startTime->lt($reservationEnd) && $endTime->gt($reservationStart)) {
                    Log::info('Time slot conflict detected', [
                        'existing_reservation_id' => $reservation->id,
                        'existing_start' => $reservationStart->format('Y-m-d H:i:s'),
                        'existing_end' => $reservationEnd->format('Y-m-d H:i:s'),
                        'new_start' => $startTime->format('Y-m-d H:i:s'),
                        'new_end' => $endTime->format('Y-m-d H:i:s')
                    ]);
                    return false; // 有重疊，不可用
                }
            } catch (\Exception $e) {
                Log::error('Error parsing reservation time', [
                    'reservation_id' => $reservation->id,
                    'reservation_date' => $reservation->reservation_date,
                    'reservation_time' => $reservation->reservation_time,
                    'error' => $e->getMessage()
                ]);
                // 如果無法解析時間，為了安全起見，假設有衝突
                return false;
            }
        }
        
        return true; // 無重疊，可用
    }
    
    /**
     * 獲取指定日期的可預約時段
     */
    private function getDailyAvailableSlots($date, $service, $lastEndTime = null)
    {
        try {
            Log::info('getDailyAvailableSlots called', [
                'date' => $date->format('Y-m-d'),
                'service_id' => $service->id,
                'service_duration' => $service->duration,
                'last_end_time' => $lastEndTime ? $lastEndTime->format('Y-m-d H:i:s') : null
            ]);
            
            $availableSlots = collect();
            
            // 定義營業時間 (可以從資料庫設定讀取，這裡先寫死)
            $businessStartTime = '08:00';
            $businessEndTime = '18:00';
            
            // 從設定中讀取營業時間（如果有的話）
            $startTimeSetting = Setting::get('business_start_time');
            $endTimeSetting = Setting::get('business_end_time');
            
            if ($startTimeSetting) $businessStartTime = $startTimeSetting;
            if ($endTimeSetting) $businessEndTime = $endTimeSetting;
            
            Log::info('Business hours', [
                'start_time' => $businessStartTime,
                'end_time' => $businessEndTime
            ]);
            
            // 建立當天的開始和結束時間
            $dayStart = Carbon::parse($date->format('Y-m-d') . ' ' . $businessStartTime);
            $dayEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $businessEndTime);
            
            // 如果是今天，確保開始時間不早於現在
            if ($date->isToday()) {
                $now = now();
                if ($dayStart->lt($now)) {
                    // 調整到下一個整點或半點
                    $minutes = $now->minute;
                    if ($minutes <= 30) {
                        $dayStart = $now->copy()->minute(30)->second(0);
                    } else {
                        $dayStart = $now->copy()->addHour()->minute(0)->second(0);
                    }
                }
            }
            
            // 如果有上次結束時間且是同一天，從該時間開始
            if ($lastEndTime && $lastEndTime->isSameDay($dayStart)) {
                $dayStart = $lastEndTime->copy();
            }
            
            Log::info('Time range for slot generation', [
                'day_start' => $dayStart->format('Y-m-d H:i:s'),
                'day_end' => $dayEnd->format('Y-m-d H:i:s')
            ]);
            
            // 生成該日期的所有可能時段
            $timeSlots = $this->generateTimeSlots($dayStart, $dayEnd, $service->duration);
            
            Log::info('Generated time slots', [
                'slots_count' => $timeSlots->count()
            ]);
            
            // 過濾掉已被預約的時段
            $availableTimeSlots = $this->filterBookedSlots($timeSlots, $date, $service);
            
            Log::info('Available time slots after filtering', [
                'available_count' => $availableTimeSlots->count()
            ]);
            
            return $availableTimeSlots;
            
        } catch (\Exception $e) {
            Log::error('Error in getDailyAvailableSlots', [
                'date' => $date ? $date->format('Y-m-d') : 'null',
                'service_id' => $service ? $service->id : 'null',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect();
        }
    }
    
    /**
     * 生成指定時間範圍內的所有可能時段
     */
    private function generateTimeSlots($startTime, $endTime, $serviceDuration)
    {
        $slots = collect();
        $interval = 30; // 30分鐘間隔
        
        $currentTime = $startTime->copy();
        
        while ($currentTime->copy()->addMinutes($serviceDuration) <= $endTime) {
            $slotEndTime = $currentTime->copy()->addMinutes($serviceDuration);
            
            // 創建虛擬時段對象
            $virtualSlot = new \stdClass();
            $virtualSlot->id = $currentTime->format('YmdHi');
            $virtualSlot->start_time = $currentTime->toDateTimeString();
            $virtualSlot->end_time = $slotEndTime->toDateTimeString();
            $virtualSlot->display_time = $currentTime->format('H:i') . ' - ' . $slotEndTime->format('H:i');
            $virtualSlot->date = $currentTime->format('Y-m-d');
            
            $slots->push($virtualSlot);
            $currentTime->addMinutes($interval);
        }
        
        return $slots;
    }
    
    /**
     * 過濾掉已被預約的時段
     */
    private function filterBookedSlots($timeSlots, $date, $service)
    {
        $dateStr = $date->format('Y-m-d');
        
        // 獲取該日期的所有預約
        $existingReservations = Reservation::where('reservation_date', $dateStr)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();
        
        return $timeSlots->filter(function($slot) use ($existingReservations) {
            $slotStart = Carbon::parse($slot->start_time);
            $slotEnd = Carbon::parse($slot->end_time);
            
            // 檢查是否與現有預約重疊
            foreach ($existingReservations as $reservation) {
                try {
                    // 使用模型的輔助方法獲取完整的預約日期時間
                    $reservationStart = $reservation->getReservationDateTime();
                    $reservationEnd = $reservationStart->copy()->addMinutes($reservation->service->duration);
                    
                    // 檢查時間重疊：新時段開始 < 現有結束 AND 新時段結束 > 現有開始
                    if ($slotStart->lt($reservationEnd) && $slotEnd->gt($reservationStart)) {
                        return false; // 有重疊，排除此時段
                    }
                } catch (\Exception $e) {
                    Log::error('Error parsing reservation time in filterBookedSlots', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage()
                    ]);
                    return false; // 為了安全起見，排除此時段
                }
            }
            
            return true; // 無重疊，保留此時段
        });
    }
    
    /**
     * 為時段生成可能的開始時間（已廢棄）
     * @deprecated 請使用新的時段生成邏輯
     */
    private function generatePossibleStartTimes($timeSlot, $serviceDuration)
    {
        // 這個方法已廢棄，保留是為了向後兼容
        return collect();
    }
    
    /**
     * 創建虛擬時段對象（已廢棄）
     * @deprecated 請使用新的時段生成邏輯
     */
    private function createVirtualTimeSlot($baseTimeSlot, $startTime, $serviceDuration)
    {
        // 這個方法已廢棄，保留是為了向後兼容
        $virtualSlot = new \stdClass();
        $virtualSlot->id = $startTime->format('YmdHi');
        $virtualSlot->start_time = $startTime->toDateTimeString();
        $virtualSlot->end_time = $startTime->copy()->addMinutes($serviceDuration)->toDateTimeString();
        return $virtualSlot;
    }
    
    /**
     * 檢查虛擬時段是否可預約（簡化版）
     * 用於防止並發問題的最後檢查
     */
    private function canBookVirtualTimeSlot($virtualTimeSlot, $service)
    {
        // 檢查時段是否已被預約（並發檢查）
        $startTime = Carbon::parse($virtualTimeSlot->start_time);
        $endTime = Carbon::parse($virtualTimeSlot->end_time);
        $dateStr = $startTime->format('Y-m-d');
        
        $existingReservations = Reservation::where('reservation_date', $dateStr)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();
        
        foreach ($existingReservations as $reservation) {
            try {
                // 使用模型的輔助方法獲取完整的預約日期時間
                $reservationStart = $reservation->getReservationDateTime();
                $reservationEnd = $reservationStart->copy()->addMinutes($reservation->service->duration);
                
                // 檢查時間重疊
                if ($startTime->lt($reservationEnd) && $endTime->gt($reservationStart)) {
                    return false; // 有重疊，不能預約
                }
            } catch (\Exception $e) {
                Log::error('Error parsing reservation time in canBookVirtualTimeSlot', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage()
                ]);
                return false; // 為了安全起見，假設有衝突
            }
        }
        
        return true; // 無重疊，可以預約
    }
    
    /**
     * 計算指定時間範圍內的預約數量（已廢棄）
     * @deprecated 已整合到新的時段過濾邏輯中
     */
    private function getBookingsForTimeRange($baseTimeSlot, $startTime, $duration)
    {
        // 這個方法已廢棄，保留是為了向後兼容
        return 0;
    }
    
    /**
     * 根據虛擬時段 ID 找到對應的虛擬時段對象
     * 支援兩種格式:
     * 1. 新格式: base_time_slot_id_HHMM (例如: 1_0800)
     * 2. 舊格式: YmdHi (例如: 202501180800) - 向後兼容
     */
    private function findVirtualTimeSlot($serviceId, $virtualTimeSlotId)
    {
        $service = Service::find($serviceId);
        if (!$service) {
            Log::warning('Service not found in findVirtualTimeSlot', [
                'service_id' => $serviceId
            ]);
            return null;
        }
        
        Log::info('Finding virtual time slot', [
            'service_id' => $serviceId,
            'virtual_time_slot_id' => $virtualTimeSlotId
        ]);
        
        // 檢查是否為舊格式 (12位數字)
        if (strlen($virtualTimeSlotId) === 12 && is_numeric($virtualTimeSlotId)) {
            return $this->findVirtualTimeSlotOldFormat($service, $virtualTimeSlotId);
        }
        
        // 新格式: 解析虛擬時段 ID: base_time_slot_id_HHMM
        $parts = explode('_', $virtualTimeSlotId);
        if (count($parts) < 2) {
            Log::warning('Invalid virtual time slot ID format', [
                'virtual_time_slot_id' => $virtualTimeSlotId,
                'expected_format' => 'base_time_slot_id_HHMM or YmdHi'
            ]);
            return null;
        }
        
        $baseTimeSlotId = $parts[0];
        $timeCode = end($parts); // 取最後一個部分作為時間代碼
        
        // 查找基礎時段
        $baseTimeSlot = AvailableTime::where('id', $baseTimeSlotId)
            ->where('is_active', true)
            ->first();
            
        if (!$baseTimeSlot) {
            Log::warning('Base time slot not found', [
                'base_time_slot_id' => $baseTimeSlotId
            ]);
            return null;
        }
        
        // 解析時間代碼 (HHMM 格式)
        if (strlen($timeCode) !== 4) {
            Log::warning('Invalid time code format', [
                'time_code' => $timeCode,
                'expected_format' => 'HHMM'
            ]);
            return null;
        }
        
        $hour = intval(substr($timeCode, 0, 2));
        $minute = intval(substr($timeCode, 2, 2));
        
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            Log::warning('Invalid time values', [
                'hour' => $hour,
                'minute' => $minute
            ]);
            return null;
        }
        
        // 組合完整的開始時間
        $baseDate = Carbon::parse($baseTimeSlot->start_time)->format('Y-m-d');
        $startTime = Carbon::parse($baseDate . ' ' . sprintf('%02d:%02d:00', $hour, $minute));
        $endTime = $startTime->copy()->addMinutes($service->duration);
        
        // 驗證時間是否在基礎時段範圍內
        $baseStart = Carbon::parse($baseTimeSlot->start_time);
        $baseEnd = Carbon::parse($baseTimeSlot->end_time);
        
        if ($startTime->lt($baseStart) || $endTime->gt($baseEnd)) {
            Log::warning('Virtual slot time outside base time slot range', [
                'virtual_start' => $startTime->format('Y-m-d H:i:s'),
                'virtual_end' => $endTime->format('Y-m-d H:i:s'),
                'base_start' => $baseStart->format('Y-m-d H:i:s'),
                'base_end' => $baseEnd->format('Y-m-d H:i:s')
            ]);
            return null;
        }
        
        // 檢查時段可用性
        if (!$this->isTimeSlotAvailable($startTime, $endTime, $baseTimeSlot)) {
            Log::info('Time slot not available (already booked)');
            return null;
        }
        
        // 創建虛擬時段對象
        $virtualSlot = new \stdClass();
        $virtualSlot->id = $virtualTimeSlotId;
        $virtualSlot->base_time_slot_id = $baseTimeSlotId;
        $virtualSlot->title = $baseTimeSlot->title;
        $virtualSlot->description = $baseTimeSlot->description;
        $virtualSlot->start_time = $startTime->toDateTimeString();
        $virtualSlot->end_time = $endTime->toDateTimeString();
        $virtualSlot->display_time = $startTime->format('H:i') . ' - ' . $endTime->format('H:i');
        $virtualSlot->date = $startTime->format('Y-m-d');
        $virtualSlot->max_capacity = $baseTimeSlot->max_capacity;
        $virtualSlot->is_active = $baseTimeSlot->is_active;
        
        Log::info('Virtual time slot found successfully', [
            'virtual_slot_id' => $virtualSlot->id,
            'start_time' => $virtualSlot->start_time,
            'end_time' => $virtualSlot->end_time
        ]);
        
        return $virtualSlot;
    }
    
    /**
     * 處理舊格式的虛擬時段 ID (向後兼容)
     * 舊格式: YmdHi (例如: 202501180800)
     */
    private function findVirtualTimeSlotOldFormat($service, $virtualTimeSlotId)
    {
        Log::info('Using old format compatibility for virtual time slot', [
            'virtual_time_slot_id' => $virtualTimeSlotId
        ]);
        
        // 解析日期和時間
        $year = intval(substr($virtualTimeSlotId, 0, 4));
        $month = intval(substr($virtualTimeSlotId, 4, 2));
        $day = intval(substr($virtualTimeSlotId, 6, 2));
        $hour = intval(substr($virtualTimeSlotId, 8, 2));
        $minute = intval(substr($virtualTimeSlotId, 10, 2));
        
        // 驗證日期時間格式
        if (!checkdate($month, $day, $year) || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            Log::warning('Invalid date/time in old format ID', [
                'year' => $year, 'month' => $month, 'day' => $day,
                'hour' => $hour, 'minute' => $minute
            ]);
            return null;
        }
        
        // 創建開始時間
        $startTime = Carbon::create($year, $month, $day, $hour, $minute, 0);
        $endTime = $startTime->copy()->addMinutes($service->duration);
        
        // 尋找包含此時間的 available_time 記錄
        $baseTimeSlot = AvailableTime::where('is_active', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->whereDate('start_time', $startTime->format('Y-m-d'))
            ->first();
        
        if (!$baseTimeSlot) {
            Log::warning('No available time slot found for old format time', [
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s')
            ]);
            return null;
        }
        
        // 檢查時段可用性
        if (!$this->isTimeSlotAvailable($startTime, $endTime, $baseTimeSlot)) {
            Log::info('Old format time slot not available (already booked)');
            return null;
        }
        
        // 創建虛擬時段對象
        $virtualSlot = new \stdClass();
        $virtualSlot->id = $virtualTimeSlotId;
        $virtualSlot->base_time_slot_id = $baseTimeSlot->id;
        $virtualSlot->title = $baseTimeSlot->title;
        $virtualSlot->description = $baseTimeSlot->description;
        $virtualSlot->start_time = $startTime->toDateTimeString();
        $virtualSlot->end_time = $endTime->toDateTimeString();
        $virtualSlot->display_time = $startTime->format('H:i') . ' - ' . $endTime->format('H:i');
        $virtualSlot->date = $startTime->format('Y-m-d');
        $virtualSlot->max_capacity = $baseTimeSlot->max_capacity;
        $virtualSlot->is_active = $baseTimeSlot->is_active;
        
        Log::info('Old format virtual time slot found successfully', [
            'virtual_slot_id' => $virtualSlot->id,
            'base_time_slot_id' => $baseTimeSlot->id,
            'start_time' => $virtualSlot->start_time,
            'end_time' => $virtualSlot->end_time
        ]);
        
        return $virtualSlot;
    }

    public function isConfigured(): bool
    {
        return !empty($this->channelAccessToken) && !empty($this->channelSecret);
    }
    
    /**
     * 公開方法：獲取指定服務的可用預約時段
     * 供 API 控制器調用
     */
    public function getAvailableTimeSlots($serviceId, $lastEndTime = null)
    {
        $service = Service::find($serviceId);
        if (!$service) {
            return [
                'success' => false,
                'message' => '找不到指定的服務項目',
                'data' => []
            ];
        }
        
        try {
            // 如果有 lastEndTime，獲取連續預約時段
            if ($lastEndTime) {
                $lastEndTimeCarbon = Carbon::parse($lastEndTime);
                $availableSlots = $this->getAvailableTimeSlotsForContinuousBooking($serviceId, $lastEndTimeCarbon);
            } else {
                $availableSlots = $this->getAvailableTimeSlotsForService($serviceId, null);
            }
            
            // 格式化回傳資料
            $formattedSlots = $availableSlots->map(function($slot) {
                return [
                    'id' => $slot->id,
                    'date' => $slot->date,
                    'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                    'display_time' => $slot->display_time,
                    'start_datetime' => $slot->start_time,
                    'end_datetime' => $slot->end_time,
                ];
            });
            
            return [
                'success' => true,
                'message' => '獲取可用時段成功',
                'data' => [
                    'service' => [
                        'id' => $service->id,
                        'name' => $service->name,
                        'duration' => $service->duration,
                        'price' => $service->price,
                    ],
                    'available_slots' => $formattedSlots->values()->toArray(),
                    'total_count' => $formattedSlots->count(),
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get available time slots', [
                'service_id' => $serviceId,
                'last_end_time' => $lastEndTime,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '獲取可用時段失敗：' . $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 設定營業時間
     */
    public function setBusinessHours($startTime, $endTime)
    {
        try {
            Setting::set('business_start_time', $startTime);
            Setting::set('business_end_time', $endTime);
            
            return [
                'success' => true,
                'message' => '營業時間設定成功'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to set business hours', [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '營業時間設定失敗：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 獲取營業時間設定
     */
    public function getBusinessHours()
    {
        return [
            'start_time' => Setting::get('business_start_time') ?? '08:00',
            'end_time' => Setting::get('business_end_time') ?? '18:00'
        ];
    }
    
    /**
     * 處理編輯服務項目
     */
    private function handleEditService($replyToken, $reservationId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 顯示可用的服務選擇
            $services = Service::where('is_active', true)->take(10)->get();
            
            if ($services->isEmpty()) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '目前沒有可用的服務項目。'
                ]);
                return;
            }

            // 建立服務選擇的 Flex Message
            $serviceContents = [];
            
            foreach ($services as $service) {
                $priceText = $service->price ? "NT$ " . number_format((float)$service->price) : "免費";
                
                $serviceContents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => $service->name,
                                    'weight' => 'bold',
                                    'size' => 'md',
                                    'flex' => 3
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $service->duration . '分',
                                    'size' => 'sm',
                                    'color' => '#666666',
                                    'align' => 'end',
                                    'flex' => 1
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $priceText,
                            'size' => 'sm',
                            'color' => '#27AE60',
                            'weight' => 'bold',
                            'margin' => 'xs'
                        ],
                        [
                            'type' => 'button',
                            'height' => 'sm',
                            'color' => '#3498DB',
                            'margin' => 'md',
                            'action' => [
                                'type' => 'postback',
                                'label' => '選擇此服務',
                                'data' => "action=update_service&reservation_id={$reservationId}&new_service_id={$service->id}"
                            ]
                        ]
                    ],
                    'backgroundColor' => '#F8F9FA',
                    'cornerRadius' => '8px',
                    'paddingAll' => 'md',
                    'margin' => 'sm'
                ];
            }

            $message = [
                'type' => 'flex',
                'altText' => '選擇新的服務項目',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '選擇新的服務項目',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "目前服務：{$reservation->service->name}",
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#E67E22',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '可選擇的服務',
                                'size' => 'lg',
                                'color' => '#333333',
                                'weight' => 'bold',
                                'margin' => 'none'
                            ],
                            [
                                'type' => 'text',
                                'text' => '選擇您希望更改的新服務項目',
                                'size' => 'sm',
                                'color' => '#666666',
                                'margin' => 'xs',
                                'wrap' => true
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'spacing' => 'sm',
                                'margin' => 'lg',
                                'contents' => $serviceContents
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '⚠️ 更改服務可能影響預約時間',
                                'size' => 'xs',
                                'color' => '#E74C3C',
                                'align' => 'center'
                            ]
                        ],
                        'paddingAll' => 'md'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to handle edit service', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '編輯服務時發生錯誤，請稍後再試。'
            ]);
        }
    }
    
    /**
     * 處理編輯預約時間
     */
    private function handleEditTime($replyToken, $reservationId, $serviceId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            $service = Service::find($serviceId);
            if (!$service) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到指定的服務項目。'
                ]);
                return;
            }

            // 先顯示日期選擇，而不是直接顯示時間
            $this->handleEditDateSelection($replyToken, $reservationId, $serviceId, $userId);

        } catch (\Exception $e) {
            Log::error('Failed to handle edit time', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'service_id' => $serviceId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '編輯預約時間時發生錯誤，請稍後再試。'
            ]);
        }
    }

    /**
     * 處理編輯預約日期選擇
     */
    private function handleEditDateSelection($replyToken, $reservationId, $serviceId, $userId)
    {
        try {
            $service = Service::find($serviceId);
            if (!$service) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到指定的服務項目。'
                ]);
                return;
            }

            $reservation = Reservation::find($reservationId);
            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 獲取可用時段
            $availableTimeSlots = $this->getAvailableTimeSlotsForService($serviceId);
            
            if ($availableTimeSlots->isEmpty()) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => "目前沒有適合「{$service->name}」的可用時段。"
                ]);
                return;
            }

            // 按日期分組
            $availableDates = [];
            foreach ($availableTimeSlots as $timeSlot) {
                $dateStr = Carbon::parse($timeSlot->start_time)->format('Y-m-d');
                $dateObj = Carbon::parse($timeSlot->start_time);
                
                if (!isset($availableDates[$dateStr])) {
                    $availableDates[$dateStr] = $dateObj;
                }
            }

            // 只取未來7天的日期
            $today = now()->startOfDay();
            $availableDates = array_filter($availableDates, function($dateObj) use ($today) {
                return $dateObj->gte($today) && $dateObj->lte($today->copy()->addDays(7));
            });

            if (empty($availableDates)) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '接下來7天沒有可用的預約時段。'
                ]);
                return;
            }

            // 建立日期選擇按鈕
            $dateButtons = [];
            $buttonRows = [];
            $buttonsPerRow = 2;

            foreach ($availableDates as $dateStr => $dateObj) {
                $dayMapping = [
                    'Monday' => '週一',
                    'Tuesday' => '週二',
                    'Wednesday' => '週三',
                    'Thursday' => '週四',
                    'Friday' => '週五',
                    'Saturday' => '週六',
                    'Sunday' => '週日'
                ];
                $dayOfWeek = $dayMapping[$dateObj->format('l')] ?? $dateObj->format('l');
                $isToday = $dateObj->isToday();
                $isTomorrow = $dateObj->isTomorrow();
                
                $displayText = $dateObj->format('m/d');
                if ($isToday) {
                    $displayText .= ' (今天)';
                } elseif ($isTomorrow) {
                    $displayText .= ' (明天)';
                } else {
                    $displayText .= " ({$dayOfWeek})";
                }

                $dateButtons[] = [
                    'type' => 'button',
                    'height' => 'sm',
                    'action' => [
                        'type' => 'postback',
                        'label' => $displayText,
                        'data' => "action=edit_date_selected&reservation_id={$reservationId}&service_id={$serviceId}&selected_date={$dateStr}"
                    ],
                    'style' => 'primary',
                    'color' => '#3498DB'
                ];

                if (count($dateButtons) == $buttonsPerRow) {
                    $buttonRows[] = [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'spacing' => 'sm',
                        'contents' => $dateButtons
                    ];
                    $dateButtons = [];
                }
            }

            // 確保至少有一行按鈕
            if (!empty($dateButtons)) {
                $buttonRows[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'spacing' => 'sm',
                    'contents' => $dateButtons
                ];
            }

            $currentDateTime = $reservation->getReservationDateTime();
            $durationText = $service->duration . '分鐘';
            $message = [
                'type' => 'flex',
                'altText' => '選擇新的預約日期',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '選擇新的預約日期',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "目前：{$currentDateTime->format('m/d H:i')}",
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
                                'type' => 'text',
                                'text' => $service->name,
                                'size' => 'lg',
                                'color' => '#333333',
                                'weight' => 'bold',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "服務時長：{$durationText}",
                                'size' => 'sm',
                                'color' => '#666666',
                                'align' => 'center',
                                'margin' => 'xs'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'text',
                                'text' => '請選擇日期',
                                'size' => 'md',
                                'color' => '#333333',
                                'weight' => 'bold',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'spacing' => 'sm',
                                'margin' => 'md',
                                'contents' => $buttonRows
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'secondary',
                                'height' => 'sm',
                                'action' => [
                                    'type' => 'message',
                                    'label' => '返回預約編輯',
                                    'text' => '查詢預約'
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to handle edit date selection', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'service_id' => $serviceId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '選擇日期時發生錯誤，請稍後再試。'
            ]);
        }
    }

    /**
     * 處理選定日期後的時間選擇
     */
    private function handleEditTimeSelection($replyToken, $reservationId, $serviceId, $selectedDate, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            $service = Service::find($serviceId);
            if (!$service) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到指定的服務項目。'
                ]);
                return;
            }

            // 獲取選定日期的可用時段
            $availableTimeSlots = $this->getAvailableTimeSlotsForService($serviceId);
            
            // 篩選選定日期的時段
            $selectedDateTimeSlots = $availableTimeSlots->filter(function($timeSlot) use ($selectedDate) {
                return Carbon::parse($timeSlot->start_time)->format('Y-m-d') === $selectedDate;
            });

            if ($selectedDateTimeSlots->isEmpty()) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '該日期沒有可用的時段，請選擇其他日期。'
                ]);
                return;
            }

            // 建立時間選擇按鈕
            $timeButtons = [];
            $buttonRows = [];
            $buttonsPerRow = 1; // 每行1個按鈕，顯示更清楚

            foreach ($selectedDateTimeSlots as $index => $timeSlot) {
                $startTime = Carbon::parse($timeSlot->start_time);
                $endTime = Carbon::parse($timeSlot->end_time);
                $timeStr = $startTime->format('H:i');
                $endTimeStr = $endTime->format('H:i');
                
                $timeButtons[] = [
                    'type' => 'button',
                    'height' => 'sm',
                    'action' => [
                        'type' => 'postback',
                        'label' => "{$timeStr} - {$endTimeStr}",
                        'data' => "action=update_time&reservation_id={$reservationId}&new_time_id={$timeSlot->id}"
                    ],
                    'style' => 'primary',
                    'color' => '#3498DB',
                    'margin' => 'sm'
                ];

                // 每1個按鈕組成一行
                if (count($timeButtons) == $buttonsPerRow || $index == count($selectedDateTimeSlots) - 1) {
                    $buttonRows[] = [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => $timeButtons
                    ];
                    $timeButtons = [];
                }
            }

            $selectedDateObj = Carbon::parse($selectedDate);
            $dayMapping = [
                'Monday' => '週一',
                'Tuesday' => '週二',
                'Wednesday' => '週三',
                'Thursday' => '週四',
                'Friday' => '週五',
                'Saturday' => '週六',
                'Sunday' => '週日'
            ];
            $dayOfWeek = $dayMapping[$selectedDateObj->format('l')] ?? $selectedDateObj->format('l');
            $displayDate = $selectedDateObj->format('m/d') . " ({$dayOfWeek})";
            
            $currentDateTime = $reservation->getReservationDateTime();
            
            $message = [
                'type' => 'flex',
                'altText' => '選擇新的預約時間',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '選擇預約時間',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "選定日期：{$displayDate}",
                                'color' => '#ffffff',
                                'size' => 'sm',
                                'align' => 'center',
                                'margin' => 'xs'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $service->name,
                                'size' => 'lg',
                                'color' => '#333333',
                                'weight' => 'bold',
                                'align' => 'center'
                            ],
                            [
                                'type' => 'text',
                                'text' => "目前：{$currentDateTime->format('m/d H:i')}",
                                'size' => 'sm',
                                'color' => '#666666',
                                'align' => 'center',
                                'margin' => 'xs'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'text',
                                'text' => '請選擇時間',
                                'size' => 'md',
                                'color' => '#333333',
                                'weight' => 'bold',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'spacing' => 'sm',
                                'margin' => 'md',
                                'contents' => $buttonRows
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'secondary',
                                'height' => 'sm',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '重新選擇日期',
                                    'data' => "action=edit_time&reservation_id={$reservationId}&service_id={$serviceId}"
                                ]
                            ]
                        ],
                        'paddingAll' => 'lg'
                    ]
                ]
            ];

            $this->replyMessage($replyToken, $message);

        } catch (\Exception $e) {
            Log::error('Failed to handle edit time selection', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'service_id' => $serviceId,
                'selected_date' => $selectedDate,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '選擇時間時發生錯誤，請稍後再試。'
            ]);
        }
    }
    
    /**
     * 處理更新服務項目
     */
    private function handleUpdateService($replyToken, $reservationId, $newServiceId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service', 'availableTime'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            $newService = Service::find($newServiceId);
            if (!$newService) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到指定的服務項目。'
                ]);
                return;
            }

            // 檢查新服務是否可以在當前時間段預約
            // 使用模型的輔助方法獲取完整的預約日期時間
            $reservationDateTime = $reservation->getReservationDateTime();
            $baseTimeSlot = $reservation->availableTime;
            
            // 檢查新服務時長是否適合當前時段
            $serviceEndTime = $reservationDateTime->copy()->addMinutes($newService->duration);
            $slotEndTime = Carbon::parse($baseTimeSlot->end_time);
            
            if ($serviceEndTime > $slotEndTime) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => "新服務「{$newService->name}」（{$newService->duration}分鐘）超出當前時段範圍，請重新選擇時間。"
                ]);
                return;
            }

            // 更新預約
            $reservation->update([
                'service_id' => $newService->id
            ]);

            $this->replyMessage($replyToken, [
                'type' => 'flex',
                'altText' => '服務更新成功',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '服務更新成功',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => "服務已更新為：{$newService->name}",
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => "時間：{$reservationDateTime->format('Y年m月d日 H:i')}",
                                'size' => 'md',
                                'color' => '#666666',
                                'align' => 'center',
                                'margin' => 'lg'
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update service', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'new_service_id' => $newServiceId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '更新服務時發生錯誤，請稍後再試。'
            ]);
        }
    }
    
    /**
     * 處理更新預約時間
     */
    private function handleUpdateTime($replyToken, $reservationId, $newTimeId, $userId)
    {
        try {
            $customer = Customer::where('line_user_id', $userId)->first();
            if (!$customer) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到您的客戶資料。'
                ]);
                return;
            }

            $reservation = Reservation::where('id', $reservationId)
                ->where('customer_id', $customer->id)
                ->with(['service', 'availableTime'])
                ->first();

            if (!$reservation) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '找不到此預約記錄。'
                ]);
                return;
            }

            // 解析新的虛擬時段
            $newVirtualTimeSlot = $this->findVirtualTimeSlot($reservation->service_id, $newTimeId);
            if (!$newVirtualTimeSlot) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '選擇的新時間無效。'
                ]);
                return;
            }

            // 檢查新時段是否可用
            if (!$this->canBookVirtualTimeSlot($newVirtualTimeSlot, $reservation->service)) {
                $this->replyMessage($replyToken, [
                    'type' => 'text',
                    'text' => '選擇的新時段已無法預約，請選擇其他時間。'
                ]);
                return;
            }

            // 注意：不需要手動調整 current_bookings
            // 我們的邏輯基於實際預約記錄，不使用 current_bookings 字段

            // 更新預約記錄到新時段
            $newBaseTimeSlot = AvailableTime::find($newVirtualTimeSlot->base_time_slot_id);
            $reservation->update([
                'available_time_id' => $newBaseTimeSlot->id,
                'reservation_date' => Carbon::parse($newVirtualTimeSlot->start_time)->toDateString(),
                'reservation_time' => Carbon::parse($newVirtualTimeSlot->start_time)->format('H:i:s')
            ]);

            $newDateTime = Carbon::parse($newVirtualTimeSlot->start_time);

            $this->replyMessage($replyToken, [
                'type' => 'flex',
                'altText' => '時間更新成功',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '時間更新成功',
                                'weight' => 'bold',
                                'color' => '#ffffff',
                                'size' => 'xl',
                                'align' => 'center'
                            ]
                        ],
                        'backgroundColor' => '#27AE60',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => "預約時間已更新",
                                'size' => 'lg',
                                'color' => '#333333',
                                'align' => 'center',
                                'weight' => 'bold'
                            ],
                            [
                                'type' => 'text',
                                'text' => "新時間：{$newDateTime->format('Y年m月d日 H:i')}",
                                'size' => 'md',
                                'color' => '#666666',
                                'align' => 'center',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'text',
                                'text' => "服務：{$reservation->service->name}",
                                'size' => 'md',
                                'color' => '#666666',
                                'align' => 'center'
                            ]
                        ],
                        'paddingAll' => 'xl'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update time', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservationId,
                'new_time_id' => $newTimeId,
                'userId' => $userId
            ]);
            
            $this->replyMessage($replyToken, [
                'type' => 'text',
                'text' => '更新時間時發生錯誤，請稍後再試。'
            ]);
        }
    }

    private function isValidImageUrl($url)
    {
        // 檢查是否為有效的 HTTP(S) URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 檢查是否為 HTTPS (LINE Bot 要求)
        if (!str_starts_with($url, 'https://')) {
            return false;
        }
        
        return true;
    }
}