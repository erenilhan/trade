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
     * Make multi-coin trading decision
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

            // Build advanced multi-coin prompt
            $prompt = $this->buildMultiCoinPrompt($account, $allMarketData);

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

            return $decision;

        } catch (Exception $e) {
            Log::error('❌ Multi-Coin AI Error', ['error' => $e->getMessage()]);

            return [
                'decisions' => [],
                'reasoning' => 'AI error: ' . $e->getMessage(),
            ];
        }
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

            // Skip BTC, ETH, and BNB if cash is low
            if ($skipExpensiveCoins && in_array($symbol, ['BTC/USDT', 'ETH/USDT', 'BNB/USDT'])) {
                continue;
            }

            // PRE-FILTERING: Hybrid filtering (time-aware + volume-aware)
            if ($enablePreFiltering) {
                $data3m = $data['3m'];
                $data4h = $data['4h'];

                // Dynamic volume threshold based on trading hours
                $currentHour = now()->hour; // UTC hour
                $isUSHours = $currentHour >= 13 && $currentHour <= 22; // US trading hours (13:00-22:00 UTC)

                // 2025 OPTIMIZATION: More balanced volume filters
                // US hours: moderate (1.1x), Off-hours: stricter (1.2x)
                $minVolumeRatio = $isUSHours ? 1.1 : 1.2;

                Log::info("⏰ Current hour: {$currentHour} UTC, US Hours: " . ($isUSHours ? 'YES' : 'NO') . ", Min Volume: {$minVolumeRatio}x");

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

                // LONG scoring
                if ($is4hUptrend) {
                    if (($data3m['macd'] ?? 0) > ($data3m['macd_signal'] ?? 0)) $longScore++;
                    if (($data3m['rsi7'] ?? 0) >= 40 && ($data3m['rsi7'] ?? 0) <= 75) $longScore++;
                    if ($data3m['price'] >= $data3m['ema20'] * 0.98 && $data3m['price'] <= $data3m['ema20'] * 1.05) $longScore++;
                    if ($adxOk) $longScore++;
                    $longScore++; // Volume already checked above
                }

                // SHORT scoring
                if ($is4hDowntrend) {
                    if (($data3m['macd'] ?? 0) < ($data3m['macd_signal'] ?? 0)) $shortScore++;
                    if (($data3m['rsi7'] ?? 0) >= 25 && ($data3m['rsi7'] ?? 0) <= 60) $shortScore++;
                    if ($data3m['price'] <= $data3m['ema20'] * 1.02 && $data3m['price'] >= $data3m['ema20'] * 0.95) $shortScore++;
                    if ($adxOk) $shortScore++;
                    $shortScore++; // Volume already checked above
                }

                // Need at least 3/5 score to send to AI
                if ($longScore >= 3) {
                    Log::info("✅ {$symbol} passed pre-filter (potential LONG, score {$longScore}/5, volume {$volumeRatio}x)");
                } else if ($shortScore >= 3) {
                    Log::info("✅ {$symbol} passed pre-filter (potential SHORT, score {$shortScore}/5, volume {$volumeRatio}x)");
                } else {
                    Log::info("⏭️ Pre-filtered {$symbol} - Low score (LONG {$longScore}/5, SHORT {$shortScore}/5)");
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

            // RSI direction check
            $rsi = $data3m['rsi7'];
            if ($rsi >= 45 && $rsi <= 72) {
                $prompt .= "RSI STATUS: ✅ IN LONG RANGE (45-72, current: {$rsi}) - healthy for LONG\n";
            } elseif ($rsi >= 28 && $rsi <= 55) {
                $prompt .= "RSI STATUS: ✅ IN SHORT RANGE (28-55, current: {$rsi}) - healthy for SHORT\n";
            } elseif ($rsi < 28) {
                $prompt .= "RSI STATUS: ⚠️ OVERSOLD (< 28, current: {$rsi}) - too weak, avoid SHORT (bounce risk)\n";
            } else {
                $prompt .= "RSI STATUS: ⚠️ OVERBOUGHT (> 72, current: {$rsi}) - too strong, avoid LONG (pullback risk)\n";
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

            $prompt .= "Intraday series (3-minute intervals, last 10 candles = 30min context):\n";
            $prompt .= "Prices: [" . implode(', ', array_map(fn($p) => number_format($p, 2), array_slice($data3m['price_series'], -10))) . "]\n";
            $prompt .= "EMA20: [" . implode(', ', array_map(fn($e) => number_format($e, 2), array_slice($data3m['indicators']['ema_series'], -10))) . "]\n";
            $prompt .= "MACD: [" . implode(', ', array_map(fn($m) => number_format($m, 3), array_slice($data3m['indicators']['macd_series'], -10))) . "]\n";
            $prompt .= "MACD Signal: [" . implode(', ', array_map(fn($s) => number_format($s, 3), array_slice($data3m['indicators']['signal_series'], -10))) . "]\n";
            $prompt .= "RSI7: [" . implode(', ', array_map(fn($r) => number_format($r, 1), array_slice($data3m['indicators']['rsi7_series'], -10))) . "]\n\n";

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
        $prompt .= '{"decisions":[{"symbol":"BTC/USDT","action":"buy|sell|hold","reasoning":"...","confidence":0.70,"leverage":2}],"chain_of_thought":"..."}\n';
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

        $decision = json_decode($response, true);

        if (!$decision || !isset($decision['decisions'])) {
            throw new Exception('Invalid DeepSeek response format: ' . $response);
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

        // SIMPLIFIED strategy - back to basics that worked (KISS principle)
        return "You are a crypto day trader. Trade LONG and SHORT based on simple, clear signals.

⚠️ STRATEGY: Trade with the trend. LONG in uptrends, SHORT in downtrends. Simple and effective.

⚠️ IMPORTANT DIRECTION LOGIC:
- When you see '**LONG signal**' or 'Favor LONG positions' → Check the 5 LONG criteria
- When you see '**SHORT signal**' or 'Favor SHORT positions' → Check the 5 SHORT criteria
- When you see 'BEARISH DOWNTREND' → This is GOOD for SHORT (not bad!)
- When you see 'BULLISH UPTREND' → This is GOOD for LONG (not bad!)
- DO NOT only check LONG criteria - check BOTH directions based on market trend!

LONG ENTRY (5 simple rules - ALL must be true):
1. MACD > MACD_Signal AND MACD > 0 (bullish momentum)
2. RSI(7) between 45-72 (healthy momentum, not overbought)
3. Price 0-2% above EMA20 (riding uptrend)
4. 4H trend: EMA20 > EMA50 AND ADX > 20 (strong uptrend on higher timeframe)
5. Volume Ratio ≥ 1.0x (minimum liquidity - coins below this already filtered out)

SHORT ENTRY (5 simple rules - ALL must be true):
1. MACD < MACD_Signal AND MACD < 0 (bearish momentum)
2. RSI(7) between 25-60 (healthy downward momentum, not oversold)
3. Price 0-2% below EMA20 (riding downtrend)
4. 4H trend: EMA20 < EMA50 AND ADX > 20 (strong downtrend on higher timeframe)
5. Volume Ratio ≥ 1.0x (minimum liquidity - coins below this already filtered out)

VOLUME QUALITY TIERS (impacts risk):
- Volume ≥ 1.5x: EXCELLENT - High liquidity, low slippage risk, full confidence
- Volume 1.2-1.5x: GOOD - Normal liquidity, standard risk
- Volume 1.0-1.2x: ACCEPTABLE - Moderate liquidity, slightly elevated risk
- Volume < 1.0x: WEAK - Already filtered out by system

HOLD IF (any of these):
- Criteria not met for LONG or SHORT
- ATR > 8% (too volatile - CRITICAL SAFETY CHECK)
- Volume marked as WEAK (< 1.0x - should already be filtered)
- AI Confidence < 60%
- 4H ADX < 20 (sideways, no clear trend)
- Volume 1.0-1.2x AND other signals not strong (prefer higher volume)

RISK MANAGEMENT:
- Maximum 1-2 new positions per cycle (LONG or SHORT)
- Skip if 4+ positions already open
- Mix LONG and SHORT when possible (hedge risk)
- Use 2x leverage for all trades (proven safe and effective)

OUTPUT FORMAT:
- Return JSON: {\"decisions\":[{\"symbol\":\"BTC/USDT\",\"action\":\"buy|sell|hold\",\"reasoning\":\"...\",\"confidence\":0.70,\"leverage\":2}],\"chain_of_thought\":\"...\"}
- Actions: 'buy' (LONG), 'sell' (SHORT), 'hold'
- Always set leverage = 2
- DO NOT set entry_price, target_price, stop_price, or invalidation (system calculates automatically)

IMPORTANT:
- Your job: Decide action (buy/sell/hold) based on the 5 rules
- System's job: Calculate entry, target, stop prices automatically
- Exits handled by trailing stops (L2 at +6%, L3 at +9%, L4 at +12%)
- Simple is better - 5 clear criteria beats 40 confusing rules
- Trade WITH the 4H trend, time entry on 3m chart
- Volume confirmation critical - no volume = no trade
- Historical data: 60-74% confidence performs best (avoid 80%+ overconfidence)
- If ATR > 8%, ALWAYS return 'hold' regardless of other signals";
    }

    /**
     * Call OpenRouter API
     * @throws ReflectionException
     */
    private function callOpenRouter(string $prompt): array
    {
        $model = config('openrouter.model', 'deepseek/deepseek-chat');

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

        $responseDTO = LaravelOpenRouter::chatRequest($chatData);
        $content = $responseDTO->choices[0]['message']['content'] ?? null;

        if (!$content) {
            throw new Exception('Empty OpenRouter response');
        }

        $decision = json_decode($content, true);
        if (!$decision || !isset($decision['decisions'])) {
            throw new Exception('Invalid OpenRouter response format: ' . $content);
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

}
