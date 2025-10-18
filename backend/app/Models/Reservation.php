<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

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
        'reservation_name', // 預約時填寫的姓名（快照）
        'reservation_phone', // 預約時填寫的電話（快照）
        'reservation_notes', // 預約時填寫的備註（快照）
        'service_id',
        'available_time_id',
        'reservation_date',
        'reservation_time',
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at',
        // 報到相關
        'check_in_status',
        'check_in_time',
        'check_in_by',
        'no_show',
        // 付款相關
        'payment_status',
        'payment_method',
        'payment_amount',
        'payment_time',
        'payment_note'
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'check_in_time' => 'datetime',
        'payment_time' => 'datetime',
        'payment_amount' => 'decimal:2',
        'no_show' => 'boolean'
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

    public function checkInUser()
    {
        return $this->belongsTo(User::class, 'check_in_by');
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
    }

    /**
     * 報到
     */
    public function checkIn($userId = null)
    {
        $reservationDateTime = $this->getReservationDateTime();
        $now = now();
        
        // 判斷是否遲到（超過預約時間15分鐘）
        $isLate = $now->diffInMinutes($reservationDateTime, false) < -15;
        
        $this->update([
            'check_in_status' => $isLate ? 'late' : 'checked_in',
            'check_in_time' => $now,
            'check_in_by' => $userId,
            'no_show' => false
        ]);

        return $this;
    }

    /**
     * 標記為爽約
     */
    public function markAsNoShow($userId = null)
    {
        $this->update([
            'check_in_status' => 'no_show',
            'check_in_time' => now(),
            'check_in_by' => $userId,
            'no_show' => true
        ]);

        return $this;
    }

    /**
     * 記錄付款
     */
    public function recordPayment($amount, $method, $note = null, $userId = null)
    {
        $totalAmount = $this->service->price ?? 0;
        
        // 計算新的付款總額
        $newPaymentAmount = $this->payment_amount + $amount;
        
        // 判斷付款狀態
        $paymentStatus = 'unpaid';
        if ($newPaymentAmount >= $totalAmount) {
            $paymentStatus = 'paid';
        } elseif ($newPaymentAmount > 0) {
            $paymentStatus = 'partial';
        }

        $updateData = [
            'payment_amount' => $newPaymentAmount,
            'payment_method' => $method,
            'payment_status' => $paymentStatus,
            'payment_time' => now(),
            'payment_note' => $note
        ];

        // 如果付款完成，自動將預約狀態更新為 completed
        if ($paymentStatus === 'paid') {
            $updateData['status'] = 'completed';
        }

        $this->update($updateData);

        return $this;
    }

    /**
     * 報到狀態文字
     */
    public function getCheckInStatusTextAttribute()
    {
        return match($this->check_in_status) {
            'pending' => '待報到',
            'checked_in' => '已報到',
            'no_show' => '爽約',
            'late' => '遲到',
            default => '未知'
        };
    }

    /**
     * 付款狀態文字
     */
    public function getPaymentStatusTextAttribute()
    {
        return match($this->payment_status) {
            'unpaid' => '未付款',
            'partial' => '部分付款',
            'paid' => '已付款',
            'refunded' => '已退款',
            default => '未知'
        };
    }

    /**
     * 付款方式文字
     */
    public function getPaymentMethodTextAttribute()
    {
        return match($this->payment_method) {
            'cash' => '現金',
            'credit_card' => '信用卡',
            'debit_card' => '金融卡',
            'transfer' => '轉帳',
            'line_pay' => 'LINE Pay',
            'other' => '其他',
            default => '未指定'
        };
    }
}
