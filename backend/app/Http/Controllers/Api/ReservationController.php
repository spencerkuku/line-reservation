<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::with(['user', 'customer', 'service'])
            ->orderBy('reservation_date', 'desc')
            ->orderBy('reservation_time', 'desc')
            ->get();

        $data = $reservations->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'user_name' => $reservation->user->name ?? '系統管理員',
                'customer_id' => $reservation->customer_id,
                'customer_name' => $reservation->customer->name ?? '未知客戶',
                'customer_phone' => $reservation->customer->phone,
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

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function confirm(Reservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只能確認待審核的預約'
            ], 422);
        }

        $reservation->confirm();

        return response()->json([
            'success' => true,
            'message' => '預約已確認'
        ]);
    }

    public function cancel(Reservation $reservation)
    {
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => '無法取消此預約'
            ], 422);
        }

        $reservation->cancel();

        return response()->json([
            'success' => true,
            'message' => '預約已取消'
        ]);
    }
}
