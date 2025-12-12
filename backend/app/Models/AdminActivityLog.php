<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActivityLog extends Model
{
    use BelongsToTenant;
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_name',
        'user_email',
        'action',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'subject_data',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'method',
        'url',
        'status',
        'error_message',
    ];

    protected $casts = [
        'subject_data' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 關聯到操作者
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 關聯到操作對象
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 取得變更摘要
     */
    public function getChangesSummary(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * 取得操作類型文字
     */
    public function getActionLabel(): string
    {
        return match($this->action) {
            'created' => '建立',
            'updated' => '更新',
            'deleted' => '刪除',
            'login' => '登入',
            'logout' => '登出',
            'bulk_delete' => '批次刪除',
            'bulk_update' => '批次更新',
            'export' => '匯出',
            'import' => '匯入',
            default => $this->action,
        };
    }

    /**
     * 取得模組文字
     */
    public function getModuleLabel(): string
    {
        return match($this->module) {
            'auth' => '認證',
            'users' => '用戶管理',
            'services' => '服務管理',
            'available_times' => '時段管理',
            'reservations' => '預約管理',
            'customers' => '客戶管理',
            'settings' => '系統設定',
            'line' => 'LINE 整合',
            'reports' => '報表',
            default => $this->module,
        };
    }
}
