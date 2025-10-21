<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'order_id',
        'symbol',
        'side',
        'type',
        'amount',
        'price',
        'cost',
        'leverage',
        'stop_loss',
        'take_profit',
        'status',
        'error_message',
        'response_data',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'take_profit' => 'decimal:2',
        'response_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'filled');
    }
}
