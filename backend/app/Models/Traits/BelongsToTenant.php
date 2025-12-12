<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant Trait
 * 
 * 此 trait 用於實現多租戶資料隔離。
 * 所有使用此 trait 的 Model 都會自動：
 * 1. 在查詢時加上 tenant_id 過濾
 * 2. 在建立時自動設定 tenant_id
 */
trait BelongsToTenant
{
    /**
     * 啟動 trait
     */
    public static function bootBelongsToTenant()
    {
        // 只在有當前租戶時才套用範圍
        static::addGlobalScope(new TenantScope);

        // 建立模型時自動設定 tenant_id
        static::creating(function ($model) {
            if (!$model->tenant_id && app()->bound('currentTenant')) {
                $tenant = app('currentTenant');
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    /**
     * 獲取此模型所屬的租戶
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 設定當前租戶的範圍
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * 移除租戶範圍（用於管理員查詢所有資料）
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
