<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LineMessageLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LineMessageLogController extends Controller
{
    /**
     * 取得 LINE 訊息日誌列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = LineMessageLog::with(['customer', 'tenant'])
            ->orderBy('created_at', 'desc');

        // 篩選條件
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('line_user_id')) {
            $query->where('line_user_id', $request->line_user_id);
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->message_type);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('line_user_id', 'like', "%{$search}%")
                  ->orWhere('message_type', 'like', "%{$search}%")
                  ->orWhereRaw('JSON_EXTRACT(message_content, "$.text") LIKE ?', ["%{$search}%"]);
            });
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * 取得單一 LINE 訊息日誌詳情
     */
    public function show(LineMessageLog $log): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $log->load(['customer', 'tenant'])
        ]);
    }

    /**
     * 取得 LINE 訊息統計
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30));
        $dateTo = $request->get('date_to', now());

        $query = LineMessageLog::whereBetween('line_message_logs.created_at', [$dateFrom, $dateTo]);

        // 如果不是系統管理員，只看自己租戶的
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isSystemAdmin()) {
            $query->where('line_message_logs.tenant_id', $user->tenant_id);
        }

        $stats = [
            'total_messages' => (clone $query)->count(),
            
            'by_direction' => (clone $query)
                ->selectRaw('direction, count(*) as count')
                ->groupBy('direction')
                ->get(),
            
            'by_type' => (clone $query)
                ->selectRaw('message_type, count(*) as count')
                ->groupBy('message_type')
                ->orderBy('count', 'desc')
                ->get(),
            
            'by_tenant' => $user->isSystemAdmin() 
                ? LineMessageLog::whereBetween('line_message_logs.created_at', [$dateFrom, $dateTo])
                    ->join('tenants', 'line_message_logs.tenant_id', '=', 'tenants.id')
                    ->selectRaw('tenants.id, tenants.name, count(line_message_logs.id) as count')
                    ->groupBy('tenants.id', 'tenants.name')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                : null,
            
            'top_users' => (clone $query)
                ->selectRaw('line_user_id, count(*) as count')
                ->groupBy('line_user_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            
            'recent_messages' => (clone $query)
                ->with(['user'])
                ->orderBy('line_message_logs.created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * 取得每日訊息趨勢
     */
    public function trends(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $dateFrom = now()->subDays($days)->startOfDay();

        $query = LineMessageLog::whereBetween('line_message_logs.created_at', [$dateFrom, now()]);

        // 如果不是系統管理員，只看自己租戶的
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isSystemAdmin()) {
            $query->where('line_message_logs.tenant_id', $user->tenant_id);
        }

        $trends = $query
            ->selectRaw('DATE(line_message_logs.created_at) as date, direction, count(*) as count')
            ->groupBy('date', 'direction')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function($items, $date) {
                return [
                    'date' => $date,
                    'incoming' => $items->where('direction', 'incoming')->sum('count'),
                    'outgoing' => $items->where('direction', 'outgoing')->sum('count'),
                    'total' => $items->sum('count'),
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * 取得訊息類型列表
     */
    public function types(): JsonResponse
    {
        $types = LineMessageLog::select('message_type')
            ->distinct()
            ->orderBy('message_type')
            ->pluck('message_type');

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * 取得所有租戶列表（僅系統管理員）
     */
    public function tenants(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => '權限不足'
            ], 403);
        }

        $tenants = \App\Models\Tenant::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tenants
        ]);
    }
}
