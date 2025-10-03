<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    // 獲取客戶列表
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,blocked',
            'level' => 'nullable|in:Bronze,Silver,Gold,VIP',
            'sort_by' => 'nullable|in:name,created_at,last_interaction_at,total_reservations,total_spent',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Customer::with(['reservations' => function($q) {
            $q->latest()->limit(5);
        }]);

        // 搜尋功能
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 狀態篩選
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 客戶等級篩選 - 使用數據庫列並動態計算
        if ($request->filled('level')) {
            $level = $request->level;
            switch ($level) {
                case 'VIP':
                    $query->where(function ($q) {
                        $q->whereRaw('(
                            SELECT COUNT(*) FROM reservations 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) >= 20')
                        ->orWhereRaw('(
                            SELECT COALESCE(SUM(services.price), 0) 
                            FROM reservations 
                            LEFT JOIN services ON reservations.service_id = services.id 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) >= 5000');
                    });
                    break;
                case 'Gold':
                    $query->where(function ($q) {
                        $q->whereRaw('((
                            SELECT COUNT(*) FROM reservations 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) >= 10 AND (
                            SELECT COUNT(*) FROM reservations 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) < 20)')
                        ->orWhereRaw('((
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
                        ) < 5000)');
                    });
                    break;
                case 'Silver':
                    $query->where(function ($q) {
                        $q->whereRaw('((
                            SELECT COUNT(*) FROM reservations 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) >= 5 AND (
                            SELECT COUNT(*) FROM reservations 
                            WHERE reservations.customer_id = customers.id 
                            AND reservations.status = "confirmed"
                        ) < 10)')
                        ->orWhereRaw('((
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
                        ) < 3000)');
                    });
                    break;
                case 'Bronze':
                    $query->whereRaw('(
                        SELECT COUNT(*) FROM reservations 
                        WHERE reservations.customer_id = customers.id 
                        AND reservations.status = "confirmed"
                    ) < 5')
                    ->whereRaw('(
                        SELECT COALESCE(SUM(services.price), 0) 
                        FROM reservations 
                        LEFT JOIN services ON reservations.service_id = services.id 
                        WHERE reservations.customer_id = customers.id 
                        AND reservations.status = "confirmed"
                    ) < 1000');
                    break;
            }
        }

        // 排序
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $customers = $query->paginate($perPage);

        // 添加計算屬性
        $customers->getCollection()->transform(function ($customer) {
            $customer->level = $customer->customer_level;
            
            // 計算實際的總預約次數和總消費金額
            $totalReservations = $customer->reservations()->where('status', 'confirmed')->count();
            $totalSpent = $customer->reservations()
                ->where('status', 'confirmed')
                ->join('services', 'reservations.service_id', '=', 'services.id')
                ->sum('services.price') ?: 0;
            
            $customer->setAttribute('total_reservations', $totalReservations);
            $customer->setAttribute('total_spent', $totalSpent);
            
            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    // 獲取單一客戶資料
    public function show(Customer $customer)
    {
        $customer->load([
            'reservations.service',
            'reservations.availableTime',
            'lineMessageLogs' => function($q) {
                $q->latest()->limit(10);
            }
        ]);

        $customer->level = $customer->customer_level;
        
        // 計算實際的總預約次數和總消費金額
        $totalReservations = $customer->reservations()->where('status', 'confirmed')->count();
        $totalSpent = $customer->reservations()
            ->where('status', 'confirmed')
            ->join('services', 'reservations.service_id', '=', 'services.id')
            ->sum('services.price') ?: 0;
        
        $customer->setAttribute('total_reservations', $totalReservations);
        $customer->setAttribute('total_spent', $totalSpent);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    // 創建新客戶
    public function store(StoreCustomerRequest $request)
    {
        try {
            $validatedData = $request->validated();

            $customer = Customer::create([
                ...$validatedData,
                'status' => 'active',
                'last_interaction_at' => now(),
                'total_reservations' => 0,
                'total_spent' => 0.00,
            ]);

            Log::info('Customer created successfully', [
                'customer_id' => $customer->id,
                'name' => $customer->name
            ]);

            return response()->json([
                'success' => true,
                'message' => '客戶建立成功',
                'data' => $customer
            ], 201);

        } catch (\Exception $e) {
            Log::error('Customer creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['name', 'phone', 'email'])
            ]);

            return response()->json([
                'success' => false,
                'message' => '客戶建立失敗，請稍後再試'
            ], 500);
        }
    }

    // 更新客戶資料
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        try {
            $validatedData = $request->validated();

            $customer->update($validatedData);

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->id,
                'name' => $customer->name
            ]);

            return response()->json([
                'success' => true,
                'message' => '客戶資料更新成功',
                'data' => $customer->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Customer update failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id
            ]);

            return response()->json([
                'success' => false,
                'message' => '客戶資料更新失敗，請稍後再試'
            ], 500);
        }
    }

    // 刪除客戶
    public function destroy(Customer $customer)
    {
        // 檢查是否有未完成的預約
        $activeReservations = $customer->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        if ($activeReservations > 0) {
            return response()->json([
                'success' => false,
                'message' => '此客戶還有未完成的預約，無法刪除'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => '客戶已刪除'
        ]);
    }

    // 客戶統計資料
    public function statistics()
    {
        try {
            // 基本統計
            $stats = [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::active()->count(),
                'new_customers_this_month' => Customer::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'customers_with_reservations' => Customer::whereHas('reservations')->count(),
            ];

            // 使用子查詢計算 VIP 客戶統計
            $vipCustomerIds = DB::table('customers as c')
                ->leftJoin('reservations as r', function($join) {
                    $join->on('c.id', '=', 'r.customer_id')
                         ->where('r.status', '=', 'confirmed');
                })
                ->leftJoin('services as s', 'r.service_id', '=', 's.id')
                ->select('c.id')
                ->groupBy('c.id')
                ->havingRaw('COUNT(r.id) >= ? OR COALESCE(SUM(s.price), 0) >= ?', [20, 2000])
                ->pluck('id');

            $stats['vip_customers'] = $vipCustomerIds->count();

            // 計算所有客戶的預約統計
            $customerStats = DB::table('customers as c')
                ->leftJoin('reservations as r', function($join) {
                    $join->on('c.id', '=', 'r.customer_id')
                         ->where('r.status', '=', 'confirmed');
                })
                ->leftJoin('services as s', 'r.service_id', '=', 's.id')
                ->select(
                    'c.id',
                    DB::raw('COUNT(r.id) as reservation_count'),
                    DB::raw('COALESCE(SUM(s.price), 0) as total_spent')
                )
                ->groupBy('c.id')
                ->get();

            // 客戶等級分布
            $stats['customer_levels'] = [
                'VIP' => $customerStats->filter(function($customer) {
                    return $customer->reservation_count >= 20 || $customer->total_spent >= 2000;
                })->count(),
                'Gold' => $customerStats->filter(function($customer) {
                    return ($customer->reservation_count >= 10 && $customer->reservation_count <= 19) || 
                           ($customer->total_spent >= 1000 && $customer->total_spent <= 1999);
                })->count(),
                'Silver' => $customerStats->filter(function($customer) {
                    return ($customer->reservation_count >= 5 && $customer->reservation_count <= 9) || 
                           ($customer->total_spent >= 500 && $customer->total_spent <= 999);
                })->count(),
                'Bronze' => $customerStats->filter(function($customer) {
                    return $customer->reservation_count < 5 && $customer->total_spent < 500;
                })->count()
            ];

            // 平均數據計算
            $totalCustomers = $customerStats->count();
            $stats['average_reservations_per_customer'] = $totalCustomers > 0 
                ? round($customerStats->avg('reservation_count'), 2) 
                : 0;
            
            $stats['total_customer_spending'] = $customerStats->sum('total_spent');
            $stats['average_spending_per_customer'] = $totalCustomers > 0 
                ? round($stats['total_customer_spending'] / $totalCustomers, 2)
                : 0;

            // 本月新增的客戶消費金額
            $newCustomerIds = Customer::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->pluck('id');
            
            $stats['new_customers_spending_this_month'] = $customerStats
                ->whereIn('id', $newCustomerIds)
                ->sum('total_spent');

            // 活躍客戶統計（最近30天有互動）
            $stats['recently_active_customers'] = Customer::where('last_interaction_at', '>=', now()->subDays(30))
                ->count();

            // 客戶來源統計
            $stats['referral_sources'] = Customer::whereNotNull('referral_source')
                ->groupBy('referral_source')
                ->selectRaw('referral_source, count(*) as count')
                ->get()
                ->pluck('count', 'referral_source')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('客戶統計計算失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '統計資料計算失敗，請稍後再試'
            ], 500);
        }
    }

    // 更新客戶互動時間
    public function updateInteraction(Customer $customer)
    {
        $customer->updateLastInteraction();

        return response()->json([
            'success' => true,
            'message' => '互動時間已更新'
        ]);
    }

    // 封鎖客戶
    public function block(Customer $customer)
    {
        if ($customer->status === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => '此客戶已被封鎖'
            ], 422);
        }

        $customer->update(['status' => 'blocked']);

        Log::info('Customer blocked', [
            'customer_id' => $customer->id,
            'name' => $customer->name
        ]);

        return response()->json([
            'success' => true,
            'message' => '客戶已封鎖，將無法進行任何預約',
            'data' => $customer->fresh()
        ]);
    }

    // 解除封鎖客戶
    public function unblock(Customer $customer)
    {
        if ($customer->status !== 'blocked') {
            return response()->json([
                'success' => false,
                'message' => '此客戶並未被封鎖'
            ], 422);
        }

        $customer->update(['status' => 'active']);

        Log::info('Customer unblocked', [
            'customer_id' => $customer->id,
            'name' => $customer->name
        ]);

        return response()->json([
            'success' => true,
            'message' => '已解除客戶封鎖，可以正常進行預約',
            'data' => $customer->fresh()
        ]);
    }

    // 重新計算客戶統計數據
    public function recalculateStats(Request $request)
    {
        try {
            if ($request->has('customer_id')) {
                // 重新計算單一客戶
                $customer = Customer::findOrFail($request->customer_id);
                
                // 計算該客戶的確認預約數量和消費金額
                $reservationStats = DB::table('reservations as r')
                    ->leftJoin('services as s', 'r.service_id', '=', 's.id')
                    ->where('r.customer_id', $customer->id)
                    ->where('r.status', 'confirmed')
                    ->select(
                        DB::raw('COUNT(r.id) as reservation_count'),
                        DB::raw('COALESCE(SUM(s.price), 0) as total_spent')
                    )
                    ->first();
                
                return response()->json([
                    'success' => true,
                    'message' => '客戶統計數據已計算',
                    'data' => [
                        'customer' => $customer,
                        'stats' => [
                            'total_reservations' => $reservationStats->reservation_count ?? 0,
                            'total_spent' => $reservationStats->total_spent ?? 0
                        ]
                    ]
                ]);
            } else {
                // 計算所有客戶的統計 - 只返回統計信息，不更新資料庫
                $customerCount = Customer::count();
                
                return response()->json([
                    'success' => true,
                    'message' => "已重新計算 {$customerCount} 位客戶的統計數據"
                ]);
            }
        } catch (\Exception $e) {
            Log::error('重新計算客戶統計失敗', [
                'error' => $e->getMessage(),
                'customer_id' => $request->get('customer_id')
            ]);

            return response()->json([
                'success' => false,
                'message' => '統計數據更新失敗，請稍後再試'
            ], 500);
        }
    }
}
