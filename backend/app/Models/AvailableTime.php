<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'max_capacity',
        'current_bookings',
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
        return $query->whereColumn('current_bookings', '<', 'max_capacity');
    }

    public function isAvailable()
    {
        return $this->current_bookings < $this->max_capacity;
    }

    public function book()
    {
        if ($this->isAvailable()) {
            $this->increment('current_bookings');
            return true;
        }
        return false;
    }

    public function cancelBooking()
    {
        if ($this->current_bookings > 0) {
            $this->decrement('current_bookings');
            return true;
        }
        return false;
    }
}
