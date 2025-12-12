<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AvailableTime extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'max_capacity',
        'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean'
    ];

    // 自定義時間序列化格式，確保返回本地時區
    protected $dateFormat = 'Y-m-d H:i:s';

    // 確保時間以本地時區格式返回給前端
    public function toArray()
    {
        $array = parent::toArray();
        
        // 將時間轉換為應用程式時區格式
        if (isset($array['start_time'])) {
            $array['start_time'] = $this->start_time->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }
        
        if (isset($array['end_time'])) {
            $array['end_time'] = $this->end_time->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }
        
        return $array;
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->whereRaw('(
            SELECT COUNT(*) 
            FROM reservations 
            WHERE reservations.available_time_id = available_times.id 
            AND reservations.status IN ("confirmed", "pending")
        ) < available_times.max_capacity');
    }

    // 獲取當前預約數量（計算型屬性）
    public function getCurrentBookingsAttribute()
    {
        return $this->reservations()->whereIn('status', ['confirmed', 'pending'])->count();
    }

    public function isAvailable()
    {
        return $this->current_bookings < $this->max_capacity;
    }
}
