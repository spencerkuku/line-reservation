<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // 客戶等級篩選
        if ($request->filled('level')) {
            $level = $request->level;
            switch ($level) {
                case 'VIP':
                    $query->where(function ($q) {
                        $q->where('total_reservations', '>=', 20)
                          ->orWhere('total_spent', '>=', 2000);
                    });
                    break;
                case 'Gold':
                    $query->where(function ($q) {
                        $q->whereBetween('total_reservations', [10, 19])
                          ->orWhereBetween('total_spent', [1000, 1999]);
                    });
                    break;
                case 'Silver':
                    $query->where(function ($q) {
                        $q->whereBetween('total_reservations', [5, 9])
                          ->orWhereBetween('total_spent', [500, 999]);
                    });
                    break;
                case 'Bronze':
                    $query->where('total_reservations', '<', 5)
                          ->where('total_spent', '<', 500);
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
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::active()->count(),
            'new_customers_this_month' => Customer::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'vip_customers' => Customer::vip()->count(),
            'customers_with_reservations' => Customer::whereHas('reservations')->count(),
            'average_reservations_per_customer' => round(Customer::avg('total_reservations'), 2),
            'total_customer_spending' => Customer::sum('total_spent'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
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
}
