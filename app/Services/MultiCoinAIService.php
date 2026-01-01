<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\BotSetting;
use App\Models\Position;
use DeepSeek\DeepSeekClient;
use Exception;
use Illuminate\Support\Facades\Log;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseFormatData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use MoeMizrak\LaravelOpenrouter\Types\RoleType;
use ReflectionException;

class MultiCoinAIService
{
    private MarketDataService $marketData;
    private string $provider;
    private $aiCalculation;
    private AIService $aiService;

    public function __construct(MarketDataService $marketData, AIService $aiService)
    {
        set_time_limit(9999);
        ini_set('memory_limit', '99999M');
        ini_set('max_execution_time', '99999');
        $this->marketData = $marketData;
        $this->aiService = $aiService;
        $this->aiCalculation = app(AICalculationService::class);

        // Get AI provider from BotSetting or .env
        $this->provider = BotSetting::get('ai_provider')
            ?? config('app.ai_provider', 'openrouter');
    }

    /**
     * Make multi-coin trading decision using OPTIMIZED AI calculations
     * SMART: Pre-filter + Cache + Batch processing
     */
    public function makeDecisionWithAICalculations(array $account): array
    {
        try {
            // FORCED: Always use AI calculations
            Log::info("🧠 Using OPTIMIZED AI calculations (cached + pre-filtered)");
            
            $supportedCoins = MarketDataService::getSupportedCoins();
            
            // Get batch AI calculations (only for promising coins)
            $aiData15m = $this->aiCalculation->getBatchAICalculations($supportedCoins, '15m');
            $aiData1h = $this->aiCalculation->getBatchAICalculations($supportedCoins, '1h');

            $allMarketData = [];
            foreach ($aiData15m as $symbol => $data15m) {
                if (isset($aiData1h[$symbol])) {
                    $allMarketData[$symbol] = [
                        '15m' => $this->convertCompactFormat($data15m['i']),
                        '1h' => $this->convertCompactFormat($aiData1h[$symbol]['i']),
                    ];
                }
            }

            if (empty($allMarketData)) {
                Log::warning("⚠️ No promising coins found for AI calculation");
                return $this->makeDecision($account); // Fallback
            }

            Log::info("🎯 AI calculated " . count($allMarketData) . " promising coins (saved " . (30 - count($allMarketData)) . " API calls)");

            // Build prompt with AI-calculated data
            $prompt = $this->buildMultiCoinPrompt($account, $allMarketData);
            
            // Get trading decision
            $response = $this->aiService->makeRequest($prompt);
            $decision = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON response");
            }

            return $decision;

        } catch (\Exception $e) {
            Log::error("❌ Optimized AI calculation failed: " . $e->getMessage());
            return $this->makeDecision($account); // Always fallback
        }
    }

    /**
     * Convert compact AI format to standard format
     */
    private function convertCompactFormat(array $compact): array
    {
        return [
            'rsi7' => $compact['r7'] ?? 50,
            'rsi14' => $compact['r14'] ?? 50,
            'macd' => $compact['m'] ?? 0,
            'macd_signal' => $compact['ms'] ?? 0,
            'ema20' => $compact['e20'] ?? 0,
            'ema50' => $compact['e50'] ?? 0,
            'adx' => $compact['adx'] ?? 0,
            'atr14' => $compact['atr'] ?? 0,
            'volume_ratio' => $compact['vr'] ?? 1,
            'price' => $compact['price'] ?? $compact['e20'] ?? 0, // Use actual price or fallback to EMA20
        ];
    }
    public function makeDecision(array $account): array
    {
        try {
            // Get market data from database (not live API)
            $data15m = $this->marketData->getLatestDataAllCoins('15m');
            $data1h = $this->marketData->getLatestDataAllCoins('1h');

            // Convert to expected format
            $allMarketData = [];
            foreach ($data15m as $symbol => $data) {
                if (isset($data1h[$symbol])) {
                    $allMarketData[$symbol] = [
                        '15m' => $data,
                        '1h' => $data1h[$symbol],
                    ];
                }
            }

            // Check if we have any market data
            if (empty($allMarketData)) {
                Log::warning("⚠️ No market data available in database");
                return [
                    'decisions' => [],
                    'reasoning' => 'No market data available in database',
                ];
            }

            // Check if batch processing is enabled (for free models with rate limits)
            $batchConfig = config('trading.ai_batch_processing');
            if ($batchConfig['enabled'] && count($allMarketData) > $batchConfig['coins_per_batch']) {
                return $this->makeDecisionInBatches($account, $allMarketData, $batchConfig);
            }

            // Single request for all coins (default)
            return $this->processSingleBatch($account, $allMarketData);

        } catch (Exception $e) {
            Log::error('❌ Multi-Coin AI Error', ['error' => $e->getMessage()]);

            return [
                'decisions' => [],
                'reasoning' => 'AI error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process multiple batches of coins (for rate limit optimization)
     */
    private function makeDecisionInBatches(array $account, array $allMarketData, array $batchConfig): array
    {
        $coinsPerBatch = $batchConfig['coins_per_batch'];
        $delayBetweenBatches = $batchConfig['delay_between_batches'];

        $allCoins = array_keys($allMarketData);
        $batches = array_chunk($allCoins, $coinsPerBatch, true);

        Log::info("📦 Batch processing enabled: " . count($batches) . " batches of {$coinsPerBatch} coins each");

        $allDecisions = [];
        $allReasoning = [];

        foreach ($batches as $batchIndex => $coinBatch) {
            Log::info("📦 Processing batch " . ($batchIndex + 1) . "/" . count($batches));

            // Filter market data for this batch
            $batchMarketData = array_intersect_key($allMarketData, array_flip($coinBatch));

            // Process this batch
            $batchResult = $this->processSingleBatch($account, $batchMarketData);

            // Merge results
            $allDecisions = array_merge($allDecisions, $batchResult['decisions'] ?? []);
            $allReasoning[] = $batchResult['reasoning'] ?? '';

            // Delay before next batch (except last batch)
            if ($batchIndex < count($batches) - 1) {
                Log::info("⏳ Waiting {$delayBetweenBatches}s before next batch...");
                sleep($delayBetweenBatches);
            }
        }

        return [
            'decisions' => $allDecisions,
            'reasoning' => implode(' | ', array_filter($allReasoning)),
            'batches_processed' => count($batches),
        ];
    }

    /**
     * Process a single batch of coins
     */
    private function processSingleBatch(array $account, array $marketData): array
    {
        // Build prompt for this batch
        $prompt = $this->buildMultiCoinPrompt($account, $marketData);

        // Show which coins are being sent to AI
        $coinsInPrompt = array_keys($marketData);
        $this->showCoinsBeingSent($coinsInPrompt);

        // Show detailed prompt being sent to AI
        $this->showPromptDetails($prompt);

        Log::info("🤖 Multi-Coin AI Prompt ({$this->provider})", ['length' => strlen($prompt)]);

        // Call appropriate AI provider
        $aiResponse = match ($this->provider) {
            'deepseek' => $this->callDeepSeekAPI($prompt),
            'openrouter' => $this->callOpenRouter($prompt),
            default => throw new Exception("Invalid AI provider: {$this->provider}")
        };

        $decision = $aiResponse['decision'];
        $rawResponse = $aiResponse['raw_response'];
        $model = $aiResponse['model'];

        // Log the AI call
        $this->logAiCall($this->provider, $model, $prompt, $rawResponse, $decision);

        Log::info("🤖 Multi-Coin Decision", ['decision' => $decision]);

        // Add metadata to response
        $decision['provider'] = $this->provider;
        $decision['model'] = $model;
        if (isset($aiResponse['tokens_used'])) {
            $decision['tokens_used'] = $aiResponse['tokens_used'];
        }
        if (isset($aiResponse['cost'])) {
            $decision['cost'] = $aiResponse['cost'];
        }

        // Show AI response details
        $this->showAIResponse($decision, $rawResponse);

        return $decision;
    }

    /**
     * Build multi-coin prompt (like the example)
     */
    private function buildMultiCoinPrompt(array $account, array $allMarketData): string
    {
        $prompt = "CURRENT MARKET STATE FOR ALL COINS\n\n";

        // Skip BTC and ETH if cash is below $10
        $skipExpensiveCoins = $account['cash'] < 10;

        // Get all open positions to skip them in market data collection
        $openPositionSymbols = Position::active()->pluck('symbol')->toArray();

        // PRE-FILTER: Only send "interesting" coins to AI (saves tokens!)
        $enablePreFiltering = BotSetting::get('enable_pre_filtering', true);
        $filteredCoins = [];

        // Track which coins we're sending to AI
        $coinsToAnalyze = [];

        // Add each coin's data
        foreach ($allMarketData as $symbol => $data) {
            if (!$data) continue;

            // Skip coins with open positions (they're already being monitored)
            if (in_array($symbol, $openPositionSymbols)) {
                Log::info("⏭️ Skipping {$symbol} - already has open position");
                continue;
            }

            // Skip expensive coins if cash is low (but BTC/ETH not in new list anyway)
            if ($skipExpensiveCoins && in_array($symbol, ['PAXG/USDT', 'BNB/USDT'])) {
                continue;
            }

            // PRE-FILTERING: Hybrid filtering (time-aware + volume-aware)
            if ($enablePreFiltering) {
                $data15m = $data['15m'];
                $data1h = $data['1h'];

                // CRITICAL: Skip coins with no market data (RSI=0)
                $hasMarketData = ($data15m['rsi7'] ?? 0) > 0;
                if (!$hasMarketData) {
                    Log::info("⏭️ Pre-filtered {$symbol} - No market data available (RSI={$data15m['rsi7']})");
                    continue;
                }

                // Dynamic volume threshold based on global trading hours (UTC)
                $currentHour = now()->hour; // UTC hour

                // Get regional volume thresholds from database
                $volumeUS = BotSetting::get('volume_threshold_us', 0.9);
                $volumeAsia = BotSetting::get('volume_threshold_asia', 0.8);
                $volumeEurope = BotSetting::get('volume_threshold_europe', 0.95);
                $volumeOffPeak = BotSetting::get('volume_threshold_offpeak', 1.0);

                // Regional liquidity zones (optimized for crypto markets)
                if ($currentHour >= 13 && $currentHour <= 22) {
                    // US hours (13:00-22:00 UTC = 8am-5pm EST)
                    $minVolumeRatio = $volumeUS;
                    $region = 'US';
                } elseif ($currentHour >= 1 && $currentHour <= 9) {
                    // Asia hours (01:00-09:00 UTC = 9am-5pm Asia)
                    $minVolumeRatio = $volumeAsia;
                    $region = 'Asia';
                } elseif ($currentHour >= 7 && $currentHour <= 16) {
                    // Europe hours (07:00-16:00 UTC = 8am-5pm CET)
                    $minVolumeRatio = $volumeEurope;
                    $region = 'Europe';
                } else {
                    // Off-peak hours (low liquidity)
                    $minVolumeRatio = $volumeOffPeak;
                    $region = 'Off-peak';
                }

                Log::info("⏰ Current: {$currentHour} UTC, Region: {$region}, Min Volume: {$minVolumeRatio}x");

                // Determine 1H trend direction first (for multi-timeframe confirmation)
                $is1hUptrend = ($data1h['ema20'] ?? 0) > ($data1h['ema50'] ?? 0);
                $is1hDowntrend = ($data1h['ema20'] ?? 0) < ($data1h['ema50'] ?? 0);

                // 2025 OPTIMIZATION: ADX threshold at 20
                $adxOk = ($data1h['adx'] ?? 0) > 20;

                // If 1H ADX too weak (< 20), skip - NO weak trends
                if (($data1h['adx'] ?? 0) < 20) {
                    Log::info("⏭️ Pre-filtered {$symbol} - 1H too weak (ADX < 20)");
                    continue;
                }

                // Check volume first (critical for liquidity) - NEW: 1.1x minimum
                $volumeRatio = $data15m['volume_ratio'] ?? 0;
                if ($volumeRatio < 1.1) {
                    Log::info("⏭️ Pre-filtered {$symbol} - Volume {$volumeRatio}x < 1.1x minimum");
                    continue;
                }

                // Count how many criteria are met (need 3/5)
                $longScore = 0;
                $shortScore = 0;

                // Get RSI thresholds from database (NEW: 40-70 for long, 30-60 for short)
                $rsiLongMin = BotSetting::get('rsi_long_min', 40);
                $rsiLongMax = BotSetting::get('rsi_long_max', 70);
                $rsiShortMin = BotSetting::get('rsi_short_min', 30);
                $rsiShortMax = BotSetting::get('rsi_short_max', 60);

                // LONG scoring (volume NOT counted in score)
                if ($is1hUptrend) {
                    if (($data15m['macd'] ?? 0) > ($data15m['macd_signal'] ?? 0)) $longScore++;
                    if (($data15m['rsi7'] ?? 0) >= $rsiLongMin && ($data15m['rsi7'] ?? 0) <= $rsiLongMax) $longScore++;
                    // NEW: Price can be 0-3% from EMA20 (extended from 0-2%)
                    if ($data15m['price'] >= $data15m['ema20'] * 0.97 && $data15m['price'] <= $data15m['ema20'] * 1.03) $longScore++;
                    if ($adxOk) $longScore++;
                }

                // SHORT scoring (volume NOT counted in score)
                if ($is1hDowntrend) {
                    if (($data15m['macd'] ?? 0) < ($data15m['macd_signal'] ?? 0)) $shortScore++;
                    if (($data15m['rsi7'] ?? 0) >= $rsiShortMin && ($data15m['rsi7'] ?? 0) <= $rsiShortMax) $shortScore++;
                    // NEW: Price can be 0-3% from EMA20 (extended from 0-2%)
                    if ($data15m['price'] <= $data15m['ema20'] * 1.03 && $data15m['price'] >= $data15m['ema20'] * 0.97) $shortScore++;
                    if ($adxOk) $shortScore++;
                }

                // Need at least 3/4 score to send to AI (volume checked separately)
                if ($longScore >= 3) {
                    Log::info("✅ {$symbol} passed pre-filter (potential LONG, score {$longScore}/4, volume {$volumeRatio}x)");
                } else if ($shortScore >= 3) {
                    Log::info("✅ {$symbol} passed pre-filter (potential SHORT, score {$shortScore}/4, volume {$volumeRatio}x)");
                } else {
                    Log::info("⏭️ Pre-filtered {$symbol} - Low score (LONG {$longScore}/4, SHORT {$shortScore}/4)");
                    continue;
                }
            }

            $data15m = $data['15m'];
            $data1h = $data['1h'];

            $cleanSymbol = str_replace('/USDT', '', $symbol);

            // Track this coin (add BEFORE building prompt data)
            $coinsToAnalyze[] = $symbol;

            $prompt .= "ALL {$cleanSymbol} DATA\n";
            $macdHistogram = ($data15m['macd_histogram'] ?? ($data15m['macd'] - ($data15m['macd_signal'] ?? 0)));
            $macdHistogramRising = ($data15m['macd_histogram_rising'] ?? false);

            $prompt .= sprintf(
                "current_price = %.2f, current_ema20 = %.2f, current_macd = %.3f, macd_signal = %.3f, macd_histogram = %.3f, current_rsi (7 period) = %.2f\n",
                $data15m['price'],
                $data15m['ema20'],
                $data15m['macd'],
                $data15m['macd_signal'] ?? 0,
                $macdHistogram,
                $data15m['rsi7']
            );

            // RSI direction check (from database settings) - NEW RANGES: 40-70 for long, 30-60 for short
            $rsi = $data15m['rsi7'];
            $rsiLongMin = BotSetting::get('rsi_long_min', 40);
            $rsiLongMax = BotSetting::get('rsi_long_max', 70);
            $rsiShortMin = BotSetting::get('rsi_short_min', 30);
            $rsiShortMax = BotSetting::get('rsi_short_max', 60);

            if ($rsi >= $rsiLongMin && $rsi <= $rsiLongMax) {
                $prompt .= "RSI STATUS: ✅ IN LONG RANGE ({$rsiLongMin}-{$rsiLongMax}, current: {$rsi}) - healthy for LONG\n";
            } elseif ($rsi >= $rsiShortMin && $rsi <= $rsiShortMax) {
                $prompt .= "RSI STATUS: ✅ IN SHORT RANGE ({$rsiShortMin}-{$rsiShortMax}, current: {$rsi}) - healthy for SHORT\n";
            } elseif ($rsi < $rsiShortMin) {
                $prompt .= "RSI STATUS: ⚠️ OVERSOLD (< {$rsiShortMin}, current: {$rsi}) - too weak, avoid SHORT (bounce risk)\n";
            } else {
                $prompt .= "RSI STATUS: ⚠️ OVERBOUGHT (> {$rsiLongMax}, current: {$rsi}) - too strong, avoid LONG (pullback risk)\n";
            }

            // Price position relative to EMA20 - NEW: Extended to 3% max distance
            $ema20 = $data15m['ema20'] ?? 0;
            if ($ema20 > 0) {
                $priceVsEma = (($data15m['price'] - $ema20) / $ema20) * 100;
                if ($priceVsEma >= 0 && $priceVsEma <= 3) {
                    $prompt .= sprintf("PRICE POSITION: ✅ %.2f%% above EMA20 - good for LONG (riding uptrend)\n", $priceVsEma);
                } elseif ($priceVsEma < 0 && $priceVsEma >= -3) {
                    $prompt .= sprintf("PRICE POSITION: ✅ %.2f%% below EMA20 - good for SHORT (riding downtrend)\n", abs($priceVsEma));
                } elseif ($priceVsEma > 3) {
                    $prompt .= sprintf("PRICE POSITION: ⚠️ %.2f%% above EMA20 - too extended for LONG\n", $priceVsEma);
                } else {
                    $prompt .= sprintf("PRICE POSITION: ⚠️ %.2f%% below EMA20 - too extended for SHORT\n", abs($priceVsEma));
                }
            } else {
                $prompt .= "PRICE POSITION: ⚠️ No EMA20 data available\n";
            }
            $prompt .= "\n";

            // Direction-aware MACD check - NEW: REMOVED MACD>0 requirement, only check crossover
            $macdBullish = $data15m['macd'] > ($data15m['macd_signal'] ?? 0);

            if ($macdBullish) {
                $prompt .= "MACD STATUS: ✅ BULLISH (MACD > Signal) - **LONG signal** - evaluate LONG criteria\n\n";
            } else {
                $prompt .= "MACD STATUS: ✅ BEARISH (MACD < Signal) - **SHORT signal** - evaluate SHORT criteria\n\n";
            }

            // 2025 NEW: Bollinger Bands analysis
            $bbUpper = $data15m['indicators']['bb_upper'] ?? 0;
            $bbLower = $data15m['indicators']['bb_lower'] ?? 0;
            $bbMiddle = $data15m['indicators']['bb_middle'] ?? 0;
            $percentB = $data15m['indicators']['bb_percent_b'] ?? 0.5;

            if ($percentB < 0.2) {
                $prompt .= "BOLLINGER BANDS: ✅ OVERSOLD (%B < 0.2) - Price near lower band, potential LONG reversal\n";
            } elseif ($percentB > 0.8) {
                $prompt .= "BOLLINGER BANDS: ✅ OVERBOUGHT (%B > 0.8) - Price near upper band, potential SHORT reversal\n";
            } elseif ($percentB >= 0.4 && $percentB <= 0.6) {
                $prompt .= "BOLLINGER BANDS: ⚠️ NEUTRAL (%B in middle) - No clear signal\n";
            } else {
                $prompt .= sprintf("BOLLINGER BANDS: %s (%%.2f)\n", $percentB);
            }
            $prompt .= sprintf("  Upper: %.2f | Middle: %.2f | Lower: %.2f\n\n", $bbUpper, $bbMiddle, $bbLower);

            // 2025 NEW: Supertrend confirmation
            $supertrendBullish = $data15m['indicators']['supertrend_is_bullish'] ?? false;
            $supertrendBearish = $data15m['indicators']['supertrend_is_bearish'] ?? false;

            if ($supertrendBullish) {
                $prompt .= "SUPERTREND: ✅ BULLISH TREND - Strong confirmation for LONG positions\n\n";
            } elseif ($supertrendBearish) {
                $prompt .= "SUPERTREND: ✅ BEARISH TREND - Strong confirmation for SHORT positions\n\n";
            } else {
                $prompt .= "SUPERTREND: ⚠️ NEUTRAL - Trend unclear\n\n";
            }

            // Core volume indicator with quality assessment - NEW: 1.1x minimum threshold
            $volumeRatio = $data15m['volume_ratio'] ?? 1.0;
            if ($volumeRatio >= 1.5) {
                $volumeStatus = '✅ EXCELLENT (high liquidity, full position OK)';
            } elseif ($volumeRatio >= 1.2) {
                $volumeStatus = '✅ GOOD (normal liquidity, standard position)';
            } elseif ($volumeRatio >= 1.1) {
                $volumeStatus = '⚠️ ACCEPTABLE (moderate liquidity, prefer smaller position)';
            } else {
                $volumeStatus = '❌ WEAK (< 1.1x minimum - HOLD recommended)';
            }

            $prompt .= sprintf(
                "Volume Ratio (current/20MA): %.2fx %s\n\n",
                $volumeRatio,
                $volumeStatus
            );

            $prompt .= "Funding Rate: " . number_format($data15m['funding_rate'] ?? 0, 10) . "\n";
            $prompt .= "Open Interest: Latest: " . number_format($data15m['open_interest'] ?? 0, 2) . "\n\n";

            // Shortened series (5 candles = 75min context with 15m timeframe)
            // Only show if data available (AI calculations may not have series)
            if (isset($data15m['price_series']) && is_array($data15m['price_series'])) {
                $prompt .= "Recent 15m data (last 5 candles = 75 minutes):\n";
                $prompt .= "Price: [" . implode(',', array_map(fn($p) => number_format($p, 2), array_slice($data15m['price_series'], -5))) . "]\n";

                if (isset($data15m['indicators']['ema_series'])) {
                    $prompt .= "EMA20: [" . implode(',', array_map(fn($e) => number_format($e, 2), array_slice($data15m['indicators']['ema_series'], -5))) . "]\n";
                }
                if (isset($data15m['indicators']['macd_series'])) {
                    $prompt .= "MACD: [" . implode(',', array_map(fn($m) => number_format($m, 3), array_slice($data15m['indicators']['macd_series'], -5))) . "]\n";
                }
                if (isset($data15m['indicators']['rsi7_series'])) {
                    $prompt .= "RSI7: [" . implode(',', array_map(fn($r) => number_format($r, 1), array_slice($data15m['indicators']['rsi7_series'], -5))) . "]\n";
                }
                $prompt .= "\n";
            }

            // Volume info
            $currentVolume = $data15m['volume'] ?? 0;
            $prompt .= sprintf(
                "Volume: current=%.2f\n",
                $currentVolume
            );

            $prompt .= sprintf(
                "15m ADX(14): %.2f (Trend strength: Weak if <10, Moderate if 10-20, Strong if >20)\n\n",
                $data15m['adx'] ?? 0
            );

            $prompt .= sprintf(
                "1H: EMA20=%.2f, EMA50=%.2f, ATR=%.2f\n",
                $data1h['ema20'],
                $data1h['ema50'],
                $data1h['atr14']
            );

            // ATR volatility warning (critical safety check)
            $currentPrice = $data15m['price'] ?? 0;
            if ($currentPrice > 0) {
                $atrPercent = ($data1h['atr14'] / $currentPrice) * 100;
                $atrWarning = $atrPercent > 8 ? '⚠️ TOO VOLATILE → HOLD' : '✅ OK';
            } else {
                $atrPercent = 0;
                $atrWarning = '⚠️ No price data';
            }

            // Direction-aware 1H trend check (critical for multi-timeframe confirmation)
            $is1hUptrend = $data1h['ema20'] > ($data1h['ema50'] * 0.999);
            $adxStrong = ($data1h['adx'] ?? 0) > 20;

            if ($is1hUptrend && $adxStrong) {
                $prompt .= "1H TREND: ✅ STRONG BULLISH UPTREND (EMA20 > EMA50, ADX > 20) - **Favor LONG positions**\n";
            } elseif (!$is1hUptrend && $adxStrong) {
                $prompt .= "1H TREND: ✅ STRONG BEARISH DOWNTREND (EMA20 < EMA50, ADX > 20) - **Favor SHORT positions**\n";
            } elseif ($is1hUptrend && !$adxStrong) {
                $prompt .= "1H TREND: ⚠️ WEAK UPTREND (EMA20 > EMA50, ADX < 20) - Too weak, prefer HOLD\n";
            } else {
                $prompt .= "1H TREND: ⚠️ WEAK DOWNTREND (EMA20 < EMA50, ADX < 20) - Too weak, prefer HOLD\n";
            }

            $prompt .= sprintf(
                "VOLATILITY CHECK: ATR %.2f%% %s\n\n",
                $atrPercent,
                $atrWarning
            );
        }

        // Account information
        $prompt .= "ACCOUNT INFORMATION:\n";
        $prompt .= "Cash: \${$account['cash']}\n";
        $prompt .= "Total Value: \${$account['total_value']}\n";
        $prompt .= "Return: {$account['return_percent']}%\n\n";

        // Mention open positions count (but don't include details since they're skipped)
        $openPositionsCount = Position::active()->count();
        if ($openPositionsCount > 0) {
            $openPositionsList = Position::active()->pluck('symbol')->implode(', ');
            $prompt .= "NOTE: {$openPositionsCount} coins already have open positions ({$openPositionsList}) - they are excluded from analysis.\n\n";
        }

        // Task instructions
        $prompt .= "YOUR TASK:\n";
        $prompt .= "Analyze ONLY the coins shown above (coins without open positions).\n";
        $prompt .= "Decide: BUY (LONG), SELL (SHORT), or HOLD for each coin.\n\n";

        $prompt .= "⚠️ CRITICAL: Check the correct criteria for each coin!\n";
        $prompt .= "- If you see 'BEARISH DOWNTREND' + 'SHORT signal' → Evaluate the 5 SHORT criteria\n";
        $prompt .= "- If you see 'BULLISH UPTREND' + 'LONG signal' → Evaluate the 5 LONG criteria\n";
        $prompt .= "- DO NOT ignore SHORT opportunities! Bearish market = SHORT opportunity, not \"no trade\"\n\n";

        $prompt .= "Include ONLY: action, reasoning, confidence (0-1), leverage.\n";
        $prompt .= "DO NOT include entry_price, target_price, stop_price, or invalidation (system calculates).\n\n";

        $prompt .= "LEVERAGE:\n";
        $prompt .= "- Always use 2x leverage (safe and proven)\n";
        $prompt .= "- Historical data shows 2x outperforms 3x and 5x\n\n";

        $prompt .= "RESPONSE FORMAT (strict JSON):\n";
        $prompt .= '{"decisions":[{"symbol":"ALPACA/USDT","action":"buy|sell|hold","reasoning":"...","confidence":0.70,"leverage":2}],"chain_of_thought":"..."}\n';
        $prompt .= "\nACTIONS:\n";
        $prompt .= "- buy = LONG position (profit when price goes UP)\n";
        $prompt .= "- sell = SHORT position (profit when price goes DOWN)\n";
        $prompt .= "- hold = No trade (criteria not met or ATR > 8%)\n\n";

        // CRITICAL: Explicitly list coins to analyze (prevents AI hallucination)
        $prompt .= "⚠️ ANALYZE ONLY THESE COINS:\n";
        $prompt .= implode(', ', $coinsToAnalyze) . "\n";
        $prompt .= "DO NOT analyze BTC, ETH, LINK, or any other coins not in this list!\n";

        return $prompt;
    }

    /**
     * Call DeepSeek API directly
     */
    private function callDeepSeekAPI(string $prompt): array
    {
        $client = DeepSeekClient::build(config('deepseek.api_key'));
        $model = config('deepseek.model', 'deepseek-chat');

        $fullPrompt = $this->getSystemPrompt() . "\n\n" . $prompt;
        $response = $client
            ->withModel($model)
            ->setTemperature(0.3)
            ->setMaxTokens(16000) // Increased for 30 coins (~500 tokens per coin)
            ->setResponseFormat('json_object')
            ->query($fullPrompt)
            ->run();

        if (!$response) {
            throw new Exception('Empty DeepSeek response');
        }

        // Clean the response (remove markdown code blocks if present)
        $response = trim($response);
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);

        // Extract JSON from response (handles extra text before/after JSON)
        $response = $this->extractJsonFromResponse($response);

        $cleanResponse = $this->cleanJsonResponse($response);

        // Debug: log cleaned response sample
        Log::debug('🧹 Cleaned JSON response', [
            'length' => strlen($cleanResponse),
            'first_100' => substr($cleanResponse, 0, 100),
            'last_100' => substr($cleanResponse, -100),
            'starts_with_brace' => substr($cleanResponse, 0, 1) === '{',
            'ends_with_brace' => substr($cleanResponse, -1) === '}',
        ]);

        // Warn if JSON might be truncated
        if (substr($cleanResponse, -1) !== '}') {
            Log::warning('⚠️ JSON appears truncated (does not end with })', [
                'last_50_chars' => substr($cleanResponse, -50),
            ]);
        }

        $decision = json_decode($cleanResponse, true);

        // Better error handling with full details
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Write full content to temp file for inspection
            $tempFile = storage_path('logs/failed_json_' . time() . '.txt');
            file_put_contents($tempFile, $cleanResponse);

            Log::error('DeepSeek JSON decode error', [
                'error' => json_last_error_msg(),
                'error_code' => json_last_error(),
                'content_length' => strlen($cleanResponse),
                'content_start' => substr($cleanResponse, 0, 200),
                'content_end' => substr($cleanResponse, -200),
                'raw_sample' => bin2hex(substr($cleanResponse, 0, 50)),
                'temp_file' => $tempFile,
            ]);
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ' (length: ' . strlen($cleanResponse) . ')');
        }

        // Debug: log successful decode
        Log::debug('✅ JSON decoded successfully', [
            'decisions_count' => isset($decision['decisions']) ? count($decision['decisions']) : 0,
            'has_chain_of_thought' => isset($decision['chain_of_thought']),
        ]);

        if (!$decision || !isset($decision['decisions'])) {
            Log::error('Invalid DeepSeek response structure', [
                'has_decision' => !is_null($decision),
                'keys' => is_array($decision) ? array_keys($decision) : 'not an array',
                'content' => substr($response, 0, 500),
            ]);
            throw new Exception('Invalid DeepSeek response format: missing "decisions" key');
        }

        return [
            'decision' => $decision,
            'raw_response' => json_decode($response, true),
            'model' => $model,
        ];
    }

    /**
     * Get system prompt (use custom or default)
     */
    private function getSystemPrompt(): string
    {
        // Check if custom prompt exists in BotSetting
        $customPrompt = BotSetting::get('ai_system_prompt');

        if (!empty($customPrompt)) {
            return $customPrompt;
        }

        // COMPACT system prompt (optimized for 15m/1H scalping strategy)
        // Get RSI thresholds from database
        $rsiLongMin = BotSetting::get('rsi_long_min', 40);
        $rsiLongMax = BotSetting::get('rsi_long_max', 70);
        $rsiShortMin = BotSetting::get('rsi_short_min', 30);
        $rsiShortMax = BotSetting::get('rsi_short_max', 60);

        return "Crypto scalper. 15m entries, 1H trend confirmation. LONG=buy uptrends, SHORT=sell downtrends.

LONG (all 5 must pass):
1. MACD>Signal (15m) - NO MACD>0 requirement
2. RSI7: {$rsiLongMin}-{$rsiLongMax}
3. Price 0-3% above EMA20 (extended from 2%)
4. 1H: EMA20>EMA50 (uptrend confirmation)
5. Volume≥1.1x & 1H ADX>20

SHORT (all 5 must pass):
1. MACD<Signal (15m) - NO MACD<0 requirement
2. RSI7: {$rsiShortMin}-{$rsiShortMax}
3. Price 0-3% below EMA20 (extended from 2%)
4. 1H: EMA20<EMA50 (downtrend confirmation)
5. Volume≥1.1x & 1H ADX>20

HOLD if: ATR>8% OR criteria not met OR confidence <50%

Confidence range: 50-90% (avoid overconfidence >90%)

Max 1-2 trades/cycle. Leverage=2x always.

JSON: {\"decisions\":[{\"symbol\":\"X/USDT\",\"action\":\"buy|sell|hold\",\"reasoning\":\"brief\",\"confidence\":0.70,\"leverage\":2}],\"chain_of_thought\":\"brief\"}";
    }

    /**
     * Call OpenRouter API with rate limit handling
     * @throws ReflectionException
     */
    private function callOpenRouter(string $prompt): array
    {
        $model = trim(config('openrouter.model', 'deepseek/deepseek-chat'));
        $maxRetries = 3;
        $retryDelay = 2; // seconds

        $messages = [
            new MessageData(
                content: $this->getSystemPrompt(),
                role: RoleType::SYSTEM
            ),
            new MessageData(
                content: $prompt,
                role: RoleType::USER
            ),
        ];

        $chatData = new ChatData(
            messages: $messages,
            model: $model,
            response_format: new ResponseFormatData(
                type: 'json_object'
            ),
            max_tokens: 16000, // Increased for 30 coins (~500 tokens per coin)
            temperature: 0.3
        );

        // Retry loop for rate limits
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $responseDTO = LaravelOpenRouter::chatRequest($chatData);
                $responseArray = $responseDTO->toArray();
                $content = $responseArray['choices'][0]['message']['content'] ?? null;
                break; // Success, exit retry loop
            } catch (\Exception $e) {
                $isRateLimit = str_contains($e->getMessage(), 'rate limit') ||
                               str_contains($e->getMessage(), '429') ||
                               str_contains($e->getMessage(), 'Too Many Requests');

                if ($isRateLimit && $attempt < $maxRetries) {
                    $waitTime = $retryDelay * pow(2, $attempt - 1); // Exponential backoff
                    Log::warning("⏳ OpenRouter rate limit hit, retrying in {$waitTime}s (attempt {$attempt}/{$maxRetries})");
                    sleep($waitTime);
                    continue;
                }

                throw $e; // Not rate limit or max retries reached
            }
        }

        if (!$content) {
            throw new Exception('Empty OpenRouter response');
        }

        // Clean the response (remove markdown code blocks if present)
        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        // Extract JSON from response (handles extra text before/after JSON)
        $content = $this->extractJsonFromResponse($content);

        $cleanContent = $this->cleanJsonResponse($content);

        // Debug: log cleaned response sample
        Log::debug('🧹 Cleaned JSON response (OpenRouter)', [
            'length' => strlen($cleanContent),
            'first_100' => substr($cleanContent, 0, 100),
            'last_100' => substr($cleanContent, -100),
            'starts_with_brace' => substr($cleanContent, 0, 1) === '{',
            'ends_with_brace' => substr($cleanContent, -1) === '}',
        ]);

        // Warn if JSON might be truncated
        if (substr($cleanContent, -1) !== '}') {
            Log::warning('⚠️ JSON appears truncated (does not end with })', [
                'last_50_chars' => substr($cleanContent, -50),
            ]);
        }

        $decision = json_decode($cleanContent, true);

        // Better error handling with full details
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Write full content to temp file for inspection
            $tempFile = storage_path('logs/failed_json_' . time() . '.txt');
            file_put_contents($tempFile, $cleanContent);

            Log::error('JSON decode error', [
                'error' => json_last_error_msg(),
                'error_code' => json_last_error(),
                'content_length' => strlen($cleanContent),
                'content_start' => substr($cleanContent, 0, 200),
                'content_end' => substr($cleanContent, -200),
                'raw_sample' => bin2hex(substr($cleanContent, 0, 50)),
                'temp_file' => $tempFile,
            ]);
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ' (length: ' . strlen($cleanContent) . ')');
        }

        // Debug: log successful decode
        Log::debug('✅ JSON decoded successfully (OpenRouter)', [
            'decisions_count' => isset($decision['decisions']) ? count($decision['decisions']) : 0,
            'has_chain_of_thought' => isset($decision['chain_of_thought']),
        ]);

        if (!$decision || !isset($decision['decisions'])) {
            Log::error('Invalid response structure', [
                'has_decision' => !is_null($decision),
                'keys' => is_array($decision) ? array_keys($decision) : 'not an array',
                'content' => substr($content, 0, 500),
            ]);
            throw new Exception('Invalid OpenRouter response format: missing "decisions" key');
        }

        return [
            'decision' => $decision,
            'raw_response' => $responseDTO->toArray(),
            'model' => $model,
        ];
    }

    /**
     * Log AI call details
     */
    private function logAiCall(string $provider, string $model, string $prompt, array $response, array $decision): void
    {
        AiLog::create([
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'response' => json_encode($response),
            'decision' => $decision,
        ]);
    }

    /**
     * Show which coins are being sent to AI with their key metrics
     */
    private function showCoinsBeingSent(array $coins): void
    {
        if (app()->runningInConsole()) {
            echo "📤 Sending " . count($coins) . " coins to AI:\n";

            $noDataCount = 0;

            foreach ($coins as $symbol) {
                // Get latest market data for this coin
                $latest3m = \App\Models\MarketData::getLatest($symbol, '3m');
                $latest4h = \App\Models\MarketData::getLatest($symbol, '4h');

                if ($latest3m && $latest4h) {
                    $rsi = $latest3m->rsi7 ?? 0;
                    $macd = $latest3m->macd ?? 0;
                    $signal = $latest3m->macd_signal ?? 0;
                    $adx = $latest4h->indicators["adx"] ?? 0;
                    $volumeRatio = $latest3m->volume_ratio ?? 0;
                    $atr = $latest3m->atr3 ?? 0;

                    // Check if valid data
                    if ($rsi == 0 && $adx == 0) {
                        echo "  ⚠️ {$symbol} - NO MARKET DATA (will be filtered)\n";
                        $noDataCount++;
                    } else {
                        // Determine trend
                        $trend = $macd > $signal ? "📈" : "📉";
                        $strength = $adx > 25 ? "💪" : ($adx > 20 ? "👍" : "😴");

                        echo "  {$trend} {$symbol} - RSI:" . number_format($rsi, 0) . " MACD:" . number_format($macd, 4) . " ADX:" . number_format($adx, 0) . " Vol:" . number_format($volumeRatio, 1) . "x ATR:" . number_format($atr, 1) . "% {$strength}\n";
                    }
                } else {
                    echo "  ⚪ {$symbol} - No data in database\n";
                    $noDataCount++;
                }
            }

            if ($noDataCount > 0) {
                echo "\n⚠️ {$noDataCount} coin(s) filtered due to missing market data\n";
            }

            echo "\n";
        }
    }

    /**
     * Show prompt details being sent to AI
     */
    private function showPromptDetails(string $prompt): void
    {
        if (app()->runningInConsole()) {
            echo "📝 AI Prompt Preview:\n";
            echo "Length: " . number_format(strlen($prompt)) . " characters\n";
            
            // Show first 500 characters of prompt
            $preview = substr($prompt, 0, 500);
            echo "Preview: " . $preview . "...\n\n";
            
            // Show last part (instructions)
            $lines = explode("\n", $prompt);
            $lastLines = array_slice($lines, -10);
            echo "Instructions:\n";
            foreach ($lastLines as $line) {
                echo "  " . $line . "\n";
            }
            echo "\n";
        }
    }

    /**
     * Show AI response details
     */
    private function showAIResponse(array $decision, array $rawResponse): void
    {
        if (app()->runningInConsole()) {
            echo "🤖 AI Response Analysis:\n";
            
            // Show chain of thought if available
            if (isset($decision['chain_of_thought'])) {
                echo "💭 AI Thinking: " . substr($decision['chain_of_thought'], 0, 200) . "...\n";
            }
            
            // Show each decision with reasoning
            if (isset($decision['decisions'])) {
                echo "📊 Individual Decisions:\n";
                foreach ($decision['decisions'] as $coinDecision) {
                    $symbol = $coinDecision['symbol'] ?? 'Unknown';
                    $action = $coinDecision['action'] ?? 'hold';
                    $confidence = $coinDecision['confidence'] ?? 0;
                    $reasoning = $coinDecision['reasoning'] ?? 'No reasoning provided';
                    
                    $actionIcon = match($action) {
                        'buy' => '📈 BUY',
                        'sell' => '📉 SELL', 
                        'hold' => '⏸️ HOLD',
                        default => '❓ ' . strtoupper($action)
                    };
                    
                    echo "  {$actionIcon} {$symbol} (confidence: " . number_format($confidence, 2) . ")\n";
                    echo "    💡 Reasoning: " . substr($reasoning, 0, 100) . "...\n";
                }
            }
            
            echo "\n";
        }
    }

    /**
     * Extract JSON object from response text (handles extra text before/after)
     */
    private function extractJsonFromResponse(string $response): string
    {
        // Find first { and last }
        $firstBrace = strpos($response, '{');
        $lastBrace = strrpos($response, '}');

        if ($firstBrace === false || $lastBrace === false || $firstBrace >= $lastBrace) {
            // No valid JSON structure found, return as-is
            return $response;
        }

        // Extract the JSON portion
        $json = substr($response, $firstBrace, $lastBrace - $firstBrace + 1);

        // Verify balanced braces
        $braceCount = 0;
        $length = strlen($json);
        $inString = false;
        $escapeNext = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if (!$inString) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                }
            }
        }

        // If braces are balanced, return extracted JSON
        if ($braceCount === 0) {
            return $json;
        }

        // If not balanced, return original response
        return $response;
    }

    /**
     * Comprehensive JSON response cleaning
     */
    private function cleanJsonResponse(string $response): string
    {
        // First ensure valid UTF-8 encoding, replacing invalid sequences
        if (!mb_check_encoding($response, 'UTF-8')) {
            $response = mb_convert_encoding($response, 'UTF-8', 'UTF-8');
        }

        // Remove ONLY actual control characters (preserve UTF-8 multibyte chars)
        // \x00-\x08: NULL and control chars
        // \x0B: vertical tab
        // \x0C: form feed
        // \x0E-\x1F: more control chars
        // \x7F: DELETE
        // We keep \x09 (tab), \x0A (LF), \x0D (CR) temporarily for normalization
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $response);

        // Normalize line endings
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        // Normalize horizontal whitespace (spaces and tabs) but preserve newlines
        $clean = preg_replace('/[ \t]+/', ' ', $clean);

        // Remove excessive newlines (more than 2 consecutive)
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean);

        return trim($clean);
    }

}
