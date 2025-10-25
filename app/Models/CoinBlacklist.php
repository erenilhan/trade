<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinBlacklist extends Model
{
    protected $table = 'coin_blacklist';

    protected $fillable = [
        'symbol',
        'status',
        'min_confidence',
        'reason',
        'performance_stats',
        'auto_added',
        'expires_at',
    ];

    protected $casts = [
        'performance_stats' => 'array',
        'min_confidence' => 'decimal:2',
        'auto_added' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if coin is blacklisted
     */
    public static function isBlacklisted(string $symbol): bool
    {
        $entry = self::where('symbol', $symbol)->first();

        if (!$entry) {
            return false;
        }

        // Check if temporary blacklist expired
        if ($entry->expires_at && $entry->expires_at->isPast()) {
            $entry->update(['status' => 'active']);
            return false;
        }

        return $entry->status === 'blacklisted';
    }

    /**
     * Get minimum confidence required for a coin
     */
    public static function getMinConfidence(string $symbol): float
    {
        $entry = self::where('symbol', $symbol)->first();

        if (!$entry) {
            return 0.70; // Default confidence
        }

        // Check if temporary restriction expired
        if ($entry->expires_at && $entry->expires_at->isPast()) {
            return 0.70;
        }

        return (float) $entry->min_confidence;
    }

    /**
     * Check if coin requires high confidence
     */
    public static function requiresHighConfidence(string $symbol): bool
    {
        $entry = self::where('symbol', $symbol)->first();

        if (!$entry) {
            return false;
        }

        // Check if expired
        if ($entry->expires_at && $entry->expires_at->isPast()) {
            return false;
        }

        return $entry->status === 'high_confidence_only';
    }

    /**
     * Auto-analyze coin performance and update blacklist
     */
    public static function analyzeAndUpdate(string $symbol): void
    {
        $positions = Position::where('symbol', $symbol)
            ->where('is_open', false)
            ->get();

        // Calculate stats even for coins with < 3 trades
        $wins = $positions->where('realized_pnl', '>', 0)->count();
        $losses = $positions->where('realized_pnl', '<', 0)->count();
        $winRate = $positions->count() > 0 ? ($wins / $positions->count()) * 100 : 0;
        $totalPnl = $positions->sum('realized_pnl');
        $avgLoss = $losses > 0 ? abs($positions->where('realized_pnl', '<', 0)->avg('realized_pnl')) : 0;

        $stats = [
            'win_rate' => round($winRate, 2),
            'total_trades' => $positions->count(),
            'wins' => $wins,
            'losses' => $losses,
            'total_pnl' => round($totalPnl, 2),
            'avg_loss' => round($avgLoss, 2),
            'updated_at' => now()->toDateTimeString(),
        ];

        // Decision logic
        $status = 'active';
        $minConfidence = 0.70;
        $reason = null;

        if ($positions->count() < 3) {
            // Not enough data to make restrictions
            $status = 'active';
            $reason = "Insufficient data ({$positions->count()} trades) - normal trading";
        }
        // BLACKLIST if: Win rate < 30% AND 5+ trades AND negative P&L
        elseif ($winRate < 30 && $positions->count() >= 5 && $totalPnl < 0) {
            $status = 'blacklisted';
            $reason = "Very poor performance: {$winRate}% WR, \${$totalPnl} P&L ({$positions->count()} trades)";
        }
        // HIGH CONFIDENCE ONLY if: Win rate < 40% AND 3+ trades
        elseif ($winRate < 40 && $positions->count() >= 3) {
            $status = 'high_confidence_only';
            $minConfidence = 0.80;
            $reason = "Low win rate: {$winRate}% WR ({$positions->count()} trades) - requires 80%+ confidence";
        }
        // ACTIVE - Good performance
        else {
            $status = 'active';
            $pnlSign = $totalPnl >= 0 ? '+' : '';
            $reason = "Good performance: {$winRate}% WR, {$pnlSign}\${$totalPnl} P&L ({$positions->count()} trades)";
        }

        // Update or create entry
        self::updateOrCreate(
            ['symbol' => $symbol],
            [
                'status' => $status,
                'min_confidence' => $minConfidence,
                'reason' => $reason,
                'performance_stats' => $stats,
                'auto_added' => true,
            ]
        );
    }

    /**
     * Analyze all coins
     */
    public static function analyzeAllCoins(): array
    {
        $results = [];
        $coins = Position::select('symbol')->distinct()->pluck('symbol');

        foreach ($coins as $symbol) {
            self::analyzeAndUpdate($symbol);
            $entry = self::where('symbol', $symbol)->first();
            if ($entry && $entry->status !== 'active') {
                $results[] = $entry;
            }
        }

        return $results;
    }
}
