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

            // Skip AI if cash is below $1
            if ($cash < 1) {
                $this->warn('⚠️ Cash below $1, skipping AI call');
                $this->info('✅ Trading cycle complete (no trades)');
                return self::SUCCESS;
            }

            // Check if there are any coins without open positions
            $openPositionsCount = Position::active()->count();
            $totalCoins = 10; // BTC, ETH, SOL, BNB, XRP, DOGE, ADA, AVAX, LINK, DOT
            $coinsAvailableForTrading = $totalCoins - $openPositionsCount;

            if ($coinsAvailableForTrading === 0) {
                $this->warn("⚠️ All {$totalCoins} coins have open positions, skipping AI call");
                $this->info('✅ Trading cycle complete (all positions open)');
                return self::SUCCESS;
            }

            $this->info("📊 {$coinsAvailableForTrading}/{$totalCoins} coins available for trading");

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
                        'hold' => null,
                        default => $this->warn("⚠️ Unknown action: {$action} (AI should only return 'buy' or 'hold')")
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
        // Skip BTC, ETH, and BNB if cash is below $10
        if ($availableCash < 10 && in_array($symbol, ['BTC/USDT', 'ETH/USDT', 'BNB/USDT'])) {
            $this->warn("  ⚠️ Skipping {$symbol}: Cash below $10 (have \${$availableCash})");
            return;
        }

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

        // Set leverage on Binance
        try {
            $this->binance->getExchange()->setLeverage($leverage, $symbol);
            $this->line("  📊 Leverage set to {$leverage}x");
        } catch (\Exception $e) {
            $this->warn("  ⚠️ Leverage setting failed: " . $e->getMessage());
        }

        // Send MARKET order to Binance
        $this->line("  📤 Sending BUY order to Binance...");
        $order = $this->binance->getExchange()->createMarketOrder(
            $symbol,
            'buy',
            $quantity
        );

        $this->info("  ✅ Binance order executed: ID {$order['id']}");

        // Get actual fill price
        $actualEntryPrice = $order['average'] ?? $order['price'] ?? $entryPrice;

        // Create position record with Binance order info
        $position = Position::create([
            'symbol' => $symbol,
            'side' => 'long',
            'quantity' => $order['filled'] ?? $quantity,
            'entry_price' => $actualEntryPrice,
            'current_price' => $actualEntryPrice,
            'liquidation_price' => $liqPrice,
            'leverage' => $leverage,
            'notional_value' => $positionSize * $leverage,
            'notional_usd' => $positionSize * $leverage,
            'entry_order_id' => $order['id'],
            'exit_plan' => [
                'profit_target' => $targetPrice,
                'stop_loss' => $stopPrice,
                'invalidation_condition' => $decision['invalidation'] ?? "Price closes below " . ($actualEntryPrice * 0.95),
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

}
