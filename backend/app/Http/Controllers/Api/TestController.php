<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LineBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    public function testServiceSelection(Request $request)
    {
        try {
            Log::info('Test service selection called');
            
            $lineBotService = new LineBotService();
            
            // 測試獲取服務的可用時段
            $serviceId = $request->input('service_id', 1); // 預設測試服務ID 1
            
            Log::info('Testing with service_id: ' . $serviceId);
            
            $result = $lineBotService->getAvailableTimeSlots($serviceId);
            
            return response()->json([
                'success' => true,
                'result' => $result,
                'service_id_tested' => $serviceId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Test service selection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
    
    public function testBusinessHours()
    {
        try {
            $lineBotService = new LineBotService();
            
            $hours = $lineBotService->getBusinessHours();
            
            return response()->json([
                'success' => true,
                'business_hours' => $hours
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
