<?php

namespace App\Console\Commands;

use App\Models\Position;
use App\Services\BinanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateOpenPositions extends Command
{
    protected $signature = 'positions:update';
    protected $description = 'Update current prices and PNL for all open positions';

    public function __construct(
        private readonly BinanceService $binance
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $openPositions = Position::where('is_open', true)->get();

        if ($openPositions->isEmpty()) {
            $this->info('📭 No open positions to update');
            return self::SUCCESS;
        }

        $this->info("📊 Updating {$openPositions->count()} open positions...");

        foreach ($openPositions as $position) {
            try {
                // Fetch current price from Binance
                $ticker = $this->binance->fetchTicker($position->symbol);
                $currentPrice = $ticker['last'];

                // Calculate unrealized PNL
                $priceDiff = $currentPrice - $position->entry_price;
                if ($position->side === 'short') {
                    $priceDiff = -$priceDiff;
                }
                $unrealizedPnl = $priceDiff * $position->quantity * $position->leverage;

                // Calculate distance to targets
                $exitPlan = $position->exit_plan;
                $profitTarget = $exitPlan['profit_target'] ?? null;
                $stopLoss = $exitPlan['stop_loss'] ?? null;

                $distanceToProfit = $profitTarget ? (($profitTarget - $currentPrice) / $currentPrice) * 100 : null;
                $distanceToStop = $stopLoss ? (($currentPrice - $stopLoss) / $currentPrice) * 100 : null;

                // Update position
                $position->update([
                    'current_price' => $currentPrice,
                    'unrealized_pnl' => $unrealizedPnl,
                ]);

                $pnlEmoji = $unrealizedPnl >= 0 ? '🟢' : '🔴';
                $this->line("  {$pnlEmoji} {$position->symbol}: \${$currentPrice} (PNL: \${$unrealizedPnl})");

                if ($profitTarget) {
                    $this->line("    🎯 Target: \${$profitTarget} ({$distanceToProfit}% away)");
                }
                if ($stopLoss) {
                    $this->line("    🛑 Stop: \${$stopLoss} ({$distanceToStop}% buffer)");
                }

            } catch (\Exception $e) {
                $this->error("  ❌ {$position->symbol}: {$e->getMessage()}");
                Log::error("Failed to update position {$position->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info('✅ Position update complete');
        return self::SUCCESS;
    }
}
