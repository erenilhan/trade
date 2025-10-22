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

            // Log AI call to database
//            $this->logAICall($prompt, $aiResponse);

            $decision = $aiResponse['decision'];
            $rawResponse = $aiResponse['raw_response'];

            // Log the AI call
            $this->logAiCall($this->provider, $prompt, $rawResponse, $decision);

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

            $data3m = $data['3m'];
            $data4h = $data['4h'];

            $cleanSymbol = str_replace('/USDT', '', $symbol);

            $prompt .= "ALL {$cleanSymbol} DATA\n";
            $prompt .= sprintf(
                "current_price = %.2f, current_ema20 = %.2f, current_macd = %.3f, macd_signal = %.3f, current_rsi (7 period) = %.2f\n",
                $data3m['price'],
                $data3m['ema20'],
                $data3m['macd'],
                $data3m['macd_signal'] ?? 0,
                $data3m['rsi7']
            );

            $prompt .= sprintf(
                "MACD > Signal? %s, MACD > (price * 0.0001)? %s\n\n",
                ($data3m['macd'] > ($data3m['macd_signal'] ?? 0)) ? 'YES' : 'NO',
                ($data3m['macd'] > ($data3m['price'] * 0.0001)) ? 'YES' : 'NO'
            );

            $prompt .= "Funding Rate: " . number_format($data3m['funding_rate'], 10) . "\n";
            $prompt .= "Open Interest: Latest: " . number_format($data3m['open_interest'], 2) . "\n\n";

            $prompt .= "Intraday series (3-minute intervals, last 10 candles = 30min context):\n";
            $prompt .= "Prices: [" . implode(', ', array_map(fn($p) => number_format($p, 2), array_slice($data3m['price_series'], -10))) . "]\n";
            $prompt .= "EMA20: [" . implode(', ', array_map(fn($e) => number_format($e, 2), array_slice($data3m['indicators']['ema_series'], -10))) . "]\n";
            $prompt .= "MACD: [" . implode(', ', array_map(fn($m) => number_format($m, 3), array_slice($data3m['indicators']['macd_series'], -10))) . "]\n";
            $prompt .= "RSI7: [" . implode(', ', array_map(fn($r) => number_format($r, 1), array_slice($data3m['indicators']['rsi7_series'], -10))) . "]\n\n";

            // Volume info
            $currentVolume = $data3m['indicators']['volume'] ?? 0;
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
        $prompt .= "Always include: action, reasoning, confidence (0-1), entry_price, target_price, stop_price, invalidation.\n\n";

        $prompt .= "RESPONSE FORMAT (strict JSON):\n";
        $prompt .= '{"decisions":[{"symbol":"BTC/USDT","action":"hold|buy","reasoning":"...","confidence":0.75,"entry_price":null,"target_price":null,"stop_price":null,"invalidation":"..."}],"chain_of_thought":"..."}\n';

        return $prompt;
    }

    /**
     * Call DeepSeek API directly
     */
    private function callDeepSeekAPI(string $prompt): array
    {
        $client = DeepSeekClient::build(config('deepseek.api_key'));

        $fullPrompt = $this->getSystemPrompt() . "\n\n" . $prompt;
        $response = $client
            ->withModel(config('deepseek.model', 'deepseek-chat'))
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

        // Default prompt - STRICT day trading strategy with quality over quantity
        return "You are a disciplined crypto day trader managing BTC,ETH,SOL,BNB,XRP,DOGE,ADA,AVAX,LINK,DOT. QUALITY over QUANTITY - only trade when signals are crystal clear.

BUY CRITERIA (ALL must be true):
1. Price > EMA20 by at least 0.3% (early entry, slight buffer for whipsaw protection)
2. MACD > MACD_signal (signal line crossover) AND MACD > close * 0.00005 (looser dynamic threshold for early momentum)
3. RSI between 35-75 (allow slight oversold/overbought - catches rally starts/continuations)
4. 4H timeframe bullish: EMA20 > EMA50*0.999 (near crossover OK) AND ADX(14) > 20 (moderate trend strength)
5. Volume > 20MA*0.9 AND > previous_bar*1.05 (moderate volume confirmation)
6. Confidence must be >70% (balanced quality threshold)

If ANY condition fails → HOLD. Better to miss a trade than take a bad one.

RSI RULES:
- RSI >75 = EXTREME OVERBOUGHT = DO NOT BUY (correction likely)
- RSI <35 = EXTREME OVERSOLD = WAIT for bounce confirmation
- RSI 45-70 = OPTIMAL ZONE for entries
- RSI 35-45 = ACCEPTABLE if other signals strong (rally starting)
- RSI 70-75 = ACCEPTABLE if momentum strong (rally continuation)

DIVERSIFICATION:
- Mix large cap (BTC/ETH/BNB), mid cap (SOL/ADA/AVAX), small cap (XRP/DOGE/LINK/DOT)
- Maximum 1-2 BUY per cycle across ALL coins
- Prefer different market cap segments

Use 2-3% stop loss, target 3-5% profit. Always return valid JSON with 'decisions' array containing {symbol,action,confidence,reasoning,entry_price,target_price,stop_price,invalidation} for each coin. Actions: buy, hold.";
    }

    /**
     * Call OpenRouter API
     * @throws ReflectionException
     */
    private function callOpenRouter(string $prompt): array
    {
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
            model: config('openrouter.model', 'deepseek/deepseek-chat'),
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
        ];
    }

    /**
     * Log AI call details
     */
    private function logAiCall(string $provider, string $prompt, array $response, array $decision): void
    {
        AiLog::create([
            'provider' => $provider,
            'prompt' => $prompt,
            'response' => json_encode($response),
            'decision' => $decision,
        ]);
    }

}
