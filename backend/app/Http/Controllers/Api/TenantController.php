<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * TenantController
 * 
 * 租戶管理控制器，僅限系統管理員使用。
 */
class TenantController extends Controller
{
    /**
     * 取得所有租戶列表
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        // 搜尋
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // 狀態過濾
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 排序
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 分頁
        $perPage = $request->get('per_page', 15);
        $tenants = $query->withCount(['users', 'customers', 'reservations'])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * 取得單一租戶詳情
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['users']);
        $tenant->loadCount(['customers', 'reservations', 'services', 'availableTimes']);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'webhook_url' => $tenant->full_webhook_url,
            ],
        ]);
    }

    /**
     * 建立新租戶
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'plan' => 'nullable|string|in:basic,standard,premium',
            'subscription_type' => 'nullable|string|in:trial,subscription',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            // 根據訂閱類型設定狀態和到期日
            $subscriptionType = $validated['subscription_type'] ?? 'trial';
            $status = $subscriptionType === 'subscription' ? 'active' : 'trial';
            
            $trialEndsAt = null;
            $subscriptionEndsAt = null;
            
            if ($subscriptionType === 'trial') {
                $trialEndsAt = $validated['trial_ends_at'] ?? now()->addDays(14);
            } else {
                $subscriptionEndsAt = $validated['subscription_ends_at'] ?? now()->addYear();
            }

            // 建立租戶
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'plan' => $validated['plan'] ?? 'basic',
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'subscription_ends_at' => $subscriptionEndsAt,
            ]);

            // 產生臨時密碼
            $temporaryPassword = Str::random(12);

            // 建立租戶管理員帳號
            $user = User::create([
                'name' => $validated['name'] . ' Admin',
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => 'admin',
                'status' => 'Active',
                'tenant_id' => $tenant->id,
                'must_change_password' => true,
            ]);

            DB::commit();

            // 記錄操作
            ActivityLogger::custom(
                'created',
                'tenants',
                "建立新租戶: {$tenant->name}",
                [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'admin_email' => $user->email,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '租戶建立成功',
                'data' => [
                    'tenant' => $tenant,
                    'admin_user' => [
                        'email' => $user->email,
                        'temporary_password' => $temporaryPassword,
                    ],
                    'webhook_url' => $tenant->full_webhook_url,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => '租戶建立失敗: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新租戶
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('tenants')->ignore($tenant->id)],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'status' => 'sometimes|in:active,inactive,suspended,trial',
            'plan' => 'sometimes|in:basic,standard,premium',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
        ]);

        $oldValues = $tenant->toArray();

        $tenant->update($validated);

        // 記錄操作
        ActivityLogger::custom(
            'updated',
            'tenants',
            "更新租戶: {$tenant->name}",
            [
                'tenant_id' => $tenant->id,
                'changes' => array_diff_assoc($validated, $oldValues),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '租戶更新成功',
            'data' => $tenant,
        ]);
    }

    /**
     * 更新租戶狀態
     */
    public function updateStatus(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended,trial',
        ]);

        $oldStatus = $tenant->status;
        $tenant->update(['status' => $validated['status']]);

        // 記錄操作
        ActivityLogger::custom(
            'updated',
            'tenants',
            "更新租戶狀態: {$tenant->name}",
            [
                'tenant_id' => $tenant->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '租戶狀態更新成功',
            'data' => $tenant,
        ]);
    }

    /**
     * 更新租戶訂閱
     */
    public function updateSubscription(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'plan' => 'sometimes|in:basic,standard,premium',
            'subscription_ends_at' => 'required|date|after:today',
        ]);

        $tenant->update([
            'plan' => $validated['plan'] ?? $tenant->plan,
            'subscription_ends_at' => $validated['subscription_ends_at'],
            'status' => 'active',
        ]);

        // 記錄操作
        ActivityLogger::custom(
            'updated',
            'tenants',
            "更新租戶訂閱: {$tenant->name}",
            [
                'tenant_id' => $tenant->id,
                'plan' => $tenant->plan,
                'subscription_ends_at' => $validated['subscription_ends_at'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '租戶訂閱更新成功',
            'data' => $tenant,
        ]);
    }

    /**
     * 重設租戶管理員密碼
     */
    public function resetAdminPassword(Request $request, Tenant $tenant)
    {
        $admin = $tenant->users()->where('role', 'admin')->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => '找不到租戶管理員',
            ], 404);
        }

        $temporaryPassword = Str::random(12);
        
        $admin->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        // 記錄操作
        ActivityLogger::custom(
            'updated',
            'tenants',
            "重設租戶管理員密碼: {$tenant->name}",
            [
                'tenant_id' => $tenant->id,
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '密碼重設成功',
            'data' => [
                'email' => $admin->email,
                'temporary_password' => $temporaryPassword,
            ],
        ]);
    }

    /**
     * 刪除租戶
     */
    public function destroy(Tenant $tenant)
    {
        $tenantName = $tenant->name;
        $tenantId = $tenant->id;

        // 軟刪除租戶
        $tenant->delete();

        // 記錄操作
        ActivityLogger::custom(
            'deleted',
            'tenants',
            "刪除租戶: {$tenantName}",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '租戶已刪除',
        ]);
    }

    /**
     * 取得租戶統計資訊
     */
    public function statistics()
    {
        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'trial' => Tenant::where('status', 'trial')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'inactive' => Tenant::where('status', 'inactive')->count(),
            'expiring_soon' => Tenant::where('status', 'active')
                ->where('subscription_ends_at', '<=', now()->addDays(7))
                ->where('subscription_ends_at', '>', now())
                ->count(),
            'trial_expiring_soon' => Tenant::where('status', 'trial')
                ->where('trial_ends_at', '<=', now()->addDays(3))
                ->where('trial_ends_at', '>', now())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
