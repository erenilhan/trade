<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Models\Position;
use App\Services\BinanceService;
use App\Services\MultiCoinAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class ExecuteMultiCoinTrading extends Command
{
    protected $signature = 'trading:multi-coin';
    protected $description = 'Execute multi-coin trading with AI';

    public function __construct(
        private readonly MultiCoinAIService $ai,
        private readonly BinanceService     $binance
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Check if bot is enabled
        if (!BotSetting::get('bot_enabled', true)) {
            $this->warn('⚠️ Bot is disabled');
            return self::FAILURE;
        }

        $this->info('🚀 Starting multi-coin trading...');

        try {
            // Get account state
            $balance = $this->binance->fetchBalance();
            $cash = $balance['USDT']['free'] ?? 10000;
            $totalValue = $balance['USDT']['total'] ?? 10000;

            $initialInvestment = BotSetting::get('initial_capital', config('app.initial_capital', 10000));
            $account = [
                'cash' => $cash,
                'total_value' => $totalValue,
                'return_percent' => (($totalValue - $initialInvestment) / ($initialInvestment ?: 1)) * 100,
            ];

            $this->info("💰 Cash: \${$cash}, Total: \${$totalValue}");

            // Get AI decision
            $aiDecision = $this->ai->makeDecision($account);

            $this->info("🤖 AI made " . count($aiDecision['decisions'] ?? []) . " decisions");

            // Execute decisions
            foreach ($aiDecision['decisions'] ?? [] as $decision) {
                $symbol = $decision['symbol'];
                $action = $decision['action'];
                $confidence = $decision['confidence'] ?? 0;

                // Skip if confidence too low
                if ($confidence < 0.60 && $action !== 'hold') {
                    $this->warn("⚠️ {$symbol}: Low confidence ({$confidence}), holding");
                    continue;
                }

                $this->line("🎯 {$symbol}: {$action} (confidence: {$confidence})");

                // Execute action
                try {
                    match($action) {
                        'buy' => $this->executeBuy($symbol, $decision, $cash),
                        'close_profitable', 'stop_loss' => $this->executeClose($symbol, $action),
                        'hold' => null,
                        default => $this->warn("Unknown action: {$action}")
                    };
                } catch (Exception $e) {
                    $this->error("❌ {$symbol}: {$e->getMessage()}");
                }
            }

            $this->info('✅ Trading cycle complete');
            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Trading command failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Execute buy for a coin
     */
    private function executeBuy(string $symbol, array $decision, float $availableCash): void
    {
        // Check if position already exists
        $existingPosition = Position::active()->bySymbol($symbol)->first();
        if ($existingPosition) {
            $this->warn("  ⚠️ Position already exists for {$symbol}");
            return;
        }

        // Calculate position size
        $positionSize = BotSetting::get('position_size_usdt', 100);
        if ($availableCash < $positionSize) {
            $this->warn("  ⚠️ Insufficient cash (need \${$positionSize}, have \${$availableCash})");
            return;
        }

        $leverage = BotSetting::get('max_leverage', 2);
        $entryPrice = $decision['entry_price'] ?? $this->binance->fetchTicker($symbol)['last'];
        $targetPrice = $decision['target_price'] ?? $entryPrice * 1.05;
        $stopPrice = $decision['stop_price'] ?? $entryPrice * 0.97;

        // Calculate liquidation price
        $liqPrice = $this->binance->calculateLiquidationPrice($entryPrice, $leverage);

        // Calculate quantity
        $quantity = ($positionSize * $leverage) / $entryPrice;

        // Create position record
        $position = Position::create([
            'symbol' => $symbol,
            'side' => 'long',
            'quantity' => $quantity,
            'entry_price' => $entryPrice,
            'current_price' => $entryPrice,
            'liquidation_price' => $liqPrice,
            'leverage' => $leverage,
            'notional_value' => $positionSize * $leverage,
            'exit_plan' => [
                'profit_target' => $targetPrice,
                'stop_loss' => $stopPrice,
                'invalidation_condition' => $decision['invalidation'] ?? "Price closes below " . ($entryPrice * 0.95),
            ],
            'confidence' => $decision['confidence'],
            'risk_usd' => $positionSize * ($leverage / 100) * 3, // 3% risk
            'is_open' => true,
            'opened_at' => now(),
        ]);

        $this->info("  ✅ BUY executed: {$quantity} @ \${$entryPrice} (leverage: {$leverage}x)");
        Log::info("✅ {$symbol}: BUY executed", [
            'quantity' => $quantity,
            'entry_price' => $entryPrice,
            'leverage' => $leverage,
        ]);
    }

    /**
     * Execute close for a position
     */
    private function executeClose(string $symbol, string $action): void
    {
        $position = Position::active()->bySymbol($symbol)->first();

        if (!$position) {
            $this->warn("  ⚠️ No position to close for {$symbol}");
            return;
        }

        // Update position
        $position->update([
            'is_open' => false,
            'closed_at' => now(),
            'realized_pnl' => $position->unrealized_pnl,
        ]);

        $pnl = $position->unrealized_pnl;
        $pnlColor = $pnl >= 0 ? '🟢' : '🔴';

        $this->info("  {$pnlColor} Position closed: PNL \${$pnl} ({$action})");
        Log::info("✅ {$symbol}: Position closed ({$action})", ['pnl' => $pnl]);
    }
}
