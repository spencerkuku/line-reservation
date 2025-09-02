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
        $this->logReservationEvent('created', $reservation);
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        $this->logReservationEvent('updated', $reservation);
        
        // 如果狀態改變了，記錄狀態變更
        if ($reservation->wasChanged('status')) {
            Log::info('Reservation status changed', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'old_status' => $reservation->getOriginal('status'),
                'new_status' => $reservation->status
            ]);
        }
    }

    /**
     * Handle the Reservation "deleted" event.
     */
    public function deleted(Reservation $reservation): void
    {
        $this->logReservationEvent('deleted', $reservation);
    }

    /**
     * 記錄預約事件
     */
    private function logReservationEvent(string $event, Reservation $reservation): void
    {
        try {
            Log::info("Reservation {$event}", [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'service_id' => $reservation->service_id,
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'status' => $reservation->status
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log reservation {$event}", [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->id ?? null
            ]);
        }
    }
}
