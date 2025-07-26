<?php

namespace App\Observers;

use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

class ReservationObserver
{
    /**
     * Handle the Reservation "created" event.
     */
    public function created(Reservation $reservation): void
    {
        $this->updateCustomerStats($reservation);
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        $this->updateCustomerStats($reservation);
        
        // 如果狀態改變了，也需要更新統計
        if ($reservation->wasChanged('status')) {
            $this->updateCustomerStats($reservation);
        }
    }

    /**
     * Handle the Reservation "deleted" event.
     */
    public function deleted(Reservation $reservation): void
    {
        $this->updateCustomerStats($reservation);
    }

    /**
     * 更新客戶統計數據
     */
    private function updateCustomerStats(Reservation $reservation): void
    {
        try {
            if ($reservation->customer) {
                $reservation->customer->recalculateStats();
                
                Log::info('Customer stats updated', [
                    'customer_id' => $reservation->customer->id,
                    'reservation_id' => $reservation->id,
                    'reservation_status' => $reservation->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update customer stats', [
                'error' => $e->getMessage(),
                'customer_id' => $reservation->customer_id ?? null,
                'reservation_id' => $reservation->id
            ]);
        }
    }
}
