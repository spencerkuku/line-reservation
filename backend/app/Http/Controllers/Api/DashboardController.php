<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Reservation;
use App\Models\AvailableTime;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // 獲取統計數據
    public function stats()
    {
        // 基本統計
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'Active')->count(),
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'vip_customers' => Customer::whereRaw('(
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 20 OR (
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 5000')->count(),
            'total_services' => Service::count(),
            'active_services' => Service::where('is_active', true)->count(),
            'total_reservations' => Reservation::count(),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'confirmed_reservations' => Reservation::where('status', 'confirmed')->count(),
            'available_times' => AvailableTime::where('is_active', true)
                ->where('start_time', '>=', now())
                ->count(),
            'today_reservations' => Reservation::whereDate('reservation_date', today())->count(),
            'this_week_reservations' => Reservation::whereBetween('reservation_date', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month_reservations' => Reservation::whereMonth('reservation_date', now()->month)
                ->whereYear('reservation_date', now()->year)
                ->count(),
        ];

        // 計算本月營收 - 基於實際收到的付款金額
        $this_month_revenue = Reservation::whereMonth('reservation_date', now()->month)
            ->whereYear('reservation_date', now()->year)
            ->whereNotNull('payment_amount')
            ->sum('payment_amount');
        
        $stats['this_month_revenue'] = $this_month_revenue;
        
        // 總營收統計 - 基於實際收到的付款金額
        $total_revenue = Reservation::whereNotNull('payment_amount')
            ->sum('payment_amount');
        
        $stats['total_revenue'] = $total_revenue;
        
        // 平均每筆預約金額
        $avg_reservation_value = $stats['confirmed_reservations'] > 0 
            ? round($total_revenue / $stats['confirmed_reservations'], 2) 
            : 0;
        
        $stats['avg_reservation_value'] = $avg_reservation_value;
        
        // 新客戶統計
        $stats['new_customers_this_month'] = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        // 客戶等級分布 - 使用子查詢計算實際統計數據
        $stats['customer_levels'] = [
            'VIP' => Customer::whereRaw('(
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 20 OR (
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 5000')->count(),
            
            'Gold' => Customer::whereRaw('((
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 10 AND (
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 20) OR ((
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 3000 AND (
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 5000)')->count(),
            
            'Silver' => Customer::whereRaw('((
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 5 AND (
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 10) OR ((
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) >= 1000 AND (
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 3000)')->count(),
            
            'Bronze' => Customer::whereRaw('(
                SELECT COUNT(*) FROM reservations 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 5 AND (
                SELECT COALESCE(SUM(services.price), 0) 
                FROM reservations 
                LEFT JOIN services ON reservations.service_id = services.id 
                WHERE reservations.customer_id = customers.id 
                AND reservations.status = "confirmed"
            ) < 1000')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // 獲取最近預約
    public function reservations()
    {
        $reservations = Reservation::with(['customer', 'service'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    // 獲取熱門服務
    public function popularServices()
    {
        $popularServices = Service::with(['reservations' => function($query) {
                $query->whereMonth('reservation_date', now()->month)
                      ->whereYear('reservation_date', now()->year);
            }])
            ->withCount(['reservations as month_count' => function($query) {
                $query->where('status', 'confirmed')
                      ->whereMonth('reservation_date', now()->month)
                      ->whereYear('reservation_date', now()->year);
            }])
            ->get()
            ->map(function($service) {
                // 計算本月營收 - 基於實際收到的付款金額
                $monthRevenue = $service->reservations
                    ->whereNotNull('payment_amount')
                    ->sum('payment_amount');
                
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'count' => $service->month_count,
                    'month_revenue' => $monthRevenue,
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
    }

    // 獲取通知
    public function notices()
    {
        $notices = [];

        // 待確認的預約
        $pendingCount = Reservation::where('status', 'pending')->count();
        if ($pendingCount > 0) {
            $notices[] = [
                'type' => 'warning',
                'title' => '待確認預約',
                'message' => "您有 {$pendingCount} 筆預約待確認",
                'action' => '/reservations'
            ];
        }

        // 今日預約
        $todayCount = Reservation::whereDate('reservation_date', today())
            ->where('status', 'confirmed')
            ->count();
        if ($todayCount > 0) {
            $notices[] = [
                'type' => 'info',
                'title' => '今日預約',
                'message' => "今日有 {$todayCount} 筆確認預約",
                'action' => '/reservations'
            ];
        }

        // 非活躍服務
        $inactiveServicesCount = Service::where('is_active', false)->count();
        if ($inactiveServicesCount > 0) {
            $notices[] = [
                'type' => 'warning',
                'title' => '非活躍服務',
                'message' => "有 {$inactiveServicesCount} 項服務已停用",
                'action' => '/services'
            ];
        }

        // 封鎖用戶
        $blockedUsersCount = User::where('status', 'Inactive')->count();
        if ($blockedUsersCount > 0) {
            $notices[] = [
                'type' => 'info',
                'title' => '封鎖用戶',
                'message' => "目前有 {$blockedUsersCount} 位用戶被封鎖",
                'action' => '/users'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $notices
        ]);
    }
}
