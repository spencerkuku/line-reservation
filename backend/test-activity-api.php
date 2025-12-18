<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 獲取系統管理員
$user = \App\Models\User::where('role', 'system_admin')->first();

if (!$user) {
    echo "No system admin found\n";
    exit(1);
}

echo "User: {$user->username} (role: {$user->role})\n";
echo "User is system admin: " . ($user->isSystemAdmin() ? 'true' : 'false') . "\n\n";

// 測試 without scope
$withoutScope = \App\Models\AdminActivityLog::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
    ->count();

echo "Logs without TenantScope: {$withoutScope}\n";

// 測試 with scope
$withScope = \App\Models\AdminActivityLog::count();

echo "Logs with TenantScope: {$withScope}\n\n";

// 模擬請求
\Illuminate\Support\Facades\Auth::login($user);

$request = \Illuminate\Http\Request::create('/api/admin/activity-logs', 'GET', [
    'page' => 1,
    'per_page' => 15
]);

$app->instance('request', $request);

$controller = new \App\Http\Controllers\AdminActivityLogController();

try {
    $response = $controller->index($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    echo "API Response:\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Total: " . $data['data']['total'] . "\n";
    echo "Current page: " . $data['data']['current_page'] . "\n";
    echo "Per page: " . $data['data']['per_page'] . "\n";
    echo "Data count: " . count($data['data']['data']) . "\n\n";
    
    if (count($data['data']['data']) > 0) {
        echo "First 3 logs:\n";
        foreach (array_slice($data['data']['data'], 0, 3) as $log) {
            echo "  - ID: {$log['id']}, Module: {$log['module']}, Action: {$log['action']}, Tenant ID: " . ($log['tenant_id'] ?? 'NULL') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
