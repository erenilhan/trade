<?php

namespace App\Console\Commands;

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

        $this->line("  📊 {$symbol}: \${$currentPrice}");

        // 1. CHECK TAKE PROFIT
        if ($profitTarget && $currentPrice >= $profitTarget) {
            $this->info("    🎯 Take Profit hit! Target: \${$profitTarget}");
            $this->closePosition($position, 'take_profit', $currentPrice);
            return;
        }

        // 2. CHECK STOP LOSS
        if ($stopLoss && $currentPrice <= $stopLoss) {
            $this->warn("    🛑 Stop Loss hit! Stop: \${$stopLoss}");
            $this->closePosition($position, 'stop_loss', $currentPrice);
            return;
        }

        // 3. CHECK LIQUIDATION DANGER (only for extreme emergencies)
        if ($liqPrice) {
            $distanceToLiq = (($currentPrice - $liqPrice) / $currentPrice) * 100;

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

        // 5. TRAILING STOP (Move stop loss to breakeven if +5% profit)
        $pnlPercent = (($currentPrice - $entryPrice) / $entryPrice) * 100 * $position->leverage;

        if ($pnlPercent >= 5 && $stopLoss && $stopLoss < $entryPrice) {
            // Move stop to entry (risk-free)
            $exitPlan['stop_loss'] = $entryPrice;
            $position->update(['exit_plan' => $exitPlan]);
            $this->info("    🔒 Trailing Stop: Stop moved to breakeven (\${$entryPrice})");
        }

        $this->line("    ✓ Position OK (PNL: {$pnlPercent}%)");
    }

    private function closePosition(Position $position, string $reason, float $exitPrice): void
    {
        $symbol = $position->symbol;

        try {
            // Send SELL order to Binance
            $this->line("    📤 Closing position on Binance...");

            $order = $this->binance->getExchange()->createMarketOrder(
                $symbol,
                'sell',
                $position->quantity
            );

            $actualExitPrice = $order['average'] ?? $order['price'] ?? $exitPrice;

            // Calculate realized PNL
            $priceDiff = $actualExitPrice - $position->entry_price;
            if ($position->side === 'short') {
                $priceDiff = -$priceDiff;
            }
            $realizedPnl = $priceDiff * $position->quantity * $position->leverage;

            // Update position
            $position->update([
                'is_open' => false,
                'closed_at' => now(),
                'current_price' => $actualExitPrice,
                'realized_pnl' => $realizedPnl,
            ]);

            $pnlEmoji = $realizedPnl >= 0 ? '🟢' : '🔴';
            $this->info("    {$pnlEmoji} CLOSED ({$reason}): PNL \${$realizedPnl}");

            Log::info("✅ Position auto-closed", [
                'symbol' => $symbol,
                'reason' => $reason,
                'exit_price' => $actualExitPrice,
                'pnl' => $realizedPnl,
                'order_id' => $order['id'] ?? null,
            ]);

        } catch (Exception $e) {
            $this->error("    ❌ Failed to close: {$e->getMessage()}");
            throw $e;
        }
    }
}
