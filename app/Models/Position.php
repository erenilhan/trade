<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'symbol',
        'side',
        'quantity',
        'entry_price',
        'current_price',
        'liquidation_price',
        'unrealized_pnl',
        'realized_pnl',
        'leverage',
        'notional_value',
        'notional_usd',
        'exit_plan',
        'confidence',
        'risk_usd',
        'sl_order_id',
        'tp_order_id',
        'entry_order_id',
        'wait_for_fill',
        'is_open',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'entry_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'liquidation_price' => 'decimal:2',
        'unrealized_pnl' => 'decimal:2',
        'realized_pnl' => 'decimal:2',
        'notional_value' => 'decimal:2',
        'notional_usd' => 'decimal:2',
        'exit_plan' => 'array',
        'confidence' => 'decimal:2',
        'risk_usd' => 'decimal:2',
        'wait_for_fill' => 'boolean',
        'is_open' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeClosed($query)
    {
        return $query->where('is_open', false);
    }

    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeActive($query)
    {
        return $query->where('is_open', true)->where('wait_for_fill', false);
    }

    public function getProfitPercentAttribute()
    {
        if ($this->entry_price == 0) return 0;

        $diff = $this->current_price - $this->entry_price;

        // Adjust for side
        if ($this->side === 'short') {
            $diff = -$diff;
        }

        return ($diff / $this->entry_price) * 100 * $this->leverage;
    }

    /**
     * Format position for AI prompt
     */
    public function toPromptFormat(): array
    {
        return [
            'symbol' => $this->symbol,
            'quantity' => (float) $this->quantity,
            'entry_price' => (float) $this->entry_price,
            'current_price' => (float) $this->current_price,
            'liquidation_price' => (float) $this->liquidation_price,
            'unrealized_pnl' => (float) $this->unrealized_pnl,
            'leverage' => $this->leverage,
            'exit_plan' => $this->exit_plan,
            'confidence' => (float) $this->confidence,
            'risk_usd' => (float) $this->risk_usd,
            'sl_oid' => $this->sl_order_id ?? -1,
            'tp_oid' => $this->tp_order_id ?? -1,
            'entry_oid' => $this->entry_order_id ?? -1,
            'wait_for_fill' => $this->wait_for_fill,
            'notional_usd' => (float) $this->notional_value,
        ];
    }
}
