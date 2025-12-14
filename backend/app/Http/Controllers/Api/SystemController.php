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
     * 獲取 CPU 使用率
     */
    private function getCpuUsage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            // 轉換為百分比（假設單核心）
            return round($load[0] * 100, 1);
        }
        
        return null;
    }

    /**
     * 獲取記憶體使用率
     */
    private function getMemoryUsage()
    {
        if (function_exists('memory_get_usage')) {
            $used = memory_get_usage(true);
            $limit = ini_get('memory_limit');
            
            if ($limit !== '-1') {
                $limit = $this->convertToBytes($limit);
                return round(($used / $limit) * 100, 1);
            }
        }
        
        return null;
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
        
        return null;
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
            $max = $result[0]->Value ?? 0;
            
            return ['active' => (int)$active, 'max' => (int)$max];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 獲取資料庫響應時間（毫秒）
     */
    private function getDatabaseResponseTime()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $end = microtime(true);
            
            return round(($end - $start) * 1000, 2);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 獲取儲存空間總量（位元組）
     */
    private function getStorageTotal()
    {
        $path = storage_path();
        if (function_exists('disk_total_space')) {
            return disk_total_space($path) ?: null;
        }
        
        return null;
    }

    /**
     * 獲取儲存空間使用量（位元組）
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
        
        return null;
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
     * 獲取 API 平均響應時間（毫秒）
     */
    private function getApiResponseTime()
    {
        // 從 Cache 獲取過去一小時的平均響應時間
        return Cache::get('api_avg_response_time', null);
    }

    /**
     * 獲取 API 吞吐量（每小時請求數）
     */
    private function getApiThroughput()
    {
        // 從 Cache 獲取過去一小時的請求數
        return Cache::get('api_requests_per_hour', null);
    }

    /**
     * 獲取系統運行時間
     */
    private function getSystemUptime()
    {
        // 優先從 Cache 獲取應用啟動時間
        $appStartTime = Cache::get('app_start_time');
        
        if ($appStartTime) {
            $uptimeSeconds = now()->diffInSeconds($appStartTime);
            return [
                'seconds' => $uptimeSeconds,
                'days' => floor($uptimeSeconds / 86400),
                'hours' => floor(($uptimeSeconds % 86400) / 3600),
                'minutes' => floor(($uptimeSeconds % 3600) / 60),
                'formatted' => $this->formatUptime($uptimeSeconds)
            ];
        }
        
        // 備選方案：從 /proc/uptime 讀取系統運行時間（Linux）
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptimeSeconds = (float) explode(' ', $uptime)[0];
            return [
                'seconds' => round($uptimeSeconds),
                'days' => floor($uptimeSeconds / 86400),
                'hours' => floor(($uptimeSeconds % 86400) / 3600),
                'minutes' => floor(($uptimeSeconds % 3600) / 60),
                'formatted' => $this->formatUptime($uptimeSeconds)
            ];
        }
        
        return null;
    }

    /**
     * 格式化運行時間
     */
    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = $days . ' 天';
        if ($hours > 0) $parts[] = $hours . ' 小時';
        if ($minutes > 0 || empty($parts)) $parts[] = $minutes . ' 分鐘';
        
        return implode(' ', $parts);
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