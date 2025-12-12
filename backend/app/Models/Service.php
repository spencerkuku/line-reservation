<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'duration',
        'price',
        'image_url',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    protected $appends = ['full_image_url'];

    public function getFullImageUrlAttribute()
    {
        if ($this->image_url) {
            // 如果已經是完整 URL，直接返回
            if (str_starts_with($this->image_url, 'http')) {
                return $this->image_url;
            }
            
            // 確保路徑正確格式
            $path = ltrim($this->image_url, '/');
            
            // 如果路徑不包含 storage/，則添加
            if (!str_starts_with($path, 'storage/')) {
                $path = 'storage/' . $path;
            }
            
            // 使用環境變數決定基礎 URL，優先使用 DOMAIN_URL，其次使用 APP_URL
            $baseUrl = env('DOMAIN_URL', config('app.url'));
            
            // 如果是 ngrok 域名，確保使用 HTTPS
            if (str_contains($baseUrl, 'ngrok')) {
                $baseUrl = str_replace('http://', 'https://', $baseUrl);
            }
            
            return rtrim($baseUrl, '/') . '/' . $path;
        }
        return null;
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function activeReservations()
    {
        return $this->hasMany(Reservation::class)
                    ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
