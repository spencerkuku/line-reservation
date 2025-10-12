<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 獲取所有使用者（管理介面）
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'status', 'phone', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    // 更新使用者資料
    public function update(Request $request, User $user)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'role' => 'sometimes|in:admin,user'
            ]);

            $oldValues = $user->getOriginal();
            $data = $request->only(['name', 'email', 'phone']);
            
            // 只有管理員可以修改角色
            if ($request->user()->role === 'admin' && $request->has('role')) {
                $data['role'] = $request->role;
            }

            $user->update($data);

            // 記錄操作
            ActivityLogger::updated($user, 'users', $oldValues);

            return response()->json([
                'success' => true,
                'message' => '使用者資料已更新',
                'data' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            ActivityLogger::failed('update', 'users', "更新使用者失敗: {$user->email}", $e);
            throw $e;
        }
    }

    // 更新使用者狀態（封鎖/解封）
    public function updateStatus(Request $request, User $user)
    {
        try {
            $request->validate([
                'status' => 'required|in:Active,Inactive,Banned'
            ]);

            // 不能修改自己的狀態
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '不能修改自己的狀態'
                ], 422);
            }

            $oldStatus = $user->status;
            $user->update(['status' => $request->status]);

            $statusText = match($request->status) {
                'Active' => '啟用',
                'Inactive' => '停權',
                'Banned' => '停權'
            };

            // 記錄操作
            ActivityLogger::custom(
                'status_changed',
                'users',
                "使用者狀態變更: {$user->email} ({$oldStatus} -> {$request->status})",
                ['old_status' => $oldStatus, 'new_status' => $request->status]
            );

            return response()->json([
                'success' => true,
                'message' => "使用者已{$statusText}",
                'data' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            ActivityLogger::failed('update_status', 'users', "更新使用者狀態失敗: {$user->email}", $e);
            throw $e;
        }
    }

    // 創建新使用者（管理員功能）
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:20',
                'role' => 'sometimes|in:admin,manager,user'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role' => $request->role ?? 'user',
                'status' => 'Active'
            ]);

            // 記錄操作
            ActivityLogger::created($user, 'users');

            return response()->json([
                'success' => true,
                'message' => '使用者創建成功',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            ActivityLogger::failed('create', 'users', '建立使用者失敗', $e);
            throw $e;
        }
    }

    // 刪除使用者（管理員功能）
    public function destroy(Request $request, User $user)
    {
        try {
            // 不能刪除自己
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '不能刪除自己的帳號'
                ], 422);
            }

            // 不能刪除系統中最後一個管理員
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => '不能刪除系統中最後一個管理員'
                    ], 422);
                }
            }

            // 記錄操作（在刪除前）
            ActivityLogger::deleted($user, 'users');

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => '使用者已刪除'
            ]);
        } catch (\Exception $e) {
            ActivityLogger::failed('delete', 'users', "刪除使用者失敗: {$user->email}", $e);
            throw $e;
        }
    }
}
