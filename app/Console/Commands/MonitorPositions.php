<?php

namespace App\Console\Commands;

use App\Models\Position;
use App\Services\BinanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorPositions extends Command
{
    protected $signature = 'positions:monitor';
    protected $description = 'Monitor open positions and auto-close based on targets/stops';

    public function __construct(
        private readonly BinanceService $binance
    ) {
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
            } catch (\Exception $e) {
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

        // 3. CHECK LIQUIDATION DANGER
        if ($liqPrice) {
            $distanceToLiq = (($currentPrice - $liqPrice) / $currentPrice) * 100;

            if ($distanceToLiq < 10) {
                $this->error("    ⚠️ LIQUIDATION DANGER! Only {$distanceToLiq}% away from liq!");
                $this->closePosition($position, 'liquidation_protection', $currentPrice);
                return;
            }

            if ($distanceToLiq < 20) {
                $this->warn("    ⚠️ Warning: {$distanceToLiq}% from liquidation");
            }
        }

        // 4. TRAILING STOP (Move stop loss to breakeven if +5% profit)
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

        } catch (\Exception $e) {
            $this->error("    ❌ Failed to close: {$e->getMessage()}");
            throw $e;
        }
    }
}
