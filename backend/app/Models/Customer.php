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
        'phone',
        'email',
        'gender',
        'birthday',
        'address',
        'notes',
        'status',
        'preferences',
        'last_interaction_at',
        'referral_source',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_interaction_at' => 'datetime',
        'preferences' => 'array',
    ];

    protected $appends = ['total_reservations', 'total_spent', 'customer_level'];

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
        return $query->whereHas('reservations', function ($q) use ($minReservations) {
            $q->where('status', 'confirmed');
        }, '>=', $minReservations)
        ->orWhereHas('reservations', function ($q) use ($minSpent) {
            $q->where('status', 'confirmed')
              ->join('services', 'reservations.service_id', '=', 'services.id')
              ->havingRaw('SUM(services.price) >= ?', [$minSpent]);
        });
    }

    // 獲取總預約次數（計算型屬性）
    public function getTotalReservationsAttribute()
    {
        return $this->reservations()->where('status', 'confirmed')->count();
    }

    // 獲取總消費金額（計算型屬性）
    public function getTotalSpentAttribute()
    {
        return $this->reservations()
            ->where('status', 'confirmed')
            ->join('services', 'reservations.service_id', '=', 'services.id')
            ->sum('services.price') ?: 0;
    }

    // 獲取客戶等級
    public function getCustomerLevelAttribute()
    {
        $totalReservations = $this->total_reservations;
        $totalSpent = $this->total_spent;
        
        if ($totalReservations >= 20 || $totalSpent >= 5000) {
            return 'VIP';
        } elseif ($totalReservations >= 10 || $totalSpent >= 3000) {
            return 'Gold';
        } elseif ($totalReservations >= 5 || $totalSpent >= 1000) {
            return 'Silver';
        }
        return 'Bronze';
    }

    // 更新互動時間
    public function updateLastInteraction()
    {
        $this->update(['last_interaction_at' => now()]);
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
