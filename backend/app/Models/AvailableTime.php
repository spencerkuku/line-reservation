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
