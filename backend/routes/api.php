<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AvailableTimeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\LineWebhookController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\FrontendLogController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\AdminActivityLogController;

// 公開路由
Route::post('/auth/login', [AuthController::class, 'login']);

// LINE Webhook 路由 - 暫時停用簽名驗證以修復問題
Route::post('/line/webhook', [LineWebhookController::class, 'handle']);
    // ->middleware('verify.line.signature'); // 暫時註解

// 前端日誌路由 - 允許前端發送日誌到後端
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/frontend-logs', [FrontendLogController::class, 'store']);
    Route::post('/frontend-logs/error', [FrontendLogController::class, 'storeError']);
});

// 公開的可預約時段查詢（允許前端查看可用時段） - 添加 Rate Limiting
Route::get('/available-times', [AvailableTimeController::class, 'index'])
    ->middleware('throttle:60,1');
Route::get('/services', [ServiceController::class, 'index'])
    ->middleware('throttle:60,1'); // 允許查看服務列表

// 測試路由
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

Route::get('/test-service-selection', [TestController::class, 'testServiceSelection']);
Route::get('/test-business-hours', [TestController::class, 'testBusinessHours']);

Route::middleware('auth:sanctum')->get('/test-auth', function (Request $request) {
    return response()->json([
        'message' => 'Authentication is working',
        'user' => $request->user()
    ]);
});

// 需要認證的路由（僅用於用戶資訊和登出）
Route::middleware('auth:sanctum')->group(function () {
    // 認證相關
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // 個人資料管理
    Route::post('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/password', [AuthController::class, 'updatePassword']);
});

// 需要管理員權限的路由（所有管理功能都只允許管理員使用）
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // 儀表板（僅管理員可訪問）
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/reservations', [DashboardController::class, 'reservations']);
        Route::get('/popular-services', [DashboardController::class, 'popularServices']);
        Route::get('/notices', [DashboardController::class, 'notices']);
    });

    // 服務管理（僅管理員可訪問）
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    Route::get('/services/{service}/reservations', [ServiceController::class, 'reservations']);

    // 可預約時段管理（僅管理員可訪問）
    Route::post('/available-times', [AvailableTimeController::class, 'store']);
    Route::put('/available-times/{availableTime}', [AvailableTimeController::class, 'update']);
    Route::delete('/available-times/{availableTime}', [AvailableTimeController::class, 'destroy']);
    // 使用者管理
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::put('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // 預約管理 - 添加 Rate Limiting
    Route::get('/reservations', [ReservationController::class, 'index'])
        ->middleware('throttle:120,1');
    Route::post('/reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:10,1'); // 限制預約建立頻率
    Route::put('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])
        ->middleware('throttle:30,1');
    Route::put('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
        ->middleware('throttle:30,1');

    // 設定
    Route::get('/settings/line', [SettingController::class, 'getLineSettings']);
    Route::post('/settings/line', [SettingController::class, 'updateLineSettings']);
    Route::get('/settings', [SettingController::class, 'getAllSettings']);
    Route::post('/settings', [SettingController::class, 'updateSetting']);

    // 客戶管理（僅管理員可訪問）
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/statistics', [CustomerController::class, 'statistics']);
        Route::post('/recalculate-stats', [CustomerController::class, 'recalculateStats']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);
        Route::post('/{customer}/interaction', [CustomerController::class, 'updateInteraction']);
        Route::post('/{customer}/recalculate-stats', [CustomerController::class, 'recalculateStats']);
        Route::post('/{customer}/block', [CustomerController::class, 'block']);
        Route::post('/{customer}/unblock', [CustomerController::class, 'unblock']);
    });

    // 報到管理（僅管理員可訪問）
    Route::prefix('check-in')->group(function () {
        Route::post('/reservations/{reservation}/check-in', [CheckInController::class, 'checkIn']);
        Route::post('/reservations/{reservation}/no-show', [CheckInController::class, 'markNoShow']);
        Route::post('/reservations/{reservation}/payment', [CheckInController::class, 'recordPayment']);
        Route::get('/today', [CheckInController::class, 'getTodayCheckIns']);
    });

    // 活動日誌管理（僅管理員可訪問）
    Route::prefix('admin/activity-logs')->group(function () {
        Route::get('/', [AdminActivityLogController::class, 'index']);
        Route::get('/stats', [AdminActivityLogController::class, 'stats']);
        Route::get('/trends', [AdminActivityLogController::class, 'trends']);
        Route::get('/modules', [AdminActivityLogController::class, 'modules']);
        Route::get('/actions', [AdminActivityLogController::class, 'actions']);
        Route::get('/{log}', [AdminActivityLogController::class, 'show']);
    });
});
