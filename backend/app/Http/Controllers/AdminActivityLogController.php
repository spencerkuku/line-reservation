<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminActivityLogController extends Controller
{
    /**
     * 取得活動日誌列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        // 篩選條件
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * 取得單一活動日誌詳情
     */
    public function show(AdminActivityLog $log): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $log->load('user')
        ]);
    }

    /**
     * 取得活動統計
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30));
        $dateTo = $request->get('date_to', now());

        $stats = [
            'total_activities' => AdminActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            
            'by_module' => AdminActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('module, count(*) as count')
                ->groupBy('module')
                ->orderBy('count', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'module' => $item->module,
                        'count' => $item->count,
                        'label' => (new AdminActivityLog(['module' => $item->module]))->getModuleLabel(),
                    ];
                }),
            
            'by_action' => AdminActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('action, count(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'action' => $item->action,
                        'count' => $item->count,
                        'label' => (new AdminActivityLog(['action' => $item->action]))->getActionLabel(),
                    ];
                }),
            
            'by_user' => AdminActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('user_id, user_name, user_email, count(*) as count')
                ->groupBy('user_id', 'user_name', 'user_email')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            
            'failed_operations' => AdminActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', 'failed')
                ->count(),
            
            'recent_activities' => AdminActivityLog::with('user')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * 取得每日活動趨勢
     */
    public function trends(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $dateFrom = now()->subDays($days)->startOfDay();

        $trends = AdminActivityLog::whereBetween('created_at', [$dateFrom, now()])
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * 取得可用的模組列表
     */
    public function modules(): JsonResponse
    {
        $modules = AdminActivityLog::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->map(function($module) {
                return [
                    'value' => $module,
                    'label' => (new AdminActivityLog(['module' => $module]))->getModuleLabel(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * 取得可用的操作列表
     */
    public function actions(): JsonResponse
    {
        $actions = AdminActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(function($action) {
                return [
                    'value' => $action,
                    'label' => (new AdminActivityLog(['action' => $action]))->getActionLabel(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $actions
        ]);
    }
}
