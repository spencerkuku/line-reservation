<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    /**
     * 可批量賦值的屬性
     * 
     * 注意：
     * - reservation_date: 預約日期 (DATE 類型，格式: Y-m-d)
     * - reservation_time: 預約時間 (TIME 類型，格式: H:i:s)
     * - 請使用 getReservationDateTime() 方法獲取完整的預約日期時間
     */
    protected $fillable = [
        'customer_id', // LINE 客戶關聯
        'service_id',
        'available_time_id',
        'reservation_date',
        'reservation_time',
        'customer_name', // 預約時填寫的姓名
        'customer_phone', // 預約時填寫的電話
        'customer_notes', // 預約時填寫的備註
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at'
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    /**
     * 獲取完整的預約日期時間
     * 
     * @return \Carbon\Carbon
     */
    public function getReservationDateTime()
    {
        $reservationDate = $this->reservation_date;
        $reservationTime = $this->reservation_time;
        
        // 確保正確處理日期格式
        if ($reservationDate instanceof \Carbon\Carbon) {
            $dateStr = $reservationDate->format('Y-m-d');
        } else {
            $dateStr = $reservationDate;
        }
        
        return \Carbon\Carbon::parse($dateStr . ' ' . $reservationTime);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 新增：客戶關聯
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function availableTime()
    {
        return $this->belongsTo(AvailableTime::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('reservation_date', today());
    }

    public function confirm()
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);
    }

    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        // 釋放可預約時段容量
        if ($this->available_time_id) {
            $this->availableTime->decrement('current_bookings');
        }
    }
}
