<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::active()->get();
        
        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:15|max:480',
            'price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,png|max:5012'
        ]);

        $data = $request->only(['name', 'description', 'duration', 'price']);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('services', 'public');
            $data['image_url'] = $imagePath; // 只存儲相對路徑，不包含 /storage/ 前綴
        }

        $service = Service::create($data);

        return response()->json([
            'success' => true,
            'message' => '服務新增成功',
            'data' => $service
        ], 201);
    }

    public function destroy(Service $service)
    {
        // 檢查是否有相關預約
        $activeReservationsCount = $service->activeReservations()->count();
        
        if ($activeReservationsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "無法刪除：此服務還有 {$activeReservationsCount} 個預約記錄"
            ], 422);
        }

        // 刪除圖片
        if ($service->image_url) {
            $imagePath = $service->image_url;
            // 如果已經包含 /storage/ 前綴，則移除它
            if (str_starts_with($imagePath, '/storage/')) {
                $imagePath = substr($imagePath, 9);
            }
            Storage::disk('public')->delete($imagePath);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => '服務已刪除'
        ]);
    }

    public function reservations(Service $service)
    {
        $count = $service->activeReservations()->count();
        
        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:15|max:480',
            'price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5012'
        ]);

        $data = $request->only(['name', 'description', 'duration', 'price']);

        if ($request->hasFile('image')) {
            // 刪除舊圖片
            if ($service->image_url) {
                $oldImagePath = $service->image_url;
                // 如果已經包含 /storage/ 前綴，則移除它
                if (str_starts_with($oldImagePath, '/storage/')) {
                    $oldImagePath = substr($oldImagePath, 9);
                }
                Storage::disk('public')->delete($oldImagePath);
            }
            
            // 上傳新圖片
            $imagePath = $request->file('image')->store('services', 'public');
            $data['image_url'] = $imagePath; // 只存儲相對路徑
        }

        $service->update($data);

        return response()->json([
            'success' => true,
            'message' => '服務更新成功',
            'data' => $service
        ]);
    }
}
