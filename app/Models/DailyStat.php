<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DailyStat extends Model
{
    protected $fillable = [
        'date',
        'starting_balance',
        'current_balance',
        'daily_pnl',
        'daily_pnl_percent',
        'max_drawdown_hit',
        'cooldown_until',
        'trades_count',
        'wins_count',
        'losses_count',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'starting_balance' => 'decimal:8',
        'current_balance' => 'decimal:8',
        'daily_pnl' => 'decimal:8',
        'daily_pnl_percent' => 'decimal:4',
        'max_drawdown_hit' => 'boolean',
        'cooldown_until' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get or create today's stats (UTC based)
     */
    public static function today(): self
    {
        $date = now()->utc()->toDateString();

        return self::firstOrCreate(
            ['date' => $date],
            [
                'starting_balance' => 0,
                'current_balance' => 0,
                'daily_pnl' => 0,
                'daily_pnl_percent' => 0,
            ]
        );
    }

    /**
     * Check if we're in cooldown period
     */
    public static function isInCooldown(): bool
    {
        // Check if manual cooldown override is enabled
        if (BotSetting::get('manual_cooldown_override', false)) {
            return false;
        }

        $today = self::today();

        if (!$today->max_drawdown_hit || !$today->cooldown_until) {
            return false;
        }

        return now()->utc()->lt($today->cooldown_until);
    }

    /**
     * Update daily stats after a trade
     */
    public static function updateAfterTrade(float $pnl, bool $isWin): void
    {
        $today = self::today();

        $today->increment('trades_count');

        if ($isWin) {
            $today->increment('wins_count');
        } else {
            $today->increment('losses_count');
        }

        $today->daily_pnl += $pnl;
        $today->save();

        // Recalculate percentage
        if ($today->starting_balance > 0) {
            $today->daily_pnl_percent = ($today->daily_pnl / $today->starting_balance) * 100;
            $today->save();
        }
    }

    /**
     * Check and enforce max drawdown limit
     */
    public static function checkMaxDrawdown(): ?string
    {
        $config = config('trading.daily_max_drawdown');

        if (!$config['enabled']) {
            return null;
        }

        $today = self::today();

        // Check if already in cooldown
        if (self::isInCooldown()) {
            $remainingHours = now()->utc()->diffInHours($today->cooldown_until);
            return "Max drawdown cooldown active. Trading disabled for {$remainingHours}h";
        }

        // Check if max drawdown exceeded
        if (abs($today->daily_pnl_percent) >= $config['max_drawdown_percent']) {
            $today->update([
                'max_drawdown_hit' => true,
                'cooldown_until' => now()->utc()->addHours($config['cooldown_hours']),
            ]);

            return "Max drawdown limit ({$config['max_drawdown_percent']}%) exceeded. Trading paused for {$config['cooldown_hours']}h";
        }

        return null;
    }

    /**
     * Initialize starting balance for the day
     */
    public static function initializeDay(float $currentBalance): void
    {
        $today = self::today();

        if ($today->starting_balance == 0) {
            $today->update([
                'starting_balance' => $currentBalance,
                'current_balance' => $currentBalance,
            ]);
        } else {
            $today->update(['current_balance' => $currentBalance]);
        }
    }
}
