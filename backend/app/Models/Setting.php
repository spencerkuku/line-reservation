<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['key', 'value', 'type', 'tenant_id'];

    // 需要加密的設定鍵
    protected $encryptedKeys = [
        'line_channel_access_token',
        'line_channel_secret'
    ];

    protected $casts = [
        'value' => 'string'
    ];

    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        $value = $setting->value;
        
        // 如果是加密的鍵，先解密
        $instance = new static();
        if (in_array($key, $instance->encryptedKeys)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // 如果解密失敗，可能是舊的未加密數據，直接返回原值
                $value = $setting->value;
            }
        }

        return match($setting->type) {
            'json' => json_decode($value, true),
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => $value
        };
    }

    public static function set($key, $value, $type = 'string')
    {
        if ($type === 'json') {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = (string) (bool) $value;
        }

        // 如果是加密的鍵，先加密值
        $instance = new static();
        if (in_array($key, $instance->encryptedKeys)) {
            $value = Crypt::encryptString($value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
