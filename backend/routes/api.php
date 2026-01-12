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
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\AdminActivityLogController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\LineMessageLogController;

// 公開路由
Route::post('/auth/login', [AuthController::class, 'login']);

// LINE Webhook 路由 - 多租戶版本（使用 UUID token 確保唯一性）
Route::post('/webhook/{webhook_token}', [LineWebhookController::class, 'handle'])
    ->middleware('webhook.tenant')
    ->where('webhook_token', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// LINE Webhook 路由 - 舊版（向後兼容）
Route::post('/line/webhook', [LineWebhookController::class, 'handleLegacy']);

// 前端日誌路由 - 允許前端發送日誌到後端
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/frontend-logs', [FrontendLogController::class, 'store']);
    Route::post('/frontend-logs/error', [FrontendLogController::class, 'storeError']);
});

// ===== 公開租戶路由（顧客端使用，需要租戶識別）=====
Route::prefix('{tenant_slug}')->middleware(['throttle:60,1', 'public.tenant'])->group(function () {
    // 公開的可預約時段查詢
    Route::get('/available-times', [AvailableTimeController::class, 'index']);
    // 公開的服務列表
    Route::get('/services', [ServiceController::class, 'index']);
});

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
    
    // 訂閱管理
    Route::get('/subscription', [AuthController::class, 'getSubscription']);
    Route::get('/subscription/usage', [AuthController::class, 'getSubscriptionUsage']);
    
    // 強制修改密碼
    Route::post('/auth/force-change-password', [AuthController::class, 'forceChangePassword']);
});

// ===== 系統管理員專用路由 =====
Route::middleware(['auth:sanctum', 'system.admin'])->prefix('system')->group(function () {
    // 系統統計和監控
    Route::get('/stats', [SystemController::class, 'stats']);
    Route::get('/monitoring', [SystemController::class, 'monitoring']);
    Route::get('/alerts', [SystemController::class, 'alerts']);
    Route::get('/performance-history', [SystemController::class, 'performanceHistory']);
    Route::get('/tenant-activity', [SystemController::class, 'tenantActivity']);
    
    // 租戶管理
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::get('/statistics', [TenantController::class, 'statistics']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{tenant}', [TenantController::class, 'show']);
        Route::put('/{tenant}', [TenantController::class, 'update']);
        Route::delete('/{tenant}', [TenantController::class, 'destroy']);
        Route::put('/{tenant}/status', [TenantController::class, 'updateStatus']);
        Route::put('/{tenant}/subscription', [TenantController::class, 'updateSubscription']);
        Route::post('/{tenant}/reset-password', [TenantController::class, 'resetAdminPassword']);
    });
});

// ===== 租戶管理員路由（需要租戶識別）=====
Route::middleware(['auth:sanctum', 'admin', 'tenant'])->group(function () {
    // 儀表板（僅管理員可訪問）
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/reservations', [DashboardController::class, 'reservations']);
        Route::get('/popular-services', [DashboardController::class, 'popularServices']);
        Route::get('/notices', [DashboardController::class, 'notices']);
    });

    // 服務管理（僅管理員可訪問）
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    Route::get('/services/{service}/reservations', [ServiceController::class, 'reservations']);

    // 可預約時段管理（僅管理員可訪問）
    Route::get('/available-times', [AvailableTimeController::class, 'index']);
    Route::post('/available-times', [AvailableTimeController::class, 'store']);
    Route::put('/available-times/{availableTime}', [AvailableTimeController::class, 'update']);
    Route::delete('/available-times/{availableTime}', [AvailableTimeController::class, 'destroy']);
    Route::post('/available-times/{availableTime}/toggle-status', [AvailableTimeController::class, 'toggleStatus']);
    
    // 使用者管理（租戶內）
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
    Route::put('/reservations/{reservation}/reschedule', [ReservationController::class, 'reschedule'])
        ->middleware('throttle:30,1');

    // 設定
    Route::get('/settings/line', [SettingController::class, 'getLineSettings']);
    Route::post('/settings/line', [SettingController::class, 'updateLineSettings']);
    Route::get('/settings', [SettingController::class, 'getAllSettings']);
    Route::post('/settings', [SettingController::class, 'updateSetting']);
    Route::get('/settings/webhook-url', [SettingController::class, 'getWebhookUrl']);

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

    // LINE 訊息日誌管理（系統管理員和租戶管理員都可訪問）
    Route::prefix('line-message-logs')->group(function () {
        Route::get('/', [LineMessageLogController::class, 'index']);
        Route::get('/stats', [LineMessageLogController::class, 'stats']);
        Route::get('/trends', [LineMessageLogController::class, 'trends']);
        Route::get('/types', [LineMessageLogController::class, 'types']);
        Route::get('/tenants', [LineMessageLogController::class, 'tenants']);
        Route::get('/{log}', [LineMessageLogController::class, 'show']);
    });
});
