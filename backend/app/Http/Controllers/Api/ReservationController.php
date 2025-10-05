<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\LoggingService;
use App\Http\Requests\ReservationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logApiRequestStart($request, $requestId, 'Get Reservations');
        
        try {
            $now = Carbon::now();
            $reservations = Reservation::with(['user', 'service', 'customer'])->get();
            
            // 排序邏輯：1. 未確認預約 2. 今日預約 3. 明日預約 4. 其他預約（按時間降序）
            $reservations = $reservations->sortBy(function ($reservation) use ($now) {
                // 使用模型方法獲取完整的預約時間，避免字串拼接問題
                $reservationDateTime = $reservation->getReservationDateTime();
                $reservationDate = $reservationDateTime->startOfDay();
                $today = $now->copy()->startOfDay();
                $tomorrow = $today->copy()->addDay();
                
                // 計算時間戳（用於同級別內的排序）
                $timestamp = $reservationDateTime->timestamp;
                
                if ($reservation->status === 'pending') {
                    // 1. 未確認預約 - 最高優先級，按時間降序排列
                    return 1000000000 - $timestamp;
                } elseif ($reservationDate->equalTo($today)) {
                    // 2. 今日預約 - 第二優先級，按時間降序排列
                    return 2000000000 - $timestamp;
                } elseif ($reservationDate->equalTo($tomorrow)) {
                    // 3. 明日預約 - 第三優先級，按時間降序排列
                    return 3000000000 - $timestamp;
                } else {
                    // 4. 其他預約 - 按時間降序排列
                    return 4000000000 - $timestamp;
                }
            });

            $data = $reservations->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'user_name' => e($reservation->user->name ?? '系統管理員'),
                    'customer_id' => $reservation->customer_id,
                    'customer' => $reservation->customer,
                    'customer_name' => e($reservation->customer_name),
                    'customer_phone' => e($reservation->customer_phone),
                    'customer_notes' => e($reservation->customer_notes),
                    'customer_line_display_name' => e($reservation->customer->line_display_name ?? null),
                    'customer_line_user_id' => e($reservation->customer->line_user_id ?? null),
                    'reservation_name' => e($reservation->reservation_name),
                    'reservation_phone' => e($reservation->reservation_phone),
                    'reservation_notes' => e($reservation->reservation_notes),
                    'service_name' => e($reservation->service->name ?? '未指定服務'),
                    'service_price' => $reservation->service->price ?? 0,
                    'reservation_date' => $reservation->reservation_date,
                    'reservation_time' => $reservation->reservation_time,
                    'status' => $reservation->status,
                    'notes' => e($reservation->notes),
                    'created_at' => $reservation->created_at,
                    // 新增：報到狀態
                    'check_in_status' => $reservation->check_in_status,
                    'check_in_time' => $reservation->check_in_time?->format('Y-m-d H:i:s'),
                    'no_show' => $reservation->no_show,
                    // 新增：付款狀態
                    'payment_status' => $reservation->payment_status,
                    'payment_amount' => $reservation->payment_amount ?? 0,
                    'payment_method' => $reservation->payment_method,
                ];
            })->values(); // 重要：使用 values() 確保返回數組而不是對象

            LoggingService::logApiRequestSuccess($requestId, ['count' => $reservations->count()]);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch reservations', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '獲取預約資料失敗'
            ], 500);
        }
    }

    public function store(ReservationRequest $request)
    {
        $requestId = LoggingService::generateRequestId();
        LoggingService::logApiRequestStart($request, $requestId, 'Create Reservation');
        
        try {
            DB::beginTransaction();
            
            $validated = $request->validated();
            
            $reservation = Reservation::create([
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_line_user_id' => $validated['customer_line_user_id'] ?? null,
                'service_id' => $validated['service_id'],
                'reservation_date' => $validated['reservation_date'],
                'reservation_time' => $validated['reservation_time'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending'
            ]);
            
            DB::commit();
            
            LoggingService::logApiRequestSuccess($requestId, ['reservation_id' => $reservation->id]);
            
            return response()->json([
                'success' => true,
                'data' => $reservation,
                'message' => '預約建立成功'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create reservation', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['customer_phone']) // 不記錄敏感資訊
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '建立預約失敗'
            ], 500);
        }
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
