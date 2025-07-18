<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id', // 新增客戶關聯
        'service_id',
        'available_time_id',
        'reservation_date',
        'reservation_time',
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
