<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'logo',
        'webhook_token',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'plan',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    protected $hidden = [];

    /**
     * 啟動模型事件
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // 自動生成 slug
            if (empty($tenant->slug)) {
                $baseSlug = Str::slug($tenant->name);
                $slug = $baseSlug;
                $counter = 1;
                
                // 確保 slug 唯一
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                
                $tenant->slug = $slug;
            }
            
            // 自動生成唯一的 webhook token (UUID v4)
            if (empty($tenant->webhook_token)) {
                do {
                    $token = Str::uuid()->toString();
                } while (static::where('webhook_token', $token)->exists());
                
                $tenant->webhook_token = $token;
            }
        });
    }

    // ===== 關聯 =====

    /**
     * 租戶的使用者
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * 租戶的客戶
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * 租戶的服務
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * 租戶的可用時段
     */
    public function availableTimes()
    {
        return $this->hasMany(AvailableTime::class);
    }

    /**
     * 租戶的預約
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * 租戶的設定
     */
    public function tenantSettings()
    {
        return $this->hasMany(Setting::class);
    }

    // ===== 範圍查詢 =====

    /**
     * 活躍的租戶
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 試用中的租戶
     */
    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    /**
     * 暫停的租戶
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    // ===== 輔助方法 =====

    /**
     * 檢查租戶是否可用（包含到期檢查）
     */
    public function isActive(): bool
    {
        // 狀態檢查
        if ($this->status === 'inactive' || $this->status === 'suspended') {
            return false;
        }

        // 試用期檢查
        if ($this->status === 'trial') {
            if (!$this->trial_ends_at) {
                return true; // 沒有設定到期日，預設可用
            }
            return $this->trial_ends_at->endOfDay()->isFuture();
        }

        // 活躍狀態檢查訂閱到期
        if ($this->status === 'active') {
            if (!$this->subscription_ends_at) {
                return true; // 沒有設定到期日，預設可用
            }
            return $this->subscription_ends_at->endOfDay()->isFuture();
        }

        return false;
    }

    /**
     * 檢查是否已到期
     */
    public function isExpired(): bool
    {
        return !$this->isActive();
    }

    /**
     * 取得到期日期（試用或訂閱）
     */
    public function getExpirationDateAttribute()
    {
        if ($this->status === 'trial' && $this->trial_ends_at) {
            return $this->trial_ends_at;
        }
        
        if ($this->status === 'active' && $this->subscription_ends_at) {
            return $this->subscription_ends_at;
        }
        
        return null;
    }

    /**
     * 取得到期狀態文字
     */
    public function getExpirationStatusAttribute(): string
    {
        if ($this->status === 'suspended') {
            return '已停用';
        }
        
        if ($this->status === 'inactive') {
            return '未啟用';
        }
        
        $expirationDate = $this->expiration_date;
        
        if (!$expirationDate) {
            return '永久有效';
        }
        
        if ($expirationDate->isPast()) {
            return '已到期';
        }
        
        $daysLeft = (int) now()->diffInDays($expirationDate, false);
        
        if ($daysLeft <= 7) {
            return "即將到期 ({$daysLeft} 天)";
        }
        
        return "有效至 " . $expirationDate->format('Y-m-d');
    }

    /**
     * 取得完整的 webhook URL
     */
    public function getFullWebhookUrlAttribute()
    {
        $baseUrl = config('app.url');
        return rtrim($baseUrl, '/') . '/api/webhook/' . $this->webhook_token;
    }

    /**
     * 取得 webhook URL 路徑
     */
    public function getWebhookUrlAttribute()
    {
        return '/api/webhook/' . $this->webhook_token;
    }

    /**
     * 取得設定值
     */
    public function getSetting($key, $default = null)
    {
        return $this->tenantSettings()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * 設定值
     */
    public function setSetting($key, $value, $type = 'string')
    {
        return $this->tenantSettings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
