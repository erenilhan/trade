<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Models\Position;
use App\Services\BinanceService;
use App\Services\MarketDataService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorPositions extends Command
{
    protected $signature = 'positions:monitor';
    protected $description = 'Monitor open positions and auto-close based on targets/stops';

    public function __construct(
        private readonly BinanceService $binance,
        private readonly MarketDataService $marketData
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $openPositions = Position::where('is_open', true)->get();

        if ($openPositions->isEmpty()) {
            $this->info('📭 No open positions to monitor');
            return self::SUCCESS;
        }

        $this->info("🔍 Monitoring {$openPositions->count()} open positions...");

        foreach ($openPositions as $position) {
            try {
                $this->checkPosition($position);
            } catch (Exception $e) {
                $this->error("  ❌ {$position->symbol}: {$e->getMessage()}");
                Log::error("Position monitoring failed for {$position->id}", [
                    'error' => $e->getMessage(),
                    'position' => $position->toArray()
                ]);
            }
        }

        $this->info('✅ Position monitoring complete');
        return self::SUCCESS;
    }

    private function checkPosition(Position $position): void
    {
        $symbol = $position->symbol;
        $currentPrice = $position->current_price;
        $entryPrice = $position->entry_price;
        $exitPlan = $position->exit_plan ?? [];

        $profitTarget = $exitPlan['profit_target'] ?? null;
        $stopLoss = $exitPlan['stop_loss'] ?? null;
        $liqPrice = $position->liquidation_price;

        $this->line("  📊 {$symbol}: \${$currentPrice} ({$position->side})");

        // 1. CHECK TAKE PROFIT (handle SHORT vs LONG)
        $takeProfitHit = false;
        if ($profitTarget) {
            if ($position->side === 'short') {
                $takeProfitHit = $currentPrice <= $profitTarget; // SHORT: profit when price goes DOWN
            } else {
                $takeProfitHit = $currentPrice >= $profitTarget; // LONG: profit when price goes UP
            }
        }

        if ($takeProfitHit) {
            $this->info("    🎯 Take Profit hit! Target: \${$profitTarget}");
            $this->closePosition($position, 'take_profit', $currentPrice);
            return;
        }

        // 2. CHECK STOP LOSS (handle SHORT vs LONG)
        $stopLossHit = false;
        if ($stopLoss) {
            if ($position->side === 'short') {
                $stopLossHit = $currentPrice >= $stopLoss; // SHORT: stop when price goes UP
            } else {
                $stopLossHit = $currentPrice <= $stopLoss; // LONG: stop when price goes DOWN
            }
        }

        if ($stopLossHit) {
            $this->warn("    🛑 Stop Loss hit! Stop: \${$stopLoss}");
            $this->closePosition($position, 'stop_loss', $currentPrice);
            return;
        }

        // 3. CHECK LIQUIDATION DANGER (handle SHORT vs LONG)
        if ($liqPrice) {
            if ($position->side === 'short') {
                // SHORT: liquidation when price goes UP above liqPrice
                $distanceToLiq = (($liqPrice - $currentPrice) / $currentPrice) * 100;
            } else {
                // LONG: liquidation when price goes DOWN below liqPrice
                $distanceToLiq = (($currentPrice - $liqPrice) / $currentPrice) * 100;
            }

            // Emergency close only if VERY close to liquidation (3%)
            if ($distanceToLiq < 3) {
                $this->error("    🚨 CRITICAL LIQUIDATION DANGER! Only {$distanceToLiq}% away from liq!");
                $this->closePosition($position, 'liquidation_protection', $currentPrice);
                return;
            }

            // Warning at 10%
            if ($distanceToLiq < 10) {
                $this->error("    ⚠️ Warning: {$distanceToLiq}% from liquidation - stop loss should have triggered!");
            }
        }

        // 4. CHECK TREND INVALIDATION (early warning system)
        try {
            $marketData = $this->marketData->collectMarketData($symbol, '3m');
            $data4h = $this->marketData->collectMarketData($symbol, '4h');

            $invalidationReasons = [];

            // Check if price broke below EMA20
            if ($currentPrice < $marketData['ema20']) {
                $invalidationReasons[] = "Price < EMA20 ({$marketData['ema20']})";
            }

            // Check if MACD turned negative
            if (($marketData['macd'] ?? 0) < 0) {
                $invalidationReasons[] = "MACD turned negative ({$marketData['macd']})";
            }

            // Check if 4H trend weakened (ADX < 20)
            if (($data4h['adx'] ?? 0) < 20) {
                $invalidationReasons[] = "4H ADX weak ({$data4h['adx']} < 20)";
            }

            // Check if 4H trend reversed (EMA20 < EMA50)
            if ($data4h['ema20'] < $data4h['ema50']) {
                $invalidationReasons[] = "4H trend reversed (EMA20 < EMA50)";
            }

            // If 2+ invalidation signals AND position is NOT profitable, close early
            if (count($invalidationReasons) >= 2 && $pnlPercent < 2) {
                $this->warn("    ⚠️ TREND INVALIDATION: " . implode(', ', $invalidationReasons));
                $this->warn("    📉 Closing position early (PNL: {$pnlPercent}%)");
                $this->closePosition($position, 'trend_invalidation', $currentPrice);
                return;
            }

            // If 3+ invalidation signals, close regardless of PNL
            if (count($invalidationReasons) >= 3) {
                $this->error("    🚨 STRONG TREND INVALIDATION: " . implode(', ', $invalidationReasons));
                $this->closePosition($position, 'trend_invalidation', $currentPrice);
                return;
            }

            // Log warnings if any invalidation detected
            if (count($invalidationReasons) > 0) {
                $this->warn("    ⚠️ Warning: " . implode(', ', $invalidationReasons));
            }

        } catch (\Exception $e) {
            $this->warn("    ⚠️ Could not check trend: " . $e->getMessage());
        }

        // 5. MULTI-LEVEL TRAILING STOP (Protect profits as position grows)
        // Calculate P&L with proper SHORT handling
        $priceDiff = $currentPrice - $entryPrice;
        if ($position->side === 'short') {
            $priceDiff = -$priceDiff; // SHORT: price down = profit
        }
        $pnlPercent = ($priceDiff / $entryPrice) * 100 * $position->leverage;

        $originalStopLoss = $exitPlan['stop_loss'] ?? null;
        $trailingUpdated = false;

        // Get trailing stop settings from BotSetting (dynamic!)
        $l4Trigger = BotSetting::get('trailing_stop_l4_trigger', 12);
        $l4Target = BotSetting::get('trailing_stop_l4_target', 6);
        $l3Trigger = BotSetting::get('trailing_stop_l3_trigger', 8);
        $l3Target = BotSetting::get('trailing_stop_l3_target', 3);
        $l2Trigger = BotSetting::get('trailing_stop_l2_trigger', 5);
        $l2Target = BotSetting::get('trailing_stop_l2_target', 0);
        $l1Trigger = BotSetting::get('trailing_stop_l1_trigger', 3);
        $l1Target = BotSetting::get('trailing_stop_l1_target', -1);

        if ($pnlPercent >= $l4Trigger) {
            // Level 4: Lock in big profit
            if ($position->side === 'short') {
                $newStop = $entryPrice * (1 - ($l4Target / 100)); // SHORT: stop below entry
            } else {
                $newStop = $entryPrice * (1 + ($l4Target / 100)); // LONG: stop above entry
            }

            $shouldUpdate = !$stopLoss ||
                ($position->side === 'short' ? $newStop < $stopLoss : $newStop > $stopLoss);

            if ($shouldUpdate) {
                $exitPlan['stop_loss'] = $newStop;
                $exitPlan['trailing_level'] = 4;
                $exitPlan['max_profit_reached'] = $pnlPercent;
                $position->update(['exit_plan' => $exitPlan]);
                $this->info("    🔒🔒🔒 Trailing Stop L4: Stop moved to +{$l4Target}% (\${$newStop})");
                $trailingUpdated = true;
            }
        } elseif ($pnlPercent >= $l3Trigger) {
            // Level 3: Lock in profit
            if ($position->side === 'short') {
                $newStop = $entryPrice * (1 - ($l3Target / 100));
            } else {
                $newStop = $entryPrice * (1 + ($l3Target / 100));
            }

            $shouldUpdate = !$stopLoss ||
                ($position->side === 'short' ? $newStop < $stopLoss : $newStop > $stopLoss);

            if ($shouldUpdate) {
                $exitPlan['stop_loss'] = $newStop;
                $exitPlan['trailing_level'] = 3;
                $exitPlan['max_profit_reached'] = $pnlPercent;
                $position->update(['exit_plan' => $exitPlan]);
                $this->info("    🔒🔒 Trailing Stop L3: Stop moved to +{$l3Target}% (\${$newStop})");
                $trailingUpdated = true;
            }
        } elseif ($pnlPercent >= $l2Trigger) {
            // Level 2: Move to breakeven or custom target
            if ($position->side === 'short') {
                $newStop = $entryPrice * (1 - ($l2Target / 100));
            } else {
                $newStop = $entryPrice * (1 + ($l2Target / 100));
            }

            $shouldUpdate = !$stopLoss ||
                ($position->side === 'short' ? $newStop < $stopLoss : $newStop > $stopLoss);

            if ($shouldUpdate) {
                $exitPlan['stop_loss'] = $newStop;
                $exitPlan['trailing_level'] = 2;
                $exitPlan['max_profit_reached'] = $pnlPercent;
                $position->update(['exit_plan' => $exitPlan]);
                $targetLabel = $l2Target == 0 ? "breakeven" : "{$l2Target}%";
                $this->info("    🔒 Trailing Stop L2: Stop moved to {$targetLabel} (\${$newStop})");
                $trailingUpdated = true;
            }
        } elseif ($pnlPercent >= $l1Trigger) {
            // Level 1: Reduce risk
            if ($position->side === 'short') {
                $newStop = $entryPrice * (1 - ($l1Target / 100));
            } else {
                $newStop = $entryPrice * (1 + ($l1Target / 100));
            }

            $shouldUpdate = !$stopLoss ||
                ($position->side === 'short' ? $newStop < $stopLoss : $newStop > $stopLoss);

            if ($shouldUpdate) {
                $exitPlan['stop_loss'] = $newStop;
                $exitPlan['trailing_level'] = 1;
                $exitPlan['max_profit_reached'] = $pnlPercent;
                $position->update(['exit_plan' => $exitPlan]);
                $this->info("    🔒 Trailing Stop L1: Stop moved to {$l1Target}% (\${$newStop})");
                $trailingUpdated = true;
            }
        }

        if (!$trailingUpdated) {
            $this->line("    ✓ Position OK (PNL: " . number_format($pnlPercent, 2) . "%)");
        }
    }

    private function closePosition(Position $position, string $reason, float $exitPrice): void
    {
        $symbol = $position->symbol;

        try {
            // Determine order side based on position type
            // LONG position: close with SELL
            // SHORT position: close with BUY
            $orderSide = $position->side === 'short' ? 'buy' : 'sell';

            $this->line("    📤 Closing {$position->side} position on Binance ({$orderSide} order)...");

            $order = $this->binance->getExchange()->createMarketOrder(
                $symbol,
                $orderSide,
                $position->quantity
            );

            $actualExitPrice = $order['average'] ?? $order['price'] ?? $exitPrice;

            // Calculate realized PNL
            $priceDiff = $actualExitPrice - $position->entry_price;
            if ($position->side === 'short') {
                $priceDiff = -$priceDiff;
            }
            $realizedPnl = $priceDiff * $position->quantity * $position->leverage;
            $pnlPercent = ($priceDiff / $position->entry_price) * 100 * $position->leverage;

            // Determine close reason and metadata
            $exitPlan = $position->exit_plan ?? [];
            $trailingLevel = $exitPlan['trailing_level'] ?? null;
            $maxProfitReached = $exitPlan['max_profit_reached'] ?? null;

            // Map internal reason to enum close_reason
            $closeReason = match($reason) {
                'take_profit' => 'take_profit',
                'stop_loss' => $trailingLevel ? "trailing_stop_l{$trailingLevel}" : 'stop_loss',
                'liquidation_protection' => 'liquidated',
                'trend_invalidation' => 'other',
                default => 'other',
            };

            // Build close metadata
            $closeMetadata = [
                'profit_pct' => round($pnlPercent, 2),
                'exit_price' => $actualExitPrice,
                'order_id' => $order['id'] ?? null,
            ];

            if ($trailingLevel) {
                $closeMetadata['trailing_level'] = $trailingLevel;
                $closeMetadata['max_profit_reached'] = $maxProfitReached;
                $closeMetadata['locked_profit_pct'] = $pnlPercent;
            }

            if ($reason === 'trend_invalidation') {
                $closeMetadata['reason_detail'] = 'Trend invalidation detected';
            }

            // Update position
            $position->update([
                'is_open' => false,
                'closed_at' => now(),
                'current_price' => $actualExitPrice,
                'realized_pnl' => $realizedPnl,
                'close_reason' => $closeReason,
                'close_metadata' => $closeMetadata,
            ]);

            $pnlEmoji = $realizedPnl >= 0 ? '🟢' : '🔴';
            $this->info("    {$pnlEmoji} CLOSED ({$closeReason}): PNL \${$realizedPnl} ({$pnlPercent}%)");

            Log::info("✅ Position auto-closed", [
                'symbol' => $symbol,
                'close_reason' => $closeReason,
                'exit_price' => $actualExitPrice,
                'pnl' => $realizedPnl,
                'pnl_percent' => $pnlPercent,
                'metadata' => $closeMetadata,
            ]);

        } catch (Exception $e) {
            $this->error("    ❌ Failed to close: {$e->getMessage()}");
            throw $e;
        }
    }
}
