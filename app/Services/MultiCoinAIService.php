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

            // PRE-FILTERING: Check if coin has potential setup (save AI tokens)
            if ($enablePreFiltering) {
                $data3m = $data['3m'];
                $data4h = $data['4h'];

                // Quick basic checks - if ALL fail, skip this coin
                $priceAboveEma = $data3m['price'] > ($data3m['ema20'] * 1.003); // Price > EMA20 by 0.3%
                $macdPositive = ($data3m['macd'] ?? 0) > ($data3m['macd_signal'] ?? 0);
                $rsiOk = ($data3m['rsi7'] ?? 0) >= 35 && ($data3m['rsi7'] ?? 0) <= 75;
                $trendOk = ($data4h['ema20'] ?? 0) > ($data4h['ema50'] ?? 0) * 0.999;

                $passedChecks = 0;
                if ($priceAboveEma) $passedChecks++;
                if ($macdPositive) $passedChecks++;
                if ($rsiOk) $passedChecks++;
                if ($trendOk) $passedChecks++;

                // Need at least 2 out of 4 criteria to send to AI
                if ($passedChecks < 2) {
                    Log::info("⏭️ Pre-filtered {$symbol} - only {$passedChecks}/4 criteria met");
                    continue;
                }

                Log::info("✅ {$symbol} passed pre-filter ({$passedChecks}/4 criteria)");
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

            $prompt .= sprintf(
                "MACD > Signal? %s, MACD Histogram Rising? %s, MACD > (price * 0.0001)? %s\n\n",
                ($data3m['macd'] > ($data3m['macd_signal'] ?? 0)) ? 'YES' : 'NO',
                $macdHistogramRising ? 'YES ✅' : 'NO',
                ($data3m['macd'] > ($data3m['price'] * 0.0001)) ? 'YES' : 'NO'
            );

            // Add new indicators
            $prompt .= sprintf(
                "🆕 VOLUME & VOLATILITY:\n" .
                "Volume Ratio (current/20MA): %.2fx %s\n" .
                "Bollinger Bands: %%B=%.2f (0=lower, 0.5=middle, 1=upper), Width=%.2f%%\n" .
                "  → Price=%.2f, BB_Upper=%.2f, BB_Middle=%.2f, BB_Lower=%.2f\n" .
                "  → Position: %s\n" .
                "Stochastic RSI: %%K=%.1f, %%D=%.1f %s\n\n",
                $data3m['volume_ratio'] ?? 1.0,
                ($data3m['volume_ratio'] ?? 1.0) > 1.5 ? '✅ STRONG' : (($data3m['volume_ratio'] ?? 1.0) > 1.1 ? '⚠️ ACCEPTABLE' : '❌ WEAK'),
                $data3m['bb_percent_b'] ?? 0.5,
                $data3m['bb_width'] ?? 0,
                $data3m['price'],
                $data3m['bb_upper'] ?? $data3m['price'],
                $data3m['bb_middle'] ?? $data3m['price'],
                $data3m['bb_lower'] ?? $data3m['price'],
                ($data3m['bb_percent_b'] ?? 0.5) > 0.8 ? 'OVERBOUGHT' : (($data3m['bb_percent_b'] ?? 0.5) < 0.3 ? 'OVERSOLD' : 'NEUTRAL'),
                $data3m['stoch_rsi_k'] ?? 50,
                $data3m['stoch_rsi_d'] ?? 50,
                ($data3m['stoch_rsi_k'] ?? 50) > 80 ? '⚠️ OVERBOUGHT' : (($data3m['stoch_rsi_k'] ?? 50) < 20 ? '⚠️ OVERSOLD' : '✅ OK')
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

            $prompt .= sprintf(
                "4H Trend: EMA20 > EMA50*0.999? %s, ADX > 20? %s\n\n",
                ($data4h['ema20'] > ($data4h['ema50'] * 0.999)) ? 'YES (bullish)' : 'NO (bearish)',
                (($data4h['adx'] ?? 0) > 20) ? 'YES (moderate+)' : 'NO (weak)'
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
        $prompt .= "Analyze ONLY the coins shown above (coins without open positions). Decide BUY or HOLD for each based on technical indicators.\n";
        $prompt .= "Always include: action, reasoning, confidence (0-1), entry_price, target_price, stop_price, invalidation, leverage.\n\n";

        $prompt .= "LEVERAGE SELECTION:\n";
        $prompt .= "Choose leverage (2-3x) based on:\n";
        $prompt .= "- Signal strength: Strong signals = higher leverage (up to 3x MAX)\n";
        $prompt .= "- Volatility: High ATR = lower leverage (2x), Low ATR = moderate leverage (3x)\n";
        $prompt .= "- Trend strength: Strong ADX (>25) = can use 3x, Weak ADX = stick to 2x\n";
        $prompt .= "- Risk level: Conservative = 2x, Moderate = 2-3x, Aggressive = 3x (MAX)\n";
        $prompt .= "- IMPORTANT: Maximum leverage is 3x! Historical data shows 5x+ is net negative.\n";
        $prompt .= "Example: Strong setup + low volatility + ADX>30 + high confidence = 3x leverage\n";
        $prompt .= "Example: Weak signal + high volatility + low ADX = 2x leverage\n\n";

        $prompt .= "RESPONSE FORMAT (strict JSON):\n";
        $prompt .= '{"decisions":[{"symbol":"BTC/USDT","action":"hold|buy|sell","reasoning":"...","confidence":0.75,"leverage":5,"entry_price":null,"target_price":null,"stop_price":null,"invalidation":"..."}],"chain_of_thought":"..."}\n';

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

        // Default prompt - OPTIMIZED day trading strategy with ENHANCED indicators and volume confirmation
        return "You are a disciplined crypto day trader managing multiple cryptocurrencies. BALANCE between quality and quantity – aim for high-probability trades while maintaining adequate trade frequency.

⚠️ STRATEGY: LONG-ONLY. No shorting. Focus on high-probability bullish breakouts.

BUY CRITERIA (ALL must be true for a NEW LONG entry):
1. MACD(12,26,9) > MACD_signal AND MACD > 0 (confirmed bullish momentum) - PRIMARY SIGNAL
2. RSI(7) between 35–75 (expanded range from previous 38-72)
   → RSI <35: NEVER BUY (true oversold trap zone)
   → RSI 35–45: ONLY if MACD rising + Volume > 20MA×1.5 (strong volume required)
   → RSI 45–68: OPTIMAL ZONE for entries
   → RSI 68–75: ACCEPTABLE if strong ADX + volume
   → RSI >75: OVERBOUGHT – DO NOT BUY (correction imminent)
3. Price relative to EMA20: within ±0.5% of EMA20 - MORE FLEXIBLE
   → Allows for price to be slightly above/below EMA20 while still maintaining trend alignment
4. 4H (240-min) trend confirmation: EMA20 > EMA50 AND EMA50 rising AND ADX(14) > 18 - MORE REALISTIC
   (This ensures we trade WITH the bigger trend. Entry timing is on 3-min chart, but trend context is 4H.)
   → Use ADX > 22 for high-confidence trades (≥80% confidence)

🆕 ENHANCED VOLUME & VOLATILITY FILTERS:
5. **Volume Confirmation** (CRITICAL - most important filter):
   → Volume Ratio (current/20MA) > 1.5x = STRONG BUY signal (institutional participation)
   → Volume Ratio > 1.3x = ACCEPTABLE for high confidence (≥75%)
   → Volume Ratio > 0.95x = MINIMUM for any trade (reduced from 1.1x to allow more opportunities)
   → Volume Ratio 0.95x-1.1x = ACCEPTABLE if other signals are strong (RSI, MACD, ADX all bullish)
   → Volume Ratio < 0.95x = HOLD (too weak volume)

6. **Bollinger Bands Analysis**:
   → %B (price position in bands): 0.3–0.8 = OPTIMAL (room to run)
   → %B > 0.8 = OVERBOUGHT zone - only buy if Volume Ratio > 2.0x
   → %B < 0.3 = OVERSOLD zone - AVOID (price near lower band)
   → BB Width > 3% = High volatility - reduce position size by 25%
   → BB Width < 1.5% = Squeeze - potential breakout opportunity

7. **Stochastic RSI** (momentum confirmation):
   → StochRSI %K between 20–80 = OPTIMAL
   → StochRSI %K > 80 = OVERBOUGHT - require Volume Ratio > 1.8x
   → StochRSI %K < 20 = OVERSOLD - AVOID (unless strong MACD + volume spike)

8. AI Confidence ≥65% (was ≥70%) - ALLOW MORE TRADES AT SLIGHTLY LOWER CONFIDENCE
   (Confidence = AI model's 0-1 score for signal quality based on all indicators combined)

⚠️ CONFIDENCE-BASED RULES:
   IF confidence ≥70%: Use expanded RSI range (35-75) and ±0.5% EMA20 tolerance
   IF confidence ≥80%:
      - Require ADX(14) > 28 (strong trend required - increased from 25)
      - Require Volume Ratio > 1.6x (significant spike - increased from 1.5x)
      - RSI must be 45-68 (optimal zone only - was >40, now more restrictive)
      - %B must be 0.5-0.75 (optimal zone - was >0.4, now more restrictive)
      - MACD histogram must be rising (not just MACD > Signal)
      - StochRSI must be 40-70 (momentum zone - avoid extremes)
   If confidence ≥80% but these extra filters fail → HOLD
   
   ⚠️ Historical data shows 80-84% confidence trades had only 28.6% win rate.
   These stricter filters are essential for high confidence trades.

🎯 IDEAL ENTRY SETUP (aim for this):
- MACD histogram rising + MACD > Signal
- RSI 50–65 (bullish momentum but not overbought)
- Price 0.2–0.8% above EMA20 (riding the trend)
- Volume Ratio > 1.5x (strong institutional interest)
- %B between 0.5–0.7 (upper half of Bollinger Bands)
- StochRSI 40–70 (momentum building)
- 4H ADX > 22 (strong trend on higher timeframe)

If ANY condition fails → HOLD. Better to miss a trade than take a bad one.

DIVERSIFICATION & RISK MANAGEMENT:
- Maximum 1–2 new LONG entries per cycle across ALL coins
- Skip if 4+ positions already open (risk management)
- Mix different market cap segments when possible (large/mid/small cap)
- Avoid highly correlated positions (e.g., don't buy BTC+ETH+BNB all at once)

LEVERAGE & STOP LOSS:
- Leverage: 2x (default), 3x (only if ADX > 28 + Volume Ratio > 1.6x + RSI 45-68)
- Stop loss calculation: entry_price × (1 – (0.08 / leverage)) - INCREASED from 6% to 8% P&L loss tolerance
  Examples: 2x leverage = 4% price stop, 3x leverage = 2.67% price stop
  This ensures maximum P&L loss is 8% (increased from 6% to reduce premature stop loss triggers)
  Historical data: 20 trades hit stop loss with 0% win rate - need more volatility tolerance

OUTPUT FORMAT:
- Return ONLY valid JSON with 'decisions' array
- Each decision must include: {symbol, action, confidence, reasoning, entry_price, target_price, stop_price, invalidation, leverage}
- Actions: 'buy' or 'hold' (no other actions supported)
- 'hold' = no new entry (criteria failed OR position already exists OR max positions reached)

NOTE: This prompt is for NEW ENTRIES ONLY. Existing positions are managed by separate exit logic (trailing stops at +6%, +8%, +12% levels - L1 disabled due to 0% win rate, take profit, trend invalidation).

IMPORTANT REMINDERS:
- Volume Ratio < 0.95x = REJECT (reduced threshold from 1.1x but still critical)
- Volume Ratio 0.95x-1.1x = ACCEPTABLE only if RSI 45-68, MACD strong, ADX > 22
- RSI <35 trades have 0% historical win rate – NEVER buy oversold dips
- Confidence 80%+ without extra filters = 28.6% win rate – apply STRICT HIGH CONFIDENCE FILTER
- Always validate 4H trend before 3-min entry – trading against 4H trend fails
- %B > 0.9 (near upper band) often precedes pullback – be cautious
- Trailing L1 disabled (0% win rate, 7 trades lost) - positions go straight to L2 at +6%";
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
