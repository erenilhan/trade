<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MarketData extends Model
{
    protected $table = 'market_data';

    protected $fillable = [
        'symbol',
        'timeframe',
        'price',
        'ema20',
        'ema50',
        'macd',
        'rsi7',
        'rsi14',
        'atr3',
        'atr14',
        'volume',
        'volume_ratio',
        'funding_rate',
        'open_interest',
        'price_series',
        'indicators',
        'data_timestamp',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'ema20' => 'decimal:8',
        'ema50' => 'decimal:8',
        'macd' => 'decimal:8',
        'rsi7' => 'decimal:4',
        'rsi14' => 'decimal:4',
        'atr3' => 'decimal:8',
        'atr14' => 'decimal:8',
        'volume' => 'decimal:8',
        'volume_ratio' => 'decimal:4',
        'funding_rate' => 'decimal:10',
        'open_interest' => 'decimal:8',
        'price_series' => 'array',
        'indicators' => 'array',
        'data_timestamp' => 'datetime',
    ];

    /**
     * Get latest market data for a symbol and timeframe
     */
    public static function getLatest(string $symbol, string $timeframe = '3m'): ?self
    {
        return self::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->latest('data_timestamp')
            ->first();
    }

    /**
     * Get recent market data series
     */
    public static function getRecent(string $symbol, string $timeframe = '3m', int $limit = 10): Collection
    {
        return self::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->latest('data_timestamp')
            ->limit($limit)
            ->get();
    }

    /**
     * Store or update market data
     */
    public static function store(string $symbol, string $timeframe, array $data): self
    {
        $data['symbol'] = $symbol;
        $data['timeframe'] = $timeframe;
        $data['data_timestamp'] = $data['data_timestamp'] ?? now();

        return self::create($data);
    }
}
