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

    public function __construct(MarketDataService $marketData)
    {
        set_time_limit(9999);
        ini_set('memory_limit', '99999M');
        ini_set('max_execution_time', '99999');
        $this->marketData = $marketData;

        // Get AI provider from BotSetting or .env
        $this->provider = BotSetting::get('ai_provider')
            ?? config('app.ai_provider', 'openrouter');
    }

    /**
     * Make multi-coin trading decision (with optional batch processing)
     */
    public function makeDecision(array $account): array
    {
        try {
            // Collect all market data
            $allMarketData = $this->marketData->collectAllMarketData();

            // Check if market is too quiet (low volatility = skip AI)
            if ($this->marketData->isMarketTooQuiet($allMarketData)) {
                Log::info("🔇 Skipping AI call - market volatility too low");
                return [
                    'decisions' => [],
                    'reasoning' => 'Market volatility too low - no trading opportunities',
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
                $data3m = $data['3m'];
                $data4h = $data['4h'];

                // Dynamic volume threshold based on global trading hours (UTC)
                $currentHour = now()->hour; // UTC hour

                // Regional liquidity zones (optimized for crypto markets)
                if ($currentHour >= 13 && $currentHour <= 22) {
                    // US hours (13:00-22:00 UTC = 8am-5pm EST)
                    $minVolumeRatio = 0.9;
                    $region = 'US';
                } elseif ($currentHour >= 1 && $currentHour <= 9) {
                    // Asia hours (01:00-09:00 UTC = 9am-5pm Asia)
                    $minVolumeRatio = 0.8;
                    $region = 'Asia';
                } elseif ($currentHour >= 7 && $currentHour <= 16) {
                    // Europe hours (07:00-16:00 UTC = 8am-5pm CET)
                    $minVolumeRatio = 0.95;
                    $region = 'Europe';
                } else {
                    // Off-peak hours (low liquidity)
                    $minVolumeRatio = 1.0;
                    $region = 'Off-peak';
                }

                Log::info("⏰ Current: {$currentHour} UTC, Region: {$region}, Min Volume: {$minVolumeRatio}x");

                // Determine 4H trend direction first
                $is4hUptrend = ($data4h['ema20'] ?? 0) > ($data4h['ema50'] ?? 0);
                $is4hDowntrend = ($data4h['ema20'] ?? 0) < ($data4h['ema50'] ?? 0);

                // 2025 OPTIMIZATION: Stronger ADX requirement (20+)
                $adxOk = ($data4h['adx'] ?? 0) > 20; // Back to 20 for consistency

                // If 4H ADX too weak (< 20), skip - NO weak trends
                if (($data4h['adx'] ?? 0) < 20) {
                    Log::info("⏭️ Pre-filtered {$symbol} - 4H too weak (ADX < 20)");
                    continue;
                }

                // Check volume first (critical for liquidity)
                $volumeRatio = $data3m['volume_ratio'] ?? 0;
                if ($volumeRatio < $minVolumeRatio) {
                    Log::info("⏭️ Pre-filtered {$symbol} - Volume {$volumeRatio}x < {$minVolumeRatio}x minimum");
                    continue;
                }

                // Count how many criteria are met (need 3/5)
                $longScore = 0;
                $shortScore = 0;

                // LONG scoring (volume NOT counted in score)
                if ($is4hUptrend) {
                    if (($data3m['macd'] ?? 0) > ($data3m['macd_signal'] ?? 0)) $longScore++;
                    if (($data3m['rsi7'] ?? 0) >= 50 && ($data3m['rsi7'] ?? 0) <= 70) $longScore++; // Tightened
                    if ($data3m['price'] >= $data3m['ema20'] * 0.98 && $data3m['price'] <= $data3m['ema20'] * 1.05) $longScore++;
                    if ($adxOk) $longScore++;
                }

                // SHORT scoring (volume NOT counted in score)
                if ($is4hDowntrend) {
                    if (($data3m['macd'] ?? 0) < ($data3m['macd_signal'] ?? 0)) $shortScore++;
                    if (($data3m['rsi7'] ?? 0) >= 30 && ($data3m['rsi7'] ?? 0) <= 55) $shortScore++; // Tightened
                    if ($data3m['price'] <= $data3m['ema20'] * 1.02 && $data3m['price'] >= $data3m['ema20'] * 0.95) $shortScore++;
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

            $data3m = $data['3m'];
            $data4h = $data['4h'];

            $cleanSymbol = str_replace('/USDT', '', $symbol);

            $prompt .= "ALL {$cleanSymbol} DATA\n";
            $macdHistogram = ($data3m['macd_histogram'] ?? ($data3m['macd'] - ($data3m['macd_signal'] ?? 0)));
            $macdHistogramRising = ($data3m['macd_histogram_rising'] ?? false);
            
            $prompt .= sprintf(
                "current_price = %.2f, current_ema20 = %.2f, current_macd = %.3f, macd_signal = %.3f, macd_histogram = %.3f, current_rsi (7 period) = %.2f\n",
                $data3m['price'],
                $data3m['ema20'],
                $data3m['macd'],
                $data3m['macd_signal'] ?? 0,
                $macdHistogram,
                $data3m['rsi7']
            );

            // RSI direction check (tightened ranges for safety)
            $rsi = $data3m['rsi7'];
            if ($rsi >= 50 && $rsi <= 70) {
                $prompt .= "RSI STATUS: ✅ IN LONG RANGE (50-70, current: {$rsi}) - healthy for LONG\n";
            } elseif ($rsi >= 30 && $rsi <= 55) {
                $prompt .= "RSI STATUS: ✅ IN SHORT RANGE (30-55, current: {$rsi}) - healthy for SHORT\n";
            } elseif ($rsi < 30) {
                $prompt .= "RSI STATUS: ⚠️ OVERSOLD (< 30, current: {$rsi}) - too weak, avoid SHORT (bounce risk)\n";
            } else {
                $prompt .= "RSI STATUS: ⚠️ OVERBOUGHT (> 70, current: {$rsi}) - too strong, avoid LONG (pullback risk)\n";
            }

            // Price position relative to EMA20
            $priceVsEma = (($data3m['price'] - $data3m['ema20']) / $data3m['ema20']) * 100;
            if ($priceVsEma >= 0 && $priceVsEma <= 2) {
                $prompt .= sprintf("PRICE POSITION: ✅ %.2f%% above EMA20 - good for LONG (riding uptrend)\n", $priceVsEma);
            } elseif ($priceVsEma < 0 && $priceVsEma >= -2) {
                $prompt .= sprintf("PRICE POSITION: ✅ %.2f%% below EMA20 - good for SHORT (riding downtrend)\n", abs($priceVsEma));
            } elseif ($priceVsEma > 2) {
                $prompt .= sprintf("PRICE POSITION: ⚠️ %.2f%% above EMA20 - too extended for LONG\n", $priceVsEma);
            } else {
                $prompt .= sprintf("PRICE POSITION: ⚠️ %.2f%% below EMA20 - too extended for SHORT\n", abs($priceVsEma));
            }
            $prompt .= "\n";

            // Direction-aware MACD check (helps AI know which criteria to evaluate)
            $macdBullish = $data3m['macd'] > ($data3m['macd_signal'] ?? 0);
            $macdPositive = $data3m['macd'] > 0;

            if ($macdBullish && $macdPositive) {
                $prompt .= "MACD STATUS: ✅ BULLISH (MACD > Signal AND > 0) - **LONG signal** - evaluate LONG criteria\n\n";
            } elseif (!$macdBullish && $data3m['macd'] < 0) {
                $prompt .= "MACD STATUS: ✅ BEARISH (MACD < Signal AND < 0) - **SHORT signal** - evaluate SHORT criteria\n\n";
            } else {
                $prompt .= "MACD STATUS: ⚠️ NEUTRAL (mixed signals) - HOLD or low confidence\n\n";
            }

            // 2025 NEW: Bollinger Bands analysis
            $bbUpper = $data3m['indicators']['bb_upper'] ?? 0;
            $bbLower = $data3m['indicators']['bb_lower'] ?? 0;
            $bbMiddle = $data3m['indicators']['bb_middle'] ?? 0;
            $percentB = $data3m['indicators']['bb_percent_b'] ?? 0.5;

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
            $supertrendBullish = $data3m['indicators']['supertrend_is_bullish'] ?? false;
            $supertrendBearish = $data3m['indicators']['supertrend_is_bearish'] ?? false;

            if ($supertrendBullish) {
                $prompt .= "SUPERTREND: ✅ BULLISH TREND - Strong confirmation for LONG positions\n\n";
            } elseif ($supertrendBearish) {
                $prompt .= "SUPERTREND: ✅ BEARISH TREND - Strong confirmation for SHORT positions\n\n";
            } else {
                $prompt .= "SUPERTREND: ⚠️ NEUTRAL - Trend unclear\n\n";
            }

            // Core volume indicator with quality assessment
            $volumeRatio = $data3m['volume_ratio'] ?? 1.0;
            if ($volumeRatio >= 1.5) {
                $volumeStatus = '✅ EXCELLENT (high liquidity, full position OK)';
            } elseif ($volumeRatio >= 1.2) {
                $volumeStatus = '✅ GOOD (normal liquidity, standard position)';
            } elseif ($volumeRatio >= 1.0) {
                $volumeStatus = '⚠️ ACCEPTABLE (moderate liquidity, prefer smaller position)';
            } else {
                $volumeStatus = '❌ WEAK (low liquidity, high risk - HOLD recommended)';
            }

            $prompt .= sprintf(
                "Volume Ratio (current/20MA): %.2fx %s\n\n",
                $volumeRatio,
                $volumeStatus
            );

            $prompt .= "Funding Rate: " . number_format($data3m['funding_rate'], 10) . "\n";
            $prompt .= "Open Interest: Latest: " . number_format($data3m['open_interest'], 2) . "\n\n";

            // Shortened series (5 candles = 15min context, saves tokens)
            $prompt .= "Recent 3m data (last 5 candles):\n";
            $prompt .= "Price: [" . implode(',', array_map(fn($p) => number_format($p, 2), array_slice($data3m['price_series'], -5))) . "]\n";
            $prompt .= "EMA20: [" . implode(',', array_map(fn($e) => number_format($e, 2), array_slice($data3m['indicators']['ema_series'], -5))) . "]\n";
            $prompt .= "MACD: [" . implode(',', array_map(fn($m) => number_format($m, 3), array_slice($data3m['indicators']['macd_series'], -5))) . "]\n";
            $prompt .= "RSI7: [" . implode(',', array_map(fn($r) => number_format($r, 1), array_slice($data3m['indicators']['rsi7_series'], -5))) . "]\n\n";

            // Volume info
            $currentVolume = $data3m['volume'] ?? 0;
            $prompt .= sprintf(
                "Volume: current=%.2f\n\n",
                $currentVolume
            );

            $prompt .= sprintf(
                "4H: EMA20=%.2f, EMA50=%.2f, ATR=%.2f, ADX(14)=%.2f (Moderate trend if >20, Strong if >25)\n",
                $data4h['ema20'],
                $data4h['ema50'],
                $data4h['atr14'],
                $data4h['adx'] ?? 0
            );

            // ATR volatility warning (critical safety check)
            $atrPercent = ($data4h['atr14'] / $data3m['price']) * 100;
            $atrWarning = $atrPercent > 8 ? '⚠️ TOO VOLATILE → HOLD' : '✅ OK';

            // Direction-aware 4H trend check (critical for determining LONG vs SHORT)
            $is4hUptrend = $data4h['ema20'] > ($data4h['ema50'] * 0.999);
            $adxStrong = ($data4h['adx'] ?? 0) > 20; // Back to 20 (25 was too strict)

            if ($is4hUptrend && $adxStrong) {
                $prompt .= "4H TREND: ✅ STRONG BULLISH UPTREND (EMA20 > EMA50, ADX > 20) - **Favor LONG positions**\n";
            } elseif (!$is4hUptrend && $adxStrong) {
                $prompt .= "4H TREND: ✅ STRONG BEARISH DOWNTREND (EMA20 < EMA50, ADX > 20) - **Favor SHORT positions**\n";
            } elseif ($is4hUptrend && !$adxStrong) {
                $prompt .= "4H TREND: ⚠️ WEAK UPTREND (EMA20 > EMA50, ADX < 20) - Too weak, prefer HOLD\n";
            } else {
                $prompt .= "4H TREND: ⚠️ WEAK DOWNTREND (EMA20 < EMA50, ADX < 20) - Too weak, prefer HOLD\n";
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
        $prompt .= "- hold = No trade (criteria not met or ATR > 8%)\n";

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
            ->setMaxTokens(2000)
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

        $decision = json_decode($response, true);

        // Better error handling with full details
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('DeepSeek JSON decode error', [
                'error' => json_last_error_msg(),
                'content_length' => strlen($response),
                'content_start' => substr($response, 0, 200),
                'content_end' => substr($response, -200),
            ]);
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ' (length: ' . strlen($response) . ')');
        }

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

        // COMPACT system prompt (optimized for free models with token limits)
        return "Crypto trader. LONG=buy uptrends, SHORT=sell downtrends.

LONG (all 5 must pass):
1. MACD>Signal & MACD>0
2. RSI7: 50-70 (tightened for safety)
3. Price 0-2% above EMA20
4. 4H: EMA20>EMA50 & ADX>20
5. Volume≥1.0x

SHORT (all 5 must pass):
1. MACD<Signal & MACD<0
2. RSI7: 30-55 (tightened for safety)
3. Price 0-2% below EMA20
4. 4H: EMA20<EMA50 & ADX>20
5. Volume≥1.0x

HOLD if: ATR>8% OR criteria not met OR low confidence (<60%)

Max 1-2 trades/cycle. Leverage=2x always.

JSON: {\"decisions\":[{\"symbol\":\"X/USDT\",\"action\":\"buy|sell|hold\",\"reasoning\":\"brief\",\"confidence\":0.70,\"leverage\":2}],\"chain_of_thought\":\"brief\"}";
    }

    /**
     * Call OpenRouter API with rate limit handling
     * @throws ReflectionException
     */
    private function callOpenRouter(string $prompt): array
    {
        $model = config('openrouter.model', 'deepseek/deepseek-chat');
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
            max_tokens: 2000,
            temperature: 0.3
        );

        // Retry loop for rate limits
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $responseDTO = LaravelOpenRouter::chatRequest($chatData);
                $content = $responseDTO->choices[0]['message']['content'] ?? null;
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

        if (!isset($content)) {
            $content = $responseDTO->choices[0]['message']['content'] ?? null;
        }

        if (!$content) {
            throw new Exception('Empty OpenRouter response');
        }

        // Clean the response (remove markdown code blocks if present)
        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $decision = json_decode($content, true);

        // Better error handling with full details
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON decode error', [
                'error' => json_last_error_msg(),
                'content_length' => strlen($content),
                'content_start' => substr($content, 0, 200),
                'content_end' => substr($content, -200),
            ]);
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ' (length: ' . strlen($content) . ')');
        }

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
            
            foreach ($coins as $symbol) {
                // Get latest market data for this coin
                $latest3m = \App\Models\MarketData::getLatest($symbol, '3m');
                $latest4h = \App\Models\MarketData::getLatest($symbol, '4h');
                
                if ($latest3m && $latest4h) {
                    $rsi = $latest3m->rsi_7 ?? 0;
                    $macd = $latest3m->macd ?? 0;
                    $signal = $latest3m->macd_signal ?? 0;
                    $adx = $latest4h->adx ?? 0;
                    $volumeRatio = $latest3m->volume_ratio ?? 0;
                    $atr = $latest3m->atr_3 ?? 0;
                    
                    // Determine trend
                    $trend = $macd > $signal ? "📈" : "📉";
                    $strength = $adx > 25 ? "💪" : ($adx > 20 ? "👍" : "😴");
                    
                    echo "  {$trend} {$symbol} - RSI:" . number_format($rsi, 0) . " MACD:" . number_format($macd, 4) . " ADX:" . number_format($adx, 0) . " Vol:" . number_format($volumeRatio, 1) . "x ATR:" . number_format($atr, 1) . "% {$strength}\n";
                } else {
                    echo "  ⚪ {$symbol} - No data\n";
                }
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

}
