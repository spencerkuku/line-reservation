<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineMessageLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'line_user_id',
        'message_type',
        'message_content',
        'bot_response',
        'direction'
    ];

    protected $casts = [
        'message_content' => 'array',
        'bot_response' => 'array'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'line_user_id', 'line_user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'line_user_id', 'line_user_id');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }
}
