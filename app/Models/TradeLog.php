<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeLog extends Model
{
    protected $fillable = [
        'action',
        'success',
        'message',
        'account_state',
        'decision_data',
        'result_data',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'account_state' => 'array',
        'decision_data' => 'array',
        'result_data' => 'array',
        'executed_at' => 'datetime',
    ];

    public function scopeRecent($query, $limit = 100)
    {
        return $query->orderBy('executed_at', 'desc')->limit($limit);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }
}
