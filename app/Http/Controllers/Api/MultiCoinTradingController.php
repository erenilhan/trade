<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiCoinAIService;
use App\Services\MarketDataService;
use App\Services\BinanceService;
use App\Models\Position;
use App\Models\BotSetting;
use App\Models\DailyStat;
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

            // Initialize daily stats
            DailyStat::initializeDay($totalValue);

            // Skip AI if cash is below $10
            if ($cash < 10) {
                Log::warning('⚠️ Cash below $10, skipping AI call');
                return response()->json([
                    'success' => true,
                    'data' => [
                        'results' => [],
                        'message' => 'Cash below $10, no trades executed',
                        'account' => $account
                    ]
                ]);
            }

            // ========================================
            // 🛡️ RISK MANAGEMENT CHECKS (UTC-BASED)
            // ========================================

            // 1. Check Sleep Mode (23:00-04:00 UTC)
            $sleepModeCheck = $this->checkSleepMode();
            if ($sleepModeCheck) {
                Log::warning("😴 {$sleepModeCheck}");
                return response()->json([
                    'success' => true,
                    'data' => [
                        'results' => [],
                        'message' => $sleepModeCheck,
                        'account' => $account,
                        'sleep_mode' => true,
                    ]
                ]);
            }

            // 2. Check Daily Max Drawdown (8%)
            $drawdownCheck = DailyStat::checkMaxDrawdown();
            if ($drawdownCheck) {
                Log::error("🚨 {$drawdownCheck}");
                return response()->json([
                    'success' => true,
                    'data' => [
                        'results' => [],
                        'message' => $drawdownCheck,
                        'account' => $account,
                        'max_drawdown_hit' => true,
                    ]
                ]);
            }

            // 3. Check Cluster Loss Cooldown (3 consecutive losses)
            $clusterCheck = $this->checkClusterLossCooldown();
            if ($clusterCheck) {
                Log::warning("⏸️ {$clusterCheck}");
                return response()->json([
                    'success' => true,
                    'data' => [
                        'results' => [],
                        'message' => $clusterCheck,
                        'account' => $account,
                        'cluster_cooldown' => true,
                    ]
                ]);
            }

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

                // Reduce leverage for risky 75-79% confidence range (historically poor performance)
                if ($confidence >= 0.75 && $confidence < 0.80 && in_array($action, ['buy', 'sell'])) {
                    $decision['leverage'] = min($decision['leverage'] ?? 2, 2); // Cap at 2x for this range
                    Log::warning("⚠️ {$symbol}: Confidence {$confidence} in risky range (75-79%), capping leverage at 2x");
                }

                // Execute action
                $result = match($action) {
                    'buy' => $this->executeBuy($symbol, $decision, $cash),
                    'sell' => $this->executeSell($symbol, $decision, $cash),
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
        // Skip BTC, ETH, and BNB if cash is below $10
        if ($availableCash < 10 && in_array($symbol, ['BTC/USDT', 'ETH/USDT', 'BNB/USDT'])) {
            return ['action' => 'hold', 'reason' => "Cash below $10, skipping {$symbol}"];
        }

        // Check if position already exists
        $existingPosition = Position::active()->bySymbol($symbol)->first();
        if ($existingPosition) {
            return ['action' => 'hold', 'reason' => 'Position already exists'];
        }

        // Calculate position size
        $positionSize = BotSetting::get('position_size_usdt', 10);

        if ($availableCash < $positionSize) {
            return ['action' => 'hold', 'reason' => 'Insufficient cash'];
        }

        try {
            // Use AI recommended leverage if available, otherwise use max_leverage setting
            $leverage = $decision['leverage'] ?? BotSetting::get('max_leverage', 2);
            $maxLeverage = BotSetting::get('max_leverage', 10);

            // Safety cap: don't exceed max_leverage
            if ($leverage > $maxLeverage) {
                Log::warning("AI suggested {$leverage}x but max is {$maxLeverage}x, capping");
                $leverage = $maxLeverage;
            }

            $entryPrice = $decision['entry_price'] ?? $this->binance->fetchTicker($symbol)['last'];
            $targetPrice = $decision['target_price'] ?? $entryPrice * 1.05;

            // Dynamic stop loss based on leverage: max 8% P&L loss (increased from 6% for volatility tolerance)
            // Formula: price_stop% = 8% / leverage
            // Examples: 2x = 4% price stop, 3x = 2.67% price stop, 5x = 1.6% price stop
            // Increased tolerance to reduce premature stop loss triggers (20 trades had 0% win rate with 6% limit)
            $maxPnlLoss = 8.0; // Maximum P&L loss % (was 6.0)
            $priceStopPercent = $maxPnlLoss / $leverage;
            $stopPrice = $decision['stop_price'] ?? $entryPrice * (1 - ($priceStopPercent / 100));

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

            // Record BUY trade in trades table
            \App\Models\Trade::create([
                'order_id' => $order['id'],
                'symbol' => $symbol,
                'side' => 'buy',
                'type' => 'market',
                'amount' => $order['filled'] ?? $quantity,
                'price' => $actualEntryPrice,
                'cost' => ($order['filled'] ?? $quantity) * $actualEntryPrice,
                'leverage' => $leverage,
                'stop_loss' => $stopPrice,
                'take_profit' => $targetPrice,
                'status' => 'filled',
                'response_data' => json_encode($order),
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
     * Execute sell (SHORT) for a coin
     */
    private function executeSell(string $symbol, array $decision, float $availableCash): array
    {
        // Skip BTC, ETH, and BNB if cash is below $10
        if ($availableCash < 10 && in_array($symbol, ['BTC/USDT', 'ETH/USDT', 'BNB/USDT'])) {
            return ['action' => 'hold', 'reason' => "Cash below $10, skipping {$symbol}"];
        }

        // Check if position already exists
        $existingPosition = Position::active()->bySymbol($symbol)->first();
        if ($existingPosition) {
            return ['action' => 'hold', 'reason' => 'Position already exists'];
        }

        // Calculate position size
        $positionSize = BotSetting::get('position_size_usdt', 10);

        if ($availableCash < $positionSize) {
            return ['action' => 'hold', 'reason' => 'Insufficient cash'];
        }

        try {
            // Use AI recommended leverage if available, otherwise use max_leverage setting
            $leverage = $decision['leverage'] ?? BotSetting::get('max_leverage', 2);
            $maxLeverage = BotSetting::get('max_leverage', 10);

            // Safety cap: don't exceed max_leverage
            if ($leverage > $maxLeverage) {
                Log::warning("AI suggested {$leverage}x but max is {$maxLeverage}x, capping");
                $leverage = $maxLeverage;
            }

            $entryPrice = $decision['entry_price'] ?? $this->binance->fetchTicker($symbol)['last'];
            // SHORT: profit when price goes DOWN, stop when price goes UP
            $targetPrice = $decision['target_price'] ?? $entryPrice * 0.95; // -5% target

            // Dynamic stop loss based on leverage: max 8% P&L loss (increased from 6% for volatility tolerance)
            // Formula: price_stop% = 8% / leverage
            // Examples: 2x = 4% price stop, 3x = 2.67% price stop, 5x = 1.6% price stop
            // Increased tolerance to reduce premature stop loss triggers
            $maxPnlLoss = 8.0; // Maximum P&L loss % (was 6.0)
            $priceStopPercent = $maxPnlLoss / $leverage;
            $stopPrice = $decision['stop_price'] ?? $entryPrice * (1 + ($priceStopPercent / 100)); // SHORT: stop above entry

            // Calculate liquidation price for SHORT
            $liqPrice = $this->binance->calculateLiquidationPrice($entryPrice, $leverage, 'short');

            // Calculate quantity
            $quantity = ($positionSize * $leverage) / $entryPrice;

            // Set leverage on Binance
            try {
                $this->binance->getExchange()->setLeverage($leverage, $symbol);
                Log::info("📊 Leverage set to {$leverage}x for {$symbol}");
            } catch (\Exception $e) {
                Log::warning("⚠️ Leverage setting failed: " . $e->getMessage());
            }

            // Send MARKET SELL order to Binance (open SHORT position)
            Log::info("📤 Sending SELL (SHORT) order to Binance for {$symbol}");
            $order = $this->binance->getExchange()->createMarketOrder(
                $symbol,
                'sell',
                $quantity
            );

            Log::info("✅ Binance SHORT order executed: ID {$order['id']}");

            // Get actual fill price
            $actualEntryPrice = $order['average'] ?? $order['price'] ?? $entryPrice;

            // Create position record with Binance order info
            $position = Position::create([
                'symbol' => $symbol,
                'side' => 'short',
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
                    'invalidation_condition' => $decision['invalidation'] ?? "Price closes above " . ($actualEntryPrice * 1.05),
                    'reasoning' => $decision['reasoning'] ?? 'SHORT position based on bearish signals',
                ],
                'confidence' => $decision['confidence'],
                'risk_usd' => $positionSize * ($leverage / 100) * 3, // 3% risk
                'is_open' => true,
                'opened_at' => now(),
            ]);

            // Record SELL (SHORT) trade in trades table
            \App\Models\Trade::create([
                'order_id' => $order['id'],
                'symbol' => $symbol,
                'side' => 'sell',
                'type' => 'market',
                'amount' => $order['filled'] ?? $quantity,
                'price' => $actualEntryPrice,
                'cost' => ($order['filled'] ?? $quantity) * $actualEntryPrice,
                'leverage' => $leverage,
                'stop_loss' => $stopPrice,
                'take_profit' => $targetPrice,
                'status' => 'filled',
                'response_data' => json_encode($order),
            ]);

            Log::info("✅ {$symbol}: SELL (SHORT) executed", [
                'quantity' => $quantity,
                'entry_price' => $entryPrice,
                'leverage' => $leverage,
            ]);

            return [
                'action' => 'sell',
                'position_id' => $position->id,
                'quantity' => $quantity,
                'entry_price' => $entryPrice,
                'leverage' => $leverage,
            ];

        } catch (\Exception $e) {
            Log::error("❌ {$symbol}: SELL (SHORT) failed", ['error' => $e->getMessage()]);

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
            $pnlPercent = (($exitPrice - $position->entry_price) / $position->entry_price) * 100 * $position->leverage;

            Log::info("✅ Binance SELL order executed: ID {$order['id']}");

            // Determine close reason based on AI action
            $closeReason = match($action) {
                'close_profitable' => 'take_profit',
                'stop_loss' => 'stop_loss',
                default => 'manual',
            };

            $closeMetadata = [
                'profit_pct' => round($pnlPercent, 2),
                'exit_price' => $exitPrice,
                'order_id' => $order['id'] ?? null,
                'ai_decision' => true,
                'reason_detail' => $action === 'close_profitable' ? 'AI decided to take profit' : 'AI decided to cut losses',
            ];

            // Update position
            $position->update([
                'is_open' => false,
                'closed_at' => now(),
                'current_price' => $exitPrice,
                'realized_pnl' => $realizedPnl,
                'close_reason' => $closeReason,
                'close_metadata' => $closeMetadata,
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
     * Check if we're in sleep mode (low liquidity hours)
     */
    private function checkSleepMode(): ?string
    {
        $config = config('trading.sleep_mode');

        if (!$config['enabled']) {
            return null;
        }

        $currentHourUTC = now()->utc()->hour;
        $startHour = $config['start_hour'];
        $endHour = $config['end_hour'];

        // Handle wrap-around (23:00 - 04:00)
        $inSleepMode = false;
        if ($startHour > $endHour) {
            // Sleep mode wraps around midnight (e.g., 23:00 - 04:00)
            $inSleepMode = $currentHourUTC >= $startHour || $currentHourUTC < $endHour;
        } else {
            // Normal range (e.g., 01:00 - 05:00)
            $inSleepMode = $currentHourUTC >= $startHour && $currentHourUTC < $endHour;
        }

        if (!$inSleepMode) {
            return null;
        }

        // We're in sleep mode
        if (!$config['allow_new_trades']) {
            // Check if we already have open positions
            $openPositions = Position::where('is_open', true)->count();

            // If we're at or over the sleep mode limit, don't allow new trades
            if ($openPositions >= $config['max_positions']) {
                return "Sleep mode active (UTC {$startHour}:00-{$endHour}:00). Max {$config['max_positions']} positions allowed, currently at {$openPositions}. No new trades.";
            }

            // We're in sleep mode but under position limit
            return "Sleep mode active (UTC {$startHour}:00-{$endHour}:00). Limited trading - max {$config['max_positions']} positions.";
        }

        return null;
    }

    /**
     * Check for cluster loss cooldown (consecutive losses trigger trading pause)
     */
    private function checkClusterLossCooldown(): ?string
    {
        $config = config('trading.cluster_loss_cooldown');

        if (!$config['enabled']) {
            return null;
        }

        // Look at recent closed positions
        $lookbackTime = now()->subHours($config['lookback_hours']);
        $recentPositions = Position::where('is_open', false)
            ->where('closed_at', '>=', $lookbackTime)
            ->orderBy('closed_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentPositions->count() < $config['consecutive_losses_trigger']) {
            return null;
        }

        // Check if we have N consecutive losses
        $consecutiveLosses = 0;
        foreach ($recentPositions as $position) {
            if (($position->realized_pnl ?? 0) < 0) {
                $consecutiveLosses++;
            } else {
                // Streak broken by a win
                break;
            }
        }

        if ($consecutiveLosses >= $config['consecutive_losses_trigger']) {
            // Check if we're still in cooldown period
            $lastLoss = $recentPositions->first();
            $cooldownEnds = $lastLoss->closed_at->addHours($config['cooldown_hours']);

            if (now()->lt($cooldownEnds)) {
                $remainingHours = now()->diffInHours($cooldownEnds, false);
                return "Cluster loss cooldown active ({$consecutiveLosses} consecutive losses). Trading paused for {$remainingHours}h to prevent emotional trading.";
            }
        }

        return null;
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
