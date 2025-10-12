<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Reservation;
use App\Models\AvailableTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // 獲取統計數據
    public function stats()
    {
        try {
            $today = Carbon::today();
            $thisWeekStart = Carbon::now()->startOfWeek();
            $thisWeekEnd = Carbon::now()->endOfWeek();
            $thisMonth = Carbon::now()->month;
            $thisYear = Carbon::now()->year;

            // 基本統計
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'Active')->count(),
                'total_customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'active')->count(),
                'vip_customers' => Customer::whereRaw('(
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 20 OR (
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 5000')->count(),
                'total_services' => Service::count(),
                'active_services' => Service::where('is_active', true)->count(),
                'total_reservations' => Reservation::count(),
                'pending_reservations' => Reservation::where('status', 'pending')->count(),
                'confirmed_reservations' => Reservation::where('status', 'confirmed')->count(),
                'completed_reservations' => Reservation::where('status', 'completed')->count(),
                'cancelled_reservations' => Reservation::where('status', 'cancelled')->count(),
                'available_times' => AvailableTime::where('is_active', true)
                    ->where('start_time', '>=', now())
                    ->count(),
                'today_reservations' => Reservation::whereDate('reservation_date', $today)->count(),
                'this_week_reservations' => Reservation::whereBetween('reservation_date', [
                    $thisWeekStart,
                    $thisWeekEnd
                ])->count(),
                'this_month_reservations' => Reservation::whereMonth('reservation_date', $thisMonth)
                    ->whereYear('reservation_date', $thisYear)
                    ->count(),
            ];

            // 計算本月營收 - 基於實際收到的付款金額
            $this_month_revenue = Reservation::whereMonth('reservation_date', $thisMonth)
                ->whereYear('reservation_date', $thisYear)
                ->where('payment_status', 'paid')
                ->sum('payment_amount') ?? 0;
            
            $stats['this_month_revenue'] = floatval($this_month_revenue);
            
            // 總營收統計 - 基於實際收到的付款金額
            $total_revenue = Reservation::where('payment_status', 'paid')
                ->sum('payment_amount') ?? 0;
            
            $stats['total_revenue'] = floatval($total_revenue);
            
            // 平均每筆預約金額
            $completedCount = $stats['completed_reservations'];
            $avg_reservation_value = $completedCount > 0 
                ? round($total_revenue / $completedCount, 2) 
                : 0;
            
            $stats['avg_reservation_value'] = floatval($avg_reservation_value);
            
            // 新客戶統計
            $stats['new_customers_this_month'] = Customer::whereMonth('created_at', $thisMonth)
                ->whereYear('created_at', $thisYear)
                ->count();
                
            // 客戶等級分布 - 使用子查詢計算實際統計數據
            $stats['customer_levels'] = [
                'VIP' => Customer::whereRaw('(
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 20 OR (
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 5000')->count(),
                
                'Gold' => Customer::whereRaw('((
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 10 AND (
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 20) OR ((
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 3000 AND (
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 5000)')->count(),
                
                'Silver' => Customer::whereRaw('((
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 5 AND (
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 10) OR ((
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) >= 1000 AND (
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 3000)')->count(),
                
                'Bronze' => Customer::whereRaw('(
                    SELECT COUNT(*) FROM reservations 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 5 AND (
                    SELECT COALESCE(SUM(services.price), 0) 
                    FROM reservations 
                    LEFT JOIN services ON reservations.service_id = services.id 
                    WHERE reservations.customer_id = customers.id 
                    AND reservations.status = "completed"
                ) < 1000')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats fetch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得統計資料失敗',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // 獲取最近預約
    public function reservations()
    {
        try {
            $reservations = Reservation::with(['customer', 'service'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($reservation) {
                    // 組合預約日期和時間
                    $reservationDateTime = null;
                    if ($reservation->reservation_date && $reservation->reservation_time) {
                        try {
                            // 確保日期格式正確
                            $dateStr = $reservation->reservation_date instanceof \Carbon\Carbon 
                                ? $reservation->reservation_date->format('Y-m-d')
                                : $reservation->reservation_date;
                            
                            $reservationDateTime = Carbon::parse($dateStr . ' ' . $reservation->reservation_time);
                        } catch (\Exception $e) {
                            // 如果解析失敗，使用原始值
                            $reservationDateTime = $reservation->reservation_date;
                        }
                    }

                    return [
                        'id' => $reservation->id,
                        'customer' => $reservation->customer ? [
                            'id' => $reservation->customer->id,
                            'name' => $reservation->customer->name,
                            'line_display_name' => $reservation->customer->line_display_name,
                            'phone' => $reservation->customer->phone,
                        ] : null,
                        'customer_name' => $reservation->reservation_name,
                        'service' => $reservation->service ? [
                            'id' => $reservation->service->id,
                            'name' => $reservation->service->name,
                            'price' => floatval($reservation->service->price ?? 0),
                            'duration' => $reservation->service->duration,
                        ] : null,
                        'reservation_date' => $reservationDateTime ? $reservationDateTime->toIso8601String() : null,
                        'reservation_time' => $reservation->reservation_time,
                        'status' => $reservation->status,
                        'check_in_status' => $reservation->check_in_status,
                        'payment_status' => $reservation->payment_status,
                        'notes' => $reservation->notes,
                        'created_at' => $reservation->created_at?->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $reservations
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard reservations fetch failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得最近預約失敗',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // 獲取熱門服務
    public function popularServices()
    {
        try {
            $thisMonth = Carbon::now()->month;
            $thisYear = Carbon::now()->year;

            $popularServices = Service::with(['reservations' => function($query) use ($thisMonth, $thisYear) {
                    $query->whereMonth('reservation_date', $thisMonth)
                          ->whereYear('reservation_date', $thisYear);
                }])
                ->withCount(['reservations as month_count' => function($query) use ($thisMonth, $thisYear) {
                    $query->whereIn('status', ['confirmed', 'completed'])
                          ->whereMonth('reservation_date', $thisMonth)
                          ->whereYear('reservation_date', $thisYear);
                }])
                ->get()
                ->map(function($service) {
                    // 計算本月營收 - 基於實際收到的付款金額
                    $monthRevenue = $service->reservations
                        ->where('payment_status', 'paid')
                        ->sum('payment_amount') ?? 0;
                    
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => floatval($service->price ?? 0),
                        'count' => $service->month_count,
                        'month_revenue' => floatval($monthRevenue),
                        'duration' => $service->duration,
                        'description' => $service->description,
                        'is_active' => $service->is_active,
                    ];
                })
                ->sortByDesc('count')
                ->take(5)
                ->values();

            return response()->json([
                'success' => true,
                'data' => $popularServices
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard popular services fetch failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得熱門服務失敗',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // 獲取通知
    public function notices()
    {
        try {
            $notices = [];

            // 待確認的預約
            $pendingCount = Reservation::where('status', 'pending')->count();
            if ($pendingCount > 0) {
                $notices[] = [
                    'type' => 'warning',
                    'title' => '待確認預約',
                    'message' => "您有 {$pendingCount} 筆預約待確認",
                    'action' => '/reservations',
                    'created_at' => now(),
                ];
            }

            // 今日預約
            $todayCount = Reservation::whereDate('reservation_date', today())
                ->whereIn('status', ['confirmed', 'pending'])
                ->count();
            if ($todayCount > 0) {
                $notices[] = [
                    'type' => 'info',
                    'title' => '今日預約',
                    'message' => "今日有 {$todayCount} 筆預約",
                    'action' => '/reservations',
                    'created_at' => now(),
                ];
            }

            // 非活躍服務
            $inactiveServicesCount = Service::where('is_active', false)->count();
            if ($inactiveServicesCount > 0) {
                $notices[] = [
                    'type' => 'warning',
                    'title' => '非活躍服務',
                    'message' => "有 {$inactiveServicesCount} 項服務已停用",
                    'action' => '/services',
                    'created_at' => now(),
                ];
            }

            // 封鎖用戶
            $blockedUsersCount = User::where('status', 'Inactive')->count();
            if ($blockedUsersCount > 0) {
                $notices[] = [
                    'type' => 'info',
                    'title' => '封鎖用戶',
                    'message' => "目前有 {$blockedUsersCount} 位用戶被封鎖",
                    'action' => '/users',
                    'created_at' => now(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $notices
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard notices fetch failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得系統通知失敗',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
