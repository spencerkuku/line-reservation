<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope
 * 
 * 全局範圍，自動為所有查詢添加 tenant_id 過濾條件。
 * 系統管理員不受此限制。
 */
class TenantScope implements Scope
{
    /**
     * 應用範圍到給定的 Eloquent 查詢建構器
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 檢查是否有當前租戶
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            
            // 如果有當前租戶，過濾資料
            if ($tenant) {
                $builder->where($model->getTable() . '.tenant_id', $tenant->id);
            }
        }
        
        // 如果是系統管理員，不套用租戶過濾
        // 系統管理員的判斷在中間件中處理，這裡不會設定 currentTenant
    }
}
