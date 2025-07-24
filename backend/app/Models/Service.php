<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
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
            
            // 使用 asset() 而不是 url() 來確保產生正確的 URL
            $baseUrl = config('app.url');
            
            // 如果是本地開發環境且使用 ngrok，確保 HTTPS
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
