<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CheckInController extends Controller
{
    /**
     * 報到
     */
    public function checkIn(Request $request, Reservation $reservation)
    {
        // 檢查預約狀態
        if ($reservation->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => '此預約已取消，無法報到'
            ], 400);
        }

        // 檢查是否已報到
        if (in_array($reservation->check_in_status, ['checked_in', 'late'])) {
            return response()->json([
                'success' => false,
                'message' => '此預約已完成報到'
            ], 400);
        }

        try {
            $reservation->checkIn($request->user()->id);
            
            Log::info('Customer checked in', [
                'reservation_id' => $reservation->id,
                'check_in_status' => $reservation->check_in_status,
                'check_in_by' => $request->user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => '報到成功',
                'data' => [
                    'check_in_status' => $reservation->check_in_status,
                    'check_in_status_text' => $reservation->check_in_status_text,
                    'check_in_time' => $reservation->check_in_time->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Check-in failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '報到失敗，請稍後再試'
            ], 500);
        }
    }

    /**
     * 標記為爽約
     */
    public function markNoShow(Request $request, Reservation $reservation)
    {
        // 檢查預約狀態
        if ($reservation->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => '此預約已取消'
            ], 400);
        }

        // 檢查是否已報到
        if (in_array($reservation->check_in_status, ['checked_in', 'late'])) {
            return response()->json([
                'success' => false,
                'message' => '此預約已完成報到，無法標記為爽約'
            ], 400);
        }

        try {
            $reservation->markAsNoShow($request->user()->id);
            
            Log::info('Reservation marked as no-show', [
                'reservation_id' => $reservation->id,
                'marked_by' => $request->user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => '已標記為爽約',
                'data' => [
                    'check_in_status' => $reservation->check_in_status,
                    'check_in_status_text' => $reservation->check_in_status_text
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark no-show failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '操作失敗，請稍後再試'
            ], 500);
        }
    }

    /**
     * 記錄付款
     */
    public function recordPayment(Request $request, Reservation $reservation)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,credit_card,debit_card,transfer,line_pay,other',
            'note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $paymentAmount = $request->input('amount');
            $paymentMethod = $request->input('method');
            $paymentNote = $request->input('note');
            
            $reservation->recordPayment(
                $paymentAmount,
                $paymentMethod,
                $paymentNote,
                $request->user()->id
            );
            
            // 重新載入 model 以獲取最新狀態
            $reservation->refresh();
            
            $message = '付款記錄成功';
            if ($reservation->status === 'completed') {
                $message = '付款完成！服務已結束';
            }
            
            Log::info('Payment recorded', [
                'reservation_id' => $reservation->id,
                'amount' => $paymentAmount,
                'method' => $paymentMethod,
                'status' => $reservation->status,
                'payment_status' => $reservation->payment_status,
                'recorded_by' => $request->user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'status' => $reservation->status,
                    'payment_status' => $reservation->payment_status,
                    'payment_status_text' => $reservation->payment_status_text,
                    'payment_amount' => $reservation->payment_amount,
                    'payment_method' => $reservation->payment_method,
                    'payment_method_text' => $reservation->payment_method_text,
                    'payment_time' => $reservation->payment_time->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Payment record failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '付款記錄失敗，請稍後再試'
            ], 500);
        }
    }

    /**
     * 取得今日報到清單
     */
    public function getTodayCheckIns()
    {
        $reservations = Reservation::with(['customer', 'service', 'checkInUser'])
            ->whereDate('reservation_date', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('reservation_time', 'asc')
            ->get();

        $statistics = [
            'total' => $reservations->count(),
            'checked_in' => $reservations->where('check_in_status', 'checked_in')->count(),
            'late' => $reservations->where('check_in_status', 'late')->count(),
            'no_show' => $reservations->where('check_in_status', 'no_show')->count(),
            'pending' => $reservations->where('check_in_status', 'pending')->count(),
            'paid' => $reservations->where('payment_status', 'paid')->count(),
            'unpaid' => $reservations->where('payment_status', 'unpaid')->count(),
            'partial' => $reservations->where('payment_status', 'partial')->count()
        ];

        $data = $reservations->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                // 客戶資訊
                'customer' => [
                    'id' => $reservation->customer?->id,
                    'line_display_name' => $reservation->customer?->line_display_name,
                    'line_picture_url' => $reservation->customer?->line_picture_url,
                    'name' => $reservation->customer?->name,
                    'customer_level' => $reservation->customer?->customer_level,
                ],
                // 預約時填寫的資訊（快照）
                'reservation_name' => $reservation->reservation_name,
                'reservation_phone' => $reservation->reservation_phone,
                // 為了向後兼容保留這兩個欄位
                'customer_name' => $reservation->reservation_name,
                'customer_phone' => $reservation->reservation_phone,
                // 服務資訊
                'service_name' => $reservation->service->name ?? 'N/A',
                'service_price' => $reservation->service->price ?? 0,
                // 預約時間
                'reservation_time' => $reservation->reservation_time,
                'reservation_datetime' => $reservation->getReservationDateTime()->format('Y-m-d H:i'),
                // 報到狀態
                'check_in_status' => $reservation->check_in_status,
                'check_in_status_text' => $reservation->check_in_status_text,
                'check_in_time' => $reservation->check_in_time?->format('H:i'),
                'check_in_by_name' => $reservation->checkInUser?->name,
                // 付款狀態
                'payment_status' => $reservation->payment_status,
                'payment_status_text' => $reservation->payment_status_text,
                'payment_amount' => $reservation->payment_amount ?? 0,
                'payment_method' => $reservation->payment_method,
                'payment_method_text' => $reservation->payment_method_text,
                'payment_time' => $reservation->payment_time?->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'statistics' => $statistics
        ]);
    }
}
