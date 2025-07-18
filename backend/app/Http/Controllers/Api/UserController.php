<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:admin,user'
        ]);

        $data = $request->only(['name', 'email', 'phone']);
        
        // 只有管理員可以修改角色
        if ($request->user()->role === 'admin' && $request->has('role')) {
            $data['role'] = $request->role;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => '使用者資料已更新',
            'data' => $user->fresh()
        ]);
    }

    // 更新使用者狀態（封鎖/解封）
    public function updateStatus(Request $request, User $user)
    {
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

        $user->update(['status' => $request->status]);

        $statusText = match($request->status) {
            'Active' => '啟用',
            'Inactive' => '停權',
            'Banned' => '停權'
        };

        return response()->json([
            'success' => true,
            'message' => "使用者已{$statusText}",
            'data' => $user->fresh()
        ]);
    }

    // 創建新使用者（管理員功能）
    public function store(Request $request)
    {
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

        return response()->json([
            'success' => true,
            'message' => '使用者創建成功',
            'data' => $user
        ], 201);
    }

    // 刪除使用者（管理員功能）
    public function destroy(Request $request, User $user)
    {
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

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => '使用者已刪除'
        ]);
    }
}
