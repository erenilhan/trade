<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Models\CoinBlacklist;
use App\Models\DailyStat;
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

            // Initialize daily stats
            DailyStat::initializeDay($totalValue);

            // Skip AI if cash is below $10
            if ($cash < 10) {
                $this->warn('⚠️ Cash below $10, skipping AI call');
                $this->info('✅ Trading cycle complete (no trades)');
                return self::SUCCESS;
            }

            // Check if there are any coins without open positions
            $openPositionsCount = Position::active()->count();
            $supportedCoins = BotSetting::get('supported_coins', config('trading.default_active_pairs', []));
            $totalCoins = count($supportedCoins); // Get from BotSetting (19 coins by default)
            $coinsAvailableForTrading = $totalCoins - $openPositionsCount;

            if ($coinsAvailableForTrading === 0) {
                $this->warn("⚠️ All {$totalCoins} coins have open positions, skipping AI call");
                $this->info('✅ Trading cycle complete (all positions open)');
                return self::SUCCESS;
            }

            $this->info("📊 {$coinsAvailableForTrading}/{$totalCoins} coins available for trading");

            // ========================================
            // 🛡️ RISK MANAGEMENT CHECKS (UTC-BASED)
            // ========================================

            // 1. Check Sleep Mode (23:00-04:00 UTC)
            $sleepModeCheck = $this->checkSleepMode();
            if ($sleepModeCheck) {
                $this->warn("😴 {$sleepModeCheck}");
                return self::SUCCESS;
            }

            // 2. Check Daily Max Drawdown (8%)
            $drawdownCheck = DailyStat::checkMaxDrawdown();
            if ($drawdownCheck) {
                $this->error("🚨 {$drawdownCheck}");
                return self::SUCCESS;
            }

            // 3. Check Cluster Loss Cooldown (3 consecutive losses)
            $clusterCheck = $this->checkClusterLossCooldown();
            if ($clusterCheck) {
                $this->warn("⏸️ {$clusterCheck}");
                return self::SUCCESS;
            }

            // Get AI decision
            $aiDecision = $this->ai->makeDecision($account);

            $this->info("🤖 AI made " . count($aiDecision['decisions'] ?? []) . " decisions");

            // Execute decisions
            foreach ($aiDecision['decisions'] ?? [] as $decision) {
                $symbol = $decision['symbol'];
                $action = $decision['action'];
                $confidence = $decision['confidence'] ?? 0;

                // Check if coin is blacklisted
                if (CoinBlacklist::isBlacklisted($symbol) && $action === 'buy') {
                    $this->warn("🚫 {$symbol}: Blacklisted (poor performance history), skipping");
                    Log::info("🚫 Blacklist: Skipping {$symbol} - coin is blacklisted");
                    continue;
                }

                // Check minimum confidence requirement
                $minConfidence = CoinBlacklist::getMinConfidence($symbol);
                if ($confidence < $minConfidence && $action !== 'hold') {
                    $this->warn("⚠️ {$symbol}: Confidence ({$confidence}) below minimum ({$minConfidence}), holding");
                    Log::info("⚠️ Confidence: Skipping {$symbol} - requires {$minConfidence}, got {$confidence}");
                    continue;
                }

                $this->line("🎯 {$symbol}: {$action} (confidence: {$confidence})");

                // Execute action
                try {
                    match($action) {
                        'buy' => $this->executeBuy($symbol, $decision, $cash),
                        'sell' => $this->executeSell($symbol, $decision, $cash),
                        'hold' => null,
                        default => $this->warn("⚠️ Unknown action: {$action} (AI should return 'buy', 'sell', or 'hold')")
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

        // DYNAMIC COOLDOWN CHECK: Calculate based on volatility
        // Check if manual cooldown override is enabled
        if (!\App\Models\BotSetting::get('manual_cooldown_override', false)) {
            $requiredCooldown = $this->calculateDynamicCooldown($symbol);
            $lastTrade = Position::where('symbol', $symbol)
                ->orderBy('opened_at', 'desc')
                ->first();

            if ($lastTrade && $lastTrade->opened_at->diffInMinutes(now()) < $requiredCooldown) {
                $minutesAgo = $lastTrade->opened_at->diffInMinutes(now());
                $this->warn("  ⚠️ Skipping {$symbol}: Cooldown active ({$minutesAgo}min ago, need {$requiredCooldown}min)");
                Log::info("⏱️ Cooldown: Skipping {$symbol} - last trade {$minutesAgo}min ago, need {$requiredCooldown}min");
                return;
            }
        } else {
            Log::info("🔓 Manual cooldown override active - skipping dynamic cooldown for {$symbol}");
        }

        // DYNAMIC MARKET CAP DIVERSIFICATION: Limit positions per market cap segment
        $openPositions = Position::active()->get();
        $marketCapConfig = config('trading.market_cap_limits');
        $largeCap = $marketCapConfig['large_cap'];
        $midCap = $marketCapConfig['mid_cap'];
        $smallCap = $marketCapConfig['small_cap'];

        // Get volatility-adjusted limits
        $volatilityLevel = $this->getVolatilityLevel($symbol);
        $limits = $volatilityLevel === 'high'
            ? $marketCapConfig['high_volatility']
            : $marketCapConfig['normal'];

        $largeCapCount = $openPositions->whereIn('symbol', $largeCap)->count();
        $midCapCount = $openPositions->whereIn('symbol', $midCap)->count();
        $smallCapCount = $openPositions->whereIn('symbol', $smallCap)->count();

        // Check limits based on volatility
        if (in_array($symbol, $largeCap) && $largeCapCount >= $limits['max_large_cap']) {
            $this->warn("  ⚠️ Skipping {$symbol}: Max large cap positions reached ({$largeCapCount}/{$limits['max_large_cap']})");
            return;
        }

        if (in_array($symbol, $midCap) && $midCapCount >= $limits['max_mid_cap']) {
            $this->warn("  ⚠️ Skipping {$symbol}: Max mid cap positions reached ({$midCapCount}/{$limits['max_mid_cap']})");
            return;
        }

        if (in_array($symbol, $smallCap) && $smallCapCount >= $limits['max_small_cap']) {
            $this->warn("  ⚠️ Skipping {$symbol}: Max small cap positions reached ({$smallCapCount}/{$limits['max_small_cap']})");
            return;
        }

        // DYNAMIC POSITION SIZE: Calculate based on account balance
        $positionSize = $this->calculateDynamicPositionSize($availableCash);
        if ($availableCash < $positionSize) {
            $this->warn("  ⚠️ Insufficient cash (need \${$positionSize}, have \${$availableCash})");
            return;
        }

        // LEVERAGE: Use AI's recommendation if available, otherwise use dynamic calculation
        $leverage = $decision['leverage'] ?? $this->calculateDynamicLeverage($symbol);

        // Validate leverage (2-10x range)
        $maxLeverage = BotSetting::get('max_leverage', 10);
        $leverage = max(2, min($maxLeverage, $leverage));

        $entryPrice = $decision['entry_price'] ?? $this->binance->fetchTicker($symbol)['last'];
        $targetPrice = $decision['target_price'] ?? $entryPrice * 1.05;
        
        // Dynamic stop loss based on leverage: max 8% P&L loss (increased from 6% for volatility tolerance)
        // Formula: price_stop% = 8% / leverage
        // Examples: 2x = 4% price stop, 3x = 2.67% price stop, 5x = 1.6% price stop
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

    /**
     * Execute sell (SHORT) for a coin
     */
    private function executeSell(string $symbol, array $decision, float $availableCash): void
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

        // Use same cooldown logic as buy
        if (!\App\Models\BotSetting::get('manual_cooldown_override', false)) {
            $requiredCooldown = $this->calculateDynamicCooldown($symbol);
            $lastTrade = Position::where('symbol', $symbol)
                ->orderBy('opened_at', 'desc')
                ->first();

            if ($lastTrade && $lastTrade->opened_at->diffInMinutes(now()) < $requiredCooldown) {
                $minutesAgo = $lastTrade->opened_at->diffInMinutes(now());
                $this->warn("  ⚠️ Skipping {$symbol}: Cooldown active ({$minutesAgo}min ago, need {$requiredCooldown}min)");
                return;
            }
        }

        $positionSize = $this->calculateDynamicPositionSize($availableCash);
        if ($availableCash < $positionSize) {
            $this->warn("  ⚠️ Insufficient cash (need \${$positionSize}, have \${$availableCash})");
            return;
        }

        $leverage = $decision['leverage'] ?? $this->calculateDynamicLeverage($symbol);
        $maxLeverage = BotSetting::get('max_leverage', 10);
        $leverage = max(2, min($maxLeverage, $leverage));

        $entryPrice = $decision['entry_price'] ?? $this->binance->fetchTicker($symbol)['last'];
        $targetPrice = $decision['target_price'] ?? $entryPrice * 0.95; // SHORT target is lower
        
        // SHORT stop loss (price goes UP)
        $maxPnlLoss = 8.0;
        $priceStopPercent = $maxPnlLoss / $leverage;
        $stopPrice = $decision['stop_price'] ?? $entryPrice * (1 + ($priceStopPercent / 100));

        $quantity = ($positionSize * $leverage) / $entryPrice;

        // Set leverage
        try {
            $this->binance->getExchange()->setLeverage($leverage, $symbol);
            $this->line("  📊 Leverage set to {$leverage}x");
        } catch (\Exception $e) {
            $this->warn("  ⚠️ Leverage setting failed: " . $e->getMessage());
        }

        // Send MARKET SELL order (open SHORT)
        $this->line("  📤 Sending SELL (SHORT) order to Binance...");
        $order = $this->binance->getExchange()->createMarketOrder(
            $symbol,
            'sell',
            $quantity
        );

        $this->info("  ✅ Binance SHORT order executed: ID {$order['id']}");

        $actualEntryPrice = $order['average'] ?? $order['price'] ?? $entryPrice;

        // Create SHORT position record
        Position::create([
            'symbol' => $symbol,
            'side' => 'short',
            'quantity' => $order['filled'] ?? $quantity,
            'entry_price' => $actualEntryPrice,
            'current_price' => $actualEntryPrice,
            'leverage' => $leverage,
            'notional_value' => $positionSize * $leverage,
            'notional_usd' => $positionSize * $leverage,
            'entry_order_id' => $order['id'],
            'exit_plan' => [
                'profit_target' => $targetPrice,
                'stop_loss' => $stopPrice,
                'invalidation_condition' => $decision['invalidation'] ?? "Price closes above " . ($actualEntryPrice * 1.05),
            ],
            'confidence' => $decision['confidence'],
            'risk_usd' => $positionSize * ($leverage / 100) * 3,
            'is_open' => true,
            'opened_at' => now(),
        ]);

        $this->info("  ✅ SELL (SHORT) executed: {$quantity} @ \${$entryPrice} (leverage: {$leverage}x)");
        Log::info("✅ {$symbol}: SELL (SHORT) executed", [
            'quantity' => $quantity,
            'entry_price' => $entryPrice,
            'leverage' => $leverage,
        ]);
    }

    /**
     * Calculate dynamic position size based on account balance
     */
    private function calculateDynamicPositionSize(float $availableCash): float
    {
        if (!config('trading.dynamic_position_sizing.enabled', true)) {
            return BotSetting::get('position_size_usdt', 100);
        }

        $riskPercent = config('trading.dynamic_position_sizing.risk_percent', 2.5);
        $minSize = config('trading.dynamic_position_sizing.min_position_size', 10);
        $maxSize = config('trading.dynamic_position_sizing.max_position_size', 500);

        $positionSize = $availableCash * ($riskPercent / 100);
        $positionSize = max($minSize, min($maxSize, $positionSize));

        return round($positionSize, 2);
    }

    /**
     * Calculate dynamic leverage based on volatility
     */
    private function calculateDynamicLeverage(string $symbol): int
    {
        if (!config('trading.dynamic_leverage.enabled', true)) {
            return BotSetting::get('max_leverage', 2);
        }

        $volatilityLevel = $this->getVolatilityLevel($symbol);

        return match($volatilityLevel) {
            'low' => config('trading.dynamic_leverage.low_volatility_leverage', 5),
            'high' => config('trading.dynamic_leverage.high_volatility_leverage', 2),
            default => config('trading.dynamic_leverage.medium_volatility_leverage', 3),
        };
    }

    /**
     * Calculate dynamic cooldown based on volatility
     */
    private function calculateDynamicCooldown(string $symbol): int
    {
        if (!config('trading.dynamic_cooldown.enabled', true)) {
            return 60; // Default 1 hour
        }

        $volatilityLevel = $this->getVolatilityLevel($symbol);

        return match($volatilityLevel) {
            'low' => config('trading.dynamic_cooldown.low_volatility_minutes', 120),
            'high' => config('trading.dynamic_cooldown.high_volatility_minutes', 30),
            default => config('trading.dynamic_cooldown.medium_volatility_minutes', 60),
        };
    }

    /**
     * Get volatility level for a symbol
     * Returns: 'low', 'medium', or 'high'
     */
    private function getVolatilityLevel(string $symbol): string
    {
        try {
            // Get recent ATR data from market_data table
            $recentData = \App\Models\MarketData::where('symbol', $symbol)
                ->where('timeframe', '3m')
                ->orderBy('timestamp', 'desc')
                ->limit(100)
                ->get();

            if ($recentData->isEmpty()) {
                return 'medium'; // Default to medium if no data
            }

            // Get current ATR
            $currentAtr = $recentData->first()->atr14 ?? 0;

            // Calculate average ATR over last 100 periods
            $avgAtr = $recentData->avg('atr14') ?: 1;

            // Calculate ratio
            $atrRatio = $currentAtr / $avgAtr;

            // Classify volatility
            if ($atrRatio < 0.70) {
                return 'low';
            } elseif ($atrRatio > 1.30) {
                return 'high';
            } else {
                return 'medium';
            }

        } catch (Exception $e) {
            Log::warning("Failed to calculate volatility for {$symbol}: " . $e->getMessage());
            return 'medium'; // Fallback to medium on error
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
        // Check if manual cooldown override is enabled
        if (\App\Models\BotSetting::get('manual_cooldown_override', false)) {
            Log::info("🔓 Manual cooldown override active - skipping cluster loss cooldown");
            return null;
        }

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

}
