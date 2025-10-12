<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailableTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AvailableTimeController extends Controller
{
    // 獲取所有可預約時段
    public function index(Request $request)
    {
        // 輸入驗證和過濾
        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:' . now()->addYear(),
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:' . now()->addYear(),
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AvailableTime::query();

        // 安全的篩選條件
        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // 管理員頁面 - 顯示所有曾經建立的時段（不限制時間範圍）
        // 移除時間過濾，讓管理員可以看到所有歷史時段
        
        $perPage = $request->get('per_page', 50);
        $availableTimes = $query->orderBy('start_time')
            ->with(['reservations' => function($q) {
                // 只載入必要的預約資訊，避免暴露敏感資料
                $q->select('id', 'available_time_id', 'status');
            }])
            ->paginate($perPage);

        // 添加調試信息
        return response()->json([
            'success' => true,
            'data' => $availableTimes->items(),
            'pagination' => [
                'current_page' => $availableTimes->currentPage(),
                'last_page' => $availableTimes->lastPage(),
                'per_page' => $availableTimes->perPage(),
                'total' => $availableTimes->total(),
            ]
        ]);
    }

    // 創建可預約時段 (需要管理員權限)
    public function store(Request $request)
    {
        // 嚴格的輸入驗證 - 修正時間驗證，允許今天稍後的時間
        $request->validate([
            'title' => 'required|string|max:255|regex:/^[\p{L}\p{N}\s\-_.,!?()]+$/u',
            'description' => 'nullable|string|max:1000',
            'start_time' => 'required|date|before:' . now()->addYear(),
            'end_time' => 'required|date|after:start_time|before:' . now()->addYear(),
            'max_capacity' => 'required|integer|min:1|max:100',
        ], [
            'title.regex' => '標題只能包含字母、數字、空格和基本標點符號',
            'start_time.before' => '開始時間不能超過一年',
            'end_time.after' => '結束時間必須晚於開始時間',
            'max_capacity.max' => '最大容量不能超過100人',
        ]);

        // 檢查時間長度合理性
        try {
            // 保持原始時區，不強制轉換為 UTC
            $startTime = \Carbon\Carbon::parse($request->start_time);
            $endTime = \Carbon\Carbon::parse($request->end_time);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '時間格式錯誤：' . $e->getMessage()
            ], 422);
        }

        // 檢查開始時間是否為未來時間（至少比現在晚10分鐘）
        if ($startTime <= now()->addMinutes(10)) {
            return response()->json([
                'success' => false,
                'message' => '開始時間必須至少比現在晚10分鐘'
            ], 422);
        }
        
        // 確保時間順序正確
        if ($endTime <= $startTime) {
            return response()->json([
                'success' => false,
                'message' => '結束時間必須晚於開始時間'
            ], 422);
        }
        
        $duration = $startTime->diffInMinutes($endTime);

        // 添加 debug 信息
        if ($duration < 30) {
            return response()->json([
                'success' => false,
                'message' => "時段持續時間不能少於30分鐘（當前：{$duration}分鐘）"
            ], 422);
        }

        if ($duration > 720) { // 12小時
            return response()->json([
                'success' => false,
                'message' => '時段持續時間不能超過12小時'
            ], 422);
        }

        // 檢查時段是否衝突 - 修正邏輯，只檢查真正重疊的時段
        $conflictExists = AvailableTime::where(function ($query) use ($startTime, $endTime) {
            // 檢查時間段重疊：
            // 1. 新時段開始在現有時段內 (start_time < new_start < end_time)
            // 2. 新時段結束在現有時段內 (start_time < new_end < end_time)  
            // 3. 新時段完全包含現有時段 (new_start <= start_time AND new_end >= end_time)
            // 4. 現有時段完全包含新時段 (start_time <= new_start AND end_time >= new_end)
            $query->where(function ($subQuery) use ($startTime, $endTime) {
                // 使用標準的時間重疊檢查：兩個時間段重疊當且僅當 max(start1,start2) < min(end1,end2)
                $subQuery->where('start_time', '<', $endTime->toDateTimeString())
                         ->where('end_time', '>', $startTime->toDateTimeString());
            });
        })->exists();

        if ($conflictExists) {
            return response()->json([
                'success' => false,
                'message' => '此時段與現有時段衝突'
            ], 422);
        }

        $availableTime = AvailableTime::create([
            'title' => strip_tags($request->title), // 防止XSS
            'description' => strip_tags($request->description),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'max_capacity' => $request->max_capacity,
            'is_active' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => '可預約時段創建成功',
            'data' => $availableTime
        ], 201);
    }

    // 更新可預約時段
    public function update(Request $request, AvailableTime $availableTime)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'max_capacity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean'
        ]);

        // 如果有預約且要修改容量，檢查新容量是否足夠（使用 accessor）
        if ($request->has('max_capacity')) {
            $currentBookings = $availableTime->current_bookings;
            if ($request->max_capacity < $currentBookings) {
                return response()->json([
                    'success' => false,
                    'message' => '新容量不能小於現有預約數量'
                ], 422);
            }
        }

        $availableTime->update($request->only([
            'title', 'description', 'start_time', 'end_time', 'max_capacity', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => '可預約時段已更新',
            'data' => $availableTime->fresh()
        ]);
    }

    // 刪除可預約時段
    public function destroy(AvailableTime $availableTime)
    {
        // 檢查是否有預約（使用 accessor）
        $currentBookings = $availableTime->current_bookings;
        
        if ($currentBookings > 0) {
            return response()->json([
                'success' => false,
                'message' => '此時段已有預約，無法刪除'
            ], 422);
        }

        $availableTime->delete();

        return response()->json([
            'success' => true,
            'message' => '可預約時段已刪除'
        ]);
    }
}
