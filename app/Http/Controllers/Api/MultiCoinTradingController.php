<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiCoinAIService;
use App\Services\MarketDataService;
use App\Services\BinanceService;
use App\Models\Position;
use App\Models\BotSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MultiCoinTradingController extends Controller
{
    public function __construct(
        private MultiCoinAIService $ai,
        private MarketDataService $marketData,
        private BinanceService $binance
    ) {}

    /**
     * Execute multi-coin trading
     */
    public function execute(): JsonResponse
    {
        try {
            Log::info('🚀 Multi-Coin Trading Execute Started');

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

            // Get AI decision for all coins
            $aiDecision = $this->ai->makeDecision($account);

            $results = [];

            // Process each coin decision
            foreach ($aiDecision['decisions'] ?? [] as $decision) {
                $symbol = $decision['symbol'];
                $action = $decision['action'];
                $confidence = $decision['confidence'] ?? 0;

                Log::info("🎯 Decision for {$symbol}", ['action' => $action, 'confidence' => $confidence]);

                // Skip if confidence too low
                if ($confidence < 0.60 && $action !== 'hold') {
                    Log::warning("⚠️ {$symbol}: Confidence too low ({$confidence}), overriding to hold");
                    $results[$symbol] = ['action' => 'hold', 'reason' => 'Low confidence'];
                    continue;
                }

                // Execute action
                $result = match($action) {
                    'buy' => $this->executeBuy($symbol, $decision, $cash),
                    'close_profitable', 'stop_loss' => $this->executeClose($symbol, $action),
                    'hold' => ['action' => 'hold', 'reason' => $decision['reasoning'] ?? 'Holding'],
                    default => ['action' => 'unknown', 'error' => 'Invalid action']
                };

                $results[$symbol] = $result;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $aiDecision['decisions'],
                    'chain_of_thought' => $aiDecision['reasoning'] ?? null,
                    'account' => [
                        'cash' => $totalValue, // Assuming cash is total value for now
                        'total_value' => $totalValue,
                        'return_percent' => (($totalValue - $initialInvestment) / ($initialInvestment ?: 1)) * 100,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Multi-Coin Execute Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute buy for a coin
     */
    private function executeBuy(string $symbol, array $decision, float $availableCash): array
    {
        // Skip BTC and ETH if cash is below $10
        if ($availableCash < 10 && in_array($symbol, ['BTC/USDT', 'ETH/USDT'])) {
            return ['action' => 'hold', 'reason' => "Cash below $10, skipping {$symbol}"];
        }

        // Check if position already exists
        $existingPosition = Position::active()->bySymbol($symbol)->first();
        if ($existingPosition) {
            return ['action' => 'hold', 'reason' => 'Position already exists'];
        }

        // Calculate position size
        $positionSize = BotSetting::get('position_size_usdt', 100);
        if ($availableCash < $positionSize) {
            return ['action' => 'hold', 'reason' => 'Insufficient cash'];
        }

        try {
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
                Log::info("📊 Leverage set to {$leverage}x for {$symbol}");
            } catch (\Exception $e) {
                Log::warning("⚠️ Leverage setting failed: " . $e->getMessage());
            }

            // Send MARKET order to Binance
            Log::info("📤 Sending BUY order to Binance for {$symbol}");
            $order = $this->binance->getExchange()->createMarketOrder(
                $symbol,
                'buy',
                $quantity
            );

            Log::info("✅ Binance order executed: ID {$order['id']}");

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

            Log::info("✅ {$symbol}: BUY executed", [
                'quantity' => $quantity,
                'entry_price' => $entryPrice,
                'leverage' => $leverage,
            ]);

            return [
                'action' => 'buy',
                'position_id' => $position->id,
                'quantity' => $quantity,
                'entry_price' => $entryPrice,
                'leverage' => $leverage,
            ];

        } catch (\Exception $e) {
            Log::error("❌ {$symbol}: BUY failed", ['error' => $e->getMessage()]);

            return ['action' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute close for a position
     */
    private function executeClose(string $symbol, string $action): array
    {
        $position = Position::active()->bySymbol($symbol)->first();

        if (!$position) {
            return ['action' => 'hold', 'reason' => 'No position to close'];
        }

        try {
            // Send MARKET SELL order to Binance
            Log::info("📤 Sending SELL order to Binance for {$symbol}");
            $order = $this->binance->getExchange()->createMarketOrder(
                $symbol,
                'sell',
                $position->quantity
            );

            $exitPrice = $order['average'] ?? $order['price'] ?? $position->current_price;
            $realizedPnl = ($exitPrice - $position->entry_price) * $position->quantity * $position->leverage;

            Log::info("✅ Binance SELL order executed: ID {$order['id']}");

            // Update position
            $position->update([
                'is_open' => false,
                'closed_at' => now(),
                'current_price' => $exitPrice,
                'realized_pnl' => $realizedPnl,
            ]);

            Log::info("✅ {$symbol}: Position closed ({$action})", [
                'pnl' => $realizedPnl,
                'exit_price' => $exitPrice,
                'order_id' => $order['id'],
            ]);

            return [
                'action' => 'close',
                'type' => $action,
                'pnl' => $realizedPnl,
                'exit_price' => $exitPrice,
                'order_id' => $order['id'],
            ];

        } catch (\Exception $e) {
            Log::error("❌ {$symbol}: Close failed", ['error' => $e->getMessage()]);

            return ['action' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current status of all coins
     */
    public function status(): JsonResponse
    {
        $positions = Position::active()->get()->mapWithKeys(function ($pos) {
            return [$pos->symbol => $pos->toPromptFormat()];
        });
        $marketData = $this->marketData->getLatestDataAllCoins('3m');

        return response()->json([
            'success' => true,
            'data' => [
                'positions' => $positions,
                'market_data' => $marketData,
                'supported_coins' => MarketDataService::getSupportedCoins(),
            ],
        ]);
    }
}
