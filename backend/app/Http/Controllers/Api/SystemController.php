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
     * 獲取系統警報
     */
    public function alerts()
    {
        try {
            $alerts = [];
            
            // 檢查 CPU 使用率
            $cpu = $this->getCpuUsage();
            if ($cpu !== null && $cpu > 80) {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'cpu',
                    'title' => 'CPU 使用率過高',
                    'message' => "當前 CPU 使用率為 {$cpu}%，超過警戒值 80%",
                    'value' => $cpu,
                    'threshold' => 80,
                    'timestamp' => now()->toIso8601String()
                ];
            } elseif ($cpu !== null && $cpu > 60) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'cpu',
                    'title' => 'CPU 使用率偏高',
                    'message' => "當前 CPU 使用率為 {$cpu}%",
                    'value' => $cpu,
                    'threshold' => 60,
                    'timestamp' => now()->toIso8601String()
                ];
            }
            
            // 檢查記憶體使用率
            $memory = $this->getMemoryUsage();
            if ($memory !== null && $memory > 85) {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'memory',
                    'title' => '記憶體使用率過高',
                    'message' => "當前記憶體使用率為 {$memory}%，超過警戒值 85%",
                    'value' => $memory,
                    'threshold' => 85,
                    'timestamp' => now()->toIso8601String()
                ];
            } elseif ($memory !== null && $memory > 70) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'memory',
                    'title' => '記憶體使用率偏高',
                    'message' => "當前記憶體使用率為 {$memory}%",
                    'value' => $memory,
                    'threshold' => 70,
                    'timestamp' => now()->toIso8601String()
                ];
            }
            
            // 檢查磁碟使用率
            $disk = $this->getDiskUsage();
            if ($disk !== null && $disk > 90) {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'disk',
                    'title' => '磁碟空間不足',
                    'message' => "當前磁碟使用率為 {$disk}%，超過警戒值 90%",
                    'value' => $disk,
                    'threshold' => 90,
                    'timestamp' => now()->toIso8601String()
                ];
            } elseif ($disk !== null && $disk > 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'disk',
                    'title' => '磁碟空間偏低',
                    'message' => "當前磁碟使用率為 {$disk}%",
                    'value' => $disk,
                    'threshold' => 80,
                    'timestamp' => now()->toIso8601String()
                ];
            }
            
            // 檢查資料庫連接
            $dbStatus = $this->getDatabaseStatus();
            if ($dbStatus === 'disconnected') {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'database',
                    'title' => '資料庫連接失敗',
                    'message' => '無法連接到資料庫',
                    'timestamp' => now()->toIso8601String()
                ];
            }
            
            // 檢查資料庫響應時間
            $dbResponseTime = $this->getDatabaseResponseTime();
            if ($dbResponseTime !== null && $dbResponseTime > 500) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'database',
                    'title' => '資料庫響應緩慢',
                    'message' => "當前資料庫響應時間為 {$dbResponseTime}ms，超過建議值 500ms",
                    'value' => $dbResponseTime,
                    'threshold' => 500,
                    'timestamp' => now()->toIso8601String()
                ];
            }
            
            // 檢查儲存空間
            $storagePercentage = $this->getStoragePercentage();
            if ($storagePercentage > 90) {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'storage',
                    'title' => '儲存空間嚴重不足',
                    'message' => "當前儲存空間使用率為 {$storagePercentage}%",
                    'value' => $storagePercentage,
                    'threshold' => 90,
                    'timestamp' => now()->toIso8601String()
                ];
            } elseif ($storagePercentage > 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'storage',
                    'title' => '儲存空間偏低',
                    'message' => "當前儲存空間使用率為 {$storagePercentage}%",
                    'value' => $storagePercentage,
                    'threshold' => 80,
                    'timestamp' => now()->toIso8601String()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'total' => count($alerts),
                    'error_count' => count(array_filter($alerts, fn($a) => $a['type'] === 'error')),
                    'warning_count' => count(array_filter($alerts, fn($a) => $a['type'] === 'warning')),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取系統警報失敗',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }

    /**
     * 獲取效能歷史數據
     */
    public function performanceHistory()
    {
        try {
            // 獲取過去 24 小時的數據點
            $hours = 24;
            $history = [];
            
            for ($i = $hours - 1; $i >= 0; $i--) {
                $hourKey = now()->subHours($i)->format('YmdH');
                
                // 從 Cache 獲取該小時的數據
                $responses = Cache::get("api_responses_{$hourKey}", []);
                $requests = Cache::get("api_requests_{$hourKey}", 0);
                
                $avgResponseTime = 0;
                if (count($responses) > 0) {
                    $avgResponseTime = round(array_sum($responses) / count($responses), 2);
                }
                
                $history[] = [
                    'time' => now()->subHours($i)->format('H:00'),
                    'timestamp' => now()->subHours($i)->toIso8601String(),
                    'requests' => $requests,
                    'avgResponseTime' => $avgResponseTime,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $history,
                    'period' => '24h'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取效能歷史數據失敗',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }

    /**
     * 獲取租戶活動統計
     */
    public function tenantActivity()
    {
        try {
            // 獲取所有租戶的活動統計
            $tenants = Tenant::with(['users', 'reservations' => function($query) {
                $query->where('created_at', '>=', now()->subDays(7));
            }])
            ->withCount([
                'users',
                'reservations as total_reservations',
                'reservations as week_reservations' => function($query) {
                    $query->where('created_at', '>=', now()->subDays(7));
                },
                'reservations as today_reservations' => function($query) {
                    $query->whereDate('created_at', today());
                }
            ])
            ->orderBy('week_reservations', 'desc')
            ->limit(10)
            ->get();

            $activities = $tenants->map(function($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                    'users_count' => $tenant->users_count,
                    'total_reservations' => $tenant->total_reservations,
                    'week_reservations' => $tenant->week_reservations,
                    'today_reservations' => $tenant->today_reservations,
                    'activity_score' => $this->calculateActivityScore($tenant)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tenants' => $activities,
                    'period' => '7d'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取租戶活動統計失敗',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }

    /**
     * 計算租戶活動分數
     */
    private function calculateActivityScore($tenant)
    {
        $score = 0;
        
        // 根據本週預約數計分
        $score += $tenant->week_reservations * 10;
        
        // 根據今日預約數額外加分
        $score += $tenant->today_reservations * 5;
        
        // 根據用戶數計分
        $score += $tenant->users_count * 2;
        
        return $score;
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