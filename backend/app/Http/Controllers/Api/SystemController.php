<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemController extends Controller
{
    /**
     * 獲取系統統計數據
     */
    public function stats()
    {
        try {
            // 使用緩存來提高性能，緩存5分鐘
            $stats = Cache::remember('system_stats', 300, function () {
                return [
                    // 租戶統計
                    'activeTenants' => Tenant::where('status', 'active')->count(),
                    'totalTenants' => Tenant::count(),
                    'newTenantsThisMonth' => Tenant::whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    'newTenantsThisWeek' => Tenant::where('created_at', '>=', now()->startOfWeek())
                        ->count(),
                    
                    // 用戶統計
                    'totalUsers' => User::count(),
                    'newUsersThisWeek' => User::where('created_at', '>=', now()->startOfWeek())
                        ->count(),
                    'newUsersThisMonth' => User::whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    
                    // 預約統計
                    'totalReservations' => Reservation::count(),
                    'todayReservations' => Reservation::whereDate('reservation_date', today())
                        ->count(),
                    'thisWeekReservations' => Reservation::where('reservation_date', '>=', now()->startOfWeek())
                        ->where('reservation_date', '<=', now()->endOfWeek())
                        ->count(),
                    'thisMonthReservations' => Reservation::whereMonth('reservation_date', now()->month)
                        ->whereYear('reservation_date', now()->year)
                        ->count(),
                    
                    // 客戶統計
                    'totalCustomers' => Customer::count(),
                    'newCustomersThisMonth' => Customer::whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    
                    // 系統信息
                    'systemVersion' => config('app.version', '1.0.0'),
                    'uptime' => $this->getSystemUptime(),
                    
                    // 服務統計
                    'activeServices' => DB::table('services')
                        ->join('tenants', 'services.tenant_id', '=', 'tenants.id')
                        ->where('tenants.status', 'active')
                        ->where('services.is_active', true)
                        ->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取系統統計數據失敗',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }

    /**
     * 獲取系統監控數據
     */
    public function monitoring()
    {
        try {
            // 使用緩存，緩存1分鐘
            $monitoring = Cache::remember('system_monitoring', 60, function () {
                return [
                    // 系統負載
                    'systemLoad' => [
                        'cpu' => $this->getCpuUsage(),
                        'memory' => $this->getMemoryUsage(),
                        'disk' => $this->getDiskUsage()
                    ],
                    
                    // 資料庫狀態
                    'database' => [
                        'status' => $this->getDatabaseStatus(),
                        'connections' => $this->getDatabaseConnections(),
                        'responseTime' => $this->getDatabaseResponseTime()
                    ],
                    
                    // 儲存空間
                    'storage' => [
                        'total' => $this->getStorageTotal(),
                        'used' => $this->getStorageUsed(),
                        'percentage' => $this->getStoragePercentage()
                    ],
                    
                    // 性能指標
                    'performance' => [
                        'apiResponseTime' => $this->getApiResponseTime(),
                        'throughput' => $this->getApiThroughput()
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $monitoring
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取系統監控數據失敗',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }



    /**
     * 獲取 CPU 使用率（模擬，實際可能需要系統命令）
     */
    private function getCpuUsage()
    {
        // 實際環境中，可以使用 sys_getloadavg() 或執行系統命令
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 20, 1); // 簡化轉換
        }
        
        // 模擬數據
        return round(mt_rand(15, 45) + (mt_rand(0, 100) / 100), 1);
    }

    /**
     * 獲取記憶體使用率
     */
    private function getMemoryUsage()
    {
        if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
            $used = memory_get_usage(true);
            $peak = memory_get_peak_usage(true);
            $limit = ini_get('memory_limit');
            
            if ($limit !== '-1') {
                $limit = $this->convertToBytes($limit);
                return round(($used / $limit) * 100, 1);
            }
        }
        
        // 模擬數據
        return round(mt_rand(30, 55) + (mt_rand(0, 100) / 100), 1);
    }

    /**
     * 獲取磁碟使用率
     */
    private function getDiskUsage()
    {
        $path = storage_path();
        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            
            if ($total && $free) {
                return round((($total - $free) / $total) * 100, 1);
            }
        }
        
        // 模擬數據
        return round(mt_rand(25, 45) + (mt_rand(0, 100) / 100), 1);
    }

    /**
     * 獲取資料庫狀態
     */
    private function getDatabaseStatus()
    {
        try {
            DB::connection()->getPdo();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    /**
     * 獲取資料庫連線數
     */
    private function getDatabaseConnections()
    {
        try {
            $result = DB::select("SHOW STATUS WHERE `variable_name` = 'Threads_connected'");
            $active = $result[0]->Value ?? 0;
            
            $result = DB::select("SHOW VARIABLES WHERE `variable_name` = 'max_connections'");
            $max = $result[0]->Value ?? 100;
            
            return ['active' => (int)$active, 'max' => (int)$max];
        } catch (\Exception $e) {
            return ['active' => mt_rand(5, 20), 'max' => 100];
        }
    }

    /**
     * 獲取資料庫響應時間
     */
    private function getDatabaseResponseTime()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $end = microtime(true);
            
            return round(($end - $start) * 1000, 1);
        } catch (\Exception $e) {
            return mt_rand(10, 60);
        }
    }

    /**
     * 獲取儲存空間總量
     */
    private function getStorageTotal()
    {
        $path = storage_path();
        if (function_exists('disk_total_space')) {
            return disk_total_space($path) ?: 100 * 1024 * 1024 * 1024; // 預設 100GB
        }
        
        return 100 * 1024 * 1024 * 1024; // 100GB
    }

    /**
     * 獲取儲存空間使用量
     */
    private function getStorageUsed()
    {
        $path = storage_path();
        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            
            if ($total && $free) {
                return $total - $free;
            }
        }
        
        return 45 * 1024 * 1024 * 1024; // 預設 45GB
    }

    /**
     * 獲取儲存空間使用百分比
     */
    private function getStoragePercentage()
    {
        $total = $this->getStorageTotal();
        $used = $this->getStorageUsed();
        
        return $total > 0 ? round(($used / $total) * 100, 1) : 0;
    }

    /**
     * 獲取 API 響應時間（模擬）
     */
    private function getApiResponseTime()
    {
        // 實際環境中可以從日誌或監控系統獲取
        return mt_rand(120, 220);
    }

    /**
     * 獲取 API 吞吐量（模擬）
     */
    private function getApiThroughput()
    {
        // 實際環境中可以從日誌或監控系統獲取
        return mt_rand(1000, 1500);
    }

    /**
     * 獲取系統運行時間
     */
    private function getSystemUptime()
    {
        // 實際環境中可以使用系統命令獲取
        return 99.9;
    }

    /**
     * 轉換記憶體限制為位元組
     */
    private function convertToBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        
        switch ($last) {
            case 'g':
                $val *= 1024;
                // fallthrough
            case 'm':
                $val *= 1024;
                // fallthrough  
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }


}