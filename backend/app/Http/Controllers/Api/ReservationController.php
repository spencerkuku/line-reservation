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
            
            // 排序邏輯：1. 待確定預約(按時間排序) 2. 最近預約(已確定且未過期) 3. 過期預約
            $reservations = $reservations->sortBy(function ($reservation) use ($now) {
                // 使用模型方法獲取完整的預約時間，避免字串拼接問題
                $reservationDate = $reservation->getReservationDateTime();
                
                // 計算與現在時間的距離（秒數）
                $diffInSeconds = abs($reservationDate->diffInSeconds($now));
                
                if ($reservation->status === 'pending') {
                    // 1. 待確定預約 - 最高優先級，按時間順序排列（最近的時間在前）
                    return $diffInSeconds;
                } elseif ($reservationDate->isFuture()) {
                    // 2. 最近的預約 - 已確定且未過期，按接近度排序
                    return 1000000 + $diffInSeconds;
                } else {
                    // 3. 過期的預約 - 最低優先級，按時間倒序（最近過期的在前）
                    return 2000000 + $diffInSeconds;
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
                    // 預約快照欄位
                    'reservation_name' => e($reservation->reservation_name),
                    'reservation_phone' => e($reservation->reservation_phone),
                    'reservation_notes' => e($reservation->reservation_notes),
                    'service_name' => e($reservation->service->name ?? '未指定服務'),
                    'service_price' => $reservation->service->price ?? 0,
                    'reservation_date' => $reservation->reservation_date,
                    'reservation_time' => $reservation->reservation_time,
                    'status' => $reservation->status,
                    'notes' => e($reservation->notes),
                    'created_at' => $reservation->created_at
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
