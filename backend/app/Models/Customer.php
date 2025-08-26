<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'line_user_id',
        'name',
        'line_display_name', // LINE SDK 顯示名稱
        'line_picture_url', // LINE SDK 頭像 URL
        'line_status_message', // LINE SDK 狀態訊息
        'phone', // 參考電話，實際預約電話存在 reservations 表
        'email',
        'gender',
        'birthday',
        'address',
        'notes', // 客戶總體備註，預約特定備註存在 reservations 表
        'status',
        'preferences',
        'last_interaction_at',
        'referral_source',
        'total_reservations',
        'total_spent',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_interaction_at' => 'datetime',
        'preferences' => 'array',
        'total_spent' => 'decimal:2',
    ];

    protected $dates = [
        'deleted_at'
    ];

    // 關聯：客戶的預約記錄
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    // 關聯：客戶的LINE訊息記錄
    public function lineMessageLogs()
    {
        return $this->hasMany(LineMessageLog::class, 'line_user_id', 'line_user_id');
    }

    // 範圍查詢：活躍客戶
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // 範圍查詢：最近有互動的客戶
    public function scopeRecentlyActive($query, $days = 30)
    {
        return $query->where('last_interaction_at', '>=', now()->subDays($days));
    }

    // 範圍查詢：VIP客戶（根據預約次數或消費金額）
    public function scopeVip($query, $minReservations = 10, $minSpent = 1000)
    {
        return $query->where(function ($q) use ($minReservations, $minSpent) {
            $q->where('total_reservations', '>=', $minReservations)
              ->orWhere('total_spent', '>=', $minSpent);
        });
    }

    // 獲取客戶等級
    public function getCustomerLevelAttribute()
    {
        if ($this->total_reservations >= 20 || $this->total_spent >= 5000) {
            return 'VIP';
        } elseif ($this->total_reservations >= 10 || $this->total_spent >= 3000) {
            return 'Gold';
        } elseif ($this->total_reservations >= 5 || $this->total_spent >= 1000) {
            return 'Silver';
        }
        return 'Bronze';
    }

    // 更新互動時間
    public function updateLastInteraction()
    {
        $this->update(['last_interaction_at' => now()]);
    }

    // 增加預約次數
    public function incrementReservations()
    {
        $this->increment('total_reservations');
    }

    // 增加消費金額
    public function addSpending($amount)
    {
        $this->increment('total_spent', $amount);
    }

    // 重新計算客戶統計數據
    public function recalculateStats()
    {
        $confirmedReservations = $this->reservations()
            ->where('status', 'confirmed')
            ->with('service')
            ->get();

        $totalReservations = $confirmedReservations->count();
        $totalSpent = $confirmedReservations->sum(function($reservation) {
            return $reservation->service ? $reservation->service->price : 0;
        });

        $this->update([
            'total_reservations' => $totalReservations,
            'total_spent' => $totalSpent
        ]);

        return $this;
    }

    // 批量重新計算所有客戶統計數據
    public static function recalculateAllStats()
    {
        $customers = static::with(['reservations.service'])->get();
        
        foreach ($customers as $customer) {
            $customer->recalculateStats();
        }
        
        return $customers->count();
    }

    // 根據LINE用戶ID查找或創建客戶
    public static function findOrCreateByLineUserId($lineUserId, $name = null)
    {
        $customer = static::where('line_user_id', $lineUserId)->first();
        
        if (!$customer && $name) {
            $customer = static::create([
                'line_user_id' => $lineUserId,
                'name' => $name,
                'status' => 'active',
                'last_interaction_at' => now(),
            ]);
        }
        
        return $customer;
    }
}
