<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\LoggingService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logApiRequestStart($request, $requestId, 'Get Reservations');
        
        $reservations = Reservation::with(['user', 'customer', 'service'])
            ->orderBy('reservation_date', 'desc')
            ->orderBy('reservation_time', 'desc')
            ->get();

        $data = $reservations->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'user_name' => $reservation->user->name ?? '系統管理員',
                'customer_id' => $reservation->customer_id,
                'customer' => $reservation->customer, // 完整的customer對象
                'customer_name' => $reservation->customer_name, // 預約時填寫的姓名
                'customer_phone' => $reservation->customer_phone, // 預約時填寫的電話
                'customer_notes' => $reservation->customer_notes, // 預約時填寫的備註
                'customer_line_display_name' => $reservation->customer->line_display_name ?? null, // LINE 顯示名稱
                'customer_line_user_id' => $reservation->customer->line_user_id,
                'service_name' => $reservation->service->name,
                'service_price' => $reservation->service->price,
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'status' => $reservation->status,
                'notes' => $reservation->notes,
                'created_at' => $reservation->created_at
            ];
        });

        LoggingService::logApiRequestSuccess($requestId, ['count' => $reservations->count()]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function confirm(Reservation $reservation)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logApiRequestStart(request(), $requestId, 'Confirm Reservation');
        
        if ($reservation->status !== 'pending') {
            LoggingService::logReservationEvent('confirm_failed', [
                'reservation_id' => $reservation->id,
                'current_status' => $reservation->status,
                'reason' => 'Invalid status for confirmation'
            ], $requestId);
            
            return response()->json([
                'success' => false,
                'message' => '只能確認待審核的預約'
            ], 422);
        }

        $reservation->confirm();

        LoggingService::logReservationEvent('confirmed', [
            'reservation_id' => $reservation->id,
            'customer_name' => $reservation->customer_name,
            'service_name' => $reservation->service->name,
            'reservation_date' => $reservation->reservation_date,
            'reservation_time' => $reservation->reservation_time
        ], $requestId);

        LoggingService::logApiRequestSuccess($requestId, ['reservation_id' => $reservation->id]);

        return response()->json([
            'success' => true,
            'message' => '預約已確認'
        ]);
    }

    public function cancel(Reservation $reservation)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logApiRequestStart(request(), $requestId, 'Cancel Reservation');
        
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            LoggingService::logReservationEvent('cancel_failed', [
                'reservation_id' => $reservation->id,
                'current_status' => $reservation->status,
                'reason' => 'Invalid status for cancellation'
            ], $requestId);
            
            return response()->json([
                'success' => false,
                'message' => '無法取消此預約'
            ], 422);
        }

        $reservation->cancel();

        LoggingService::logReservationEvent('cancelled', [
            'reservation_id' => $reservation->id,
            'customer_name' => $reservation->customer_name,
            'service_name' => $reservation->service->name,
            'reservation_date' => $reservation->reservation_date,
            'reservation_time' => $reservation->reservation_time,
            'previous_status' => $reservation->getOriginal('status')
        ], $requestId);

        LoggingService::logApiRequestSuccess($requestId, ['reservation_id' => $reservation->id]);

        return response()->json([
            'success' => true,
            'message' => '預約已取消'
        ]);
    }
}
