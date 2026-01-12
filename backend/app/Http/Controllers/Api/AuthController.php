<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityLoggingService;
use App\Services\CryptographyService;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->getAuthPassword())) {
            // 記錄登入失敗
            SecurityLoggingService::logLoginFailure(
                $request->email,
                '帳號或密碼錯誤',
                $request
            );
            
            throw ValidationException::withMessages([
                'email' => ['帳號或密碼錯誤'],
            ]);
        }

        if ($user->status !== 'Active') {
            SecurityLoggingService::logLoginFailure(
                $request->email,
                '帳號已被停權或待審核',
                $request
            );
            
            throw ValidationException::withMessages([
                'email' => ['帳號已被停權或待審核'],
            ]);
        }

        // 檢查租戶狀態（暫停或停用的租戶顯示具體原因）
        if ($user->tenant_id && $user->tenant) {
            $tenant = $user->tenant;
            
            if ($tenant->status === 'suspended') {
                SecurityLoggingService::logLoginFailure(
                    $request->email,
                    '租戶帳號已被暫停',
                    $request
                );
                
                throw ValidationException::withMessages([
                    'email' => ['您的租戶帳號已被暫停，請聯繫系統管理員'],
                ]);
            }
            
            if ($tenant->status === 'inactive') {
                SecurityLoggingService::logLoginFailure(
                    $request->email,
                    '租戶帳號已被停用',
                    $request
                );
                
                throw ValidationException::withMessages([
                    'email' => ['您的租戶帳號已被停用，請聯繫系統管理員'],
                ]);
            }
            
            if (!$tenant->isActive()) {
                SecurityLoggingService::logLoginFailure(
                    $request->email,
                    '租戶訂閱已到期',
                    $request
                );
                
                throw ValidationException::withMessages([
                    'email' => ['您的租戶訂閱已到期，請聯繫系統管理員續約'],
                ]);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // 記錄登入成功
        SecurityLoggingService::logLoginSuccess($user, $request);
        ActivityLogger::login($user);

        // 準備用戶資料
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_system_admin' => $user->is_system_admin,
            'must_change_password' => $user->must_change_password,
            'tenant_id' => $user->tenant_id,
        ];

        // 如果是租戶用戶，加入租戶資訊
        if ($user->tenant_id && $user->tenant) {
            $userData['tenant'] = [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
                'status' => $user->tenant->status,
            ];
        }

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData
        ]);
    }

    public function logout(Request $request)
    {
        // 記錄登出
        SecurityLoggingService::logSecurityEvent(
            SecurityLoggingService::EVENT_TYPES['LOGOUT'],
            ['user_id' => $request->user()->id],
            'info',
            $request
        );
        ActivityLogger::logout();

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '已成功登出'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            // 處理頭像上傳
            if ($request->hasFile('avatar')) {
                // 刪除舊頭像
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                // 上傳新頭像
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $updateData['avatar'] = $avatarPath;
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => '個人資料更新成功',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新個人資料時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 驗證目前密碼
        if (!Hash::check($request->current_password, $user->getAttributes()['password'])) {
            return response()->json([
                'success' => false,
                'message' => '目前密碼不正確'
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => '密碼更新成功'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新密碼時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 強制修改密碼（首次登入）
     */
    public function forceChangePassword(Request $request)
    {
        $user = $request->user();

        // 驗證是否需要強制修改密碼
        if (!$user->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => '不需要修改密碼'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->new_password),
                'must_change_password' => false,
            ]);

            // 記錄操作
            ActivityLogger::custom(
                'updated',
                'auth',
                '首次登入修改密碼',
                ['user_id' => $user->id]
            );

            return response()->json([
                'success' => true,
                'message' => '密碼設定成功'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '設定密碼時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取訂閱信息
     */
    public function getSubscription(Request $request)
    {
        $user = $request->user();
        
        // 系統管理員沒有租戶訂閱信息
        if ($user->role === 'system_admin') {
            return response()->json([
                'success' => false,
                'message' => '系統管理員無訂閱信息'
            ], 400);
        }
        
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => '找不到租戶信息'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                ],
                'subscription' => [
                    'status' => $tenant->status,
                    'plan' => $tenant->plan,
                    'trial_ends_at' => $tenant->trial_ends_at ? $tenant->trial_ends_at->format('Y-m-d') : null,
                    'subscription_ends_at' => $tenant->subscription_ends_at ? $tenant->subscription_ends_at->format('Y-m-d') : null,
                ],
            ]
        ]);
    }

    /**
     * 獲取訂閱使用量
     */
    public function getSubscriptionUsage(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'system_admin') {
            return response()->json([
                'success' => false,
                'message' => '系統管理員無使用量信息'
            ], 400);
        }
        
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => '找不到租戶信息'
            ], 404);
        }

        // 計算本月使用量
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $monthlyReservations = $tenant->reservations()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
            
        $totalCustomers = $tenant->customers()->count();
        $totalServices = $tenant->services()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'usage' => [
                    'reservations' => $monthlyReservations,
                    'customers' => $totalCustomers,
                    'services' => $totalServices,
                ],
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d'),
                    'end' => $endOfMonth->format('Y-m-d'),
                ]
            ]
        ]);
    }

}
