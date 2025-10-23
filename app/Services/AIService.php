<?php

namespace App\Services;

use MoeMizrak\LaravelOpenrouter\Facades\Openrouter;
use DeepSeek\Client as DeepSeekClient;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\Types\RoleType;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\Trade;
use App\Models\TradeLog;
use App\Models\BotSetting;
use App\Models\AiLog;

class AIService
{
    private array $tradingHistory = [];
    private array $marketContext = [];
    private string $provider;

    public function __construct()
    {
        // AI_PROVIDER: 'openrouter', 'deepseek', 'openai'
        $this->provider = config('app.ai_provider', 'openrouter');
    }

    /**
     * Make trading decision using selected AI provider
     */
    public function makeDecision(array $account): array
    {
        try {
            // Gather market intelligence
            $this->gatherMarketContext();

            // Get trading history for context
            $this->loadTradingHistory();

            $prompt = $this->buildAdvancedPrompt($account);

            Log::info("🤖 AI Prompt ({$this->provider})", ['prompt' => substr($prompt, 0, 500) . '...']);

            // Call appropriate AI provider
            $aiResponse = match($this->provider) {
                'deepseek' => $this->callDeepSeekAPI($prompt),
                'openai' => $this->callOpenAI($prompt),
                'openrouter' => $this->callOpenRouter($prompt),
                default => throw new \Exception("Invalid AI provider: {$this->provider}")
            };

            $decision = $aiResponse['decision'];
            $rawResponse = $aiResponse['raw_response'];
            $model = $aiResponse['model'];

            // Validate and sanitize decision
            $decision = $this->validateDecision($decision, $account);

            // Log the AI call
            $this->logAiCall($this->provider, $model, $prompt, $rawResponse, $decision);

            Log::info("🤖 AI Decision ({$this->provider})", [
                'action' => $decision['action'],
                'confidence' => $decision['confidence'],
                'reasoning' => substr($decision['reasoning'] ?? '', 0, 100)
            ]);

            return $decision;

        } catch (\Exception $e) {
            Log::error('❌ AI Error', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to safe decision
            return [
                'action' => 'hold',
                'symbol' => 'BTC/USDT',
                'reasoning' => 'AI service error, holding position for safety: ' . $e->getMessage(),
                'confidence' => 0,
                'technical_analysis' => 'N/A - Error occurred',
                'risk_level' => 'high'
            ];
        }
    }

    /**
     * Call DeepSeek API directly
     */
    private function callDeepSeekAPI(string $prompt): array
    {
        $client = DeepSeekClient::make(config('deepseek.api_key'));
        $model = config('deepseek.model', 'deepseek-chat');

        $response = $client->chat()->create([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]['message']['content'] ?? null;

        if (!$content) {
            throw new \Exception('Empty DeepSeek response');
        }

        $decision = json_decode($content, true);

        return [
            'decision' => $decision,
            'raw_response' => $response->toArray(),
            'model' => $model,
        ];
    }

    /**
     * Call OpenRouter API
     */
    private function callOpenRouter(string $prompt): array
    {
        $model = config('openrouter.model', 'deepseek/deepseek-chat');

        $response = Openrouter::chatRequest(
            new ChatData(
                messages: [
                    new MessageData(
                        role: RoleType::SYSTEM,
                        content: $this->getSystemPrompt()
                    ),
                    new MessageData(
                        role: RoleType::USER,
                        content: $prompt
                    ),
                ],
                model: $model,
                temperature: 0.3,
                max_tokens: 1000,
                response_format: ['type' => 'json_object']
            )
        );

        $content = $response->choices[0]['message']['content'] ?? null;

        if (!$content) {
            throw new \Exception('Empty OpenRouter response');
        }

        $decision = json_decode($content, true);

        return [
            'decision' => $decision,
            'raw_response' => $response->toArray(),
            'model' => $model,
        ];
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt): array
    {
        $model = 'gpt-4-turbo-preview';

        $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? null;

        if (!$content) {
            throw new \Exception('Empty OpenAI response');
        }

        $decision = json_decode($content, true);

        return [
            'decision' => $decision,
            'raw_response' => $response->toArray(),
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
     * Get system prompt with trading personality
     */
    private function getSystemPrompt(): string
    {
        return <<<SYSTEM
You are an expert cryptocurrency trading AI with deep knowledge of:
- Technical analysis (RSI, MACD, Moving Averages, Support/Resistance)
- Market psychology and sentiment
- Risk management and position sizing
- Trend identification and reversal patterns

Your trading philosophy:
1. CAPITAL PRESERVATION is paramount - avoid unnecessary risks
2. Wait for HIGH PROBABILITY setups with favorable risk/reward
3. Use technical analysis to confirm decisions
4. Consider market sentiment and recent price action
5. NEVER trade on FOMO or panic
6. Respect stop losses and take profit levels

Response format: You MUST respond with valid JSON only, no additional text.
Required fields: action, symbol, reasoning, confidence, technical_analysis, risk_level

Available actions:
- "buy": Enter new long position (only if no positions exist and conditions are favorable)
- "close_profitable": Close position with profit >5%
- "stop_loss": Close losing position <-3%
- "hold": No action (default when uncertain or waiting for better setup)

Risk levels: "low", "medium", "high"
Confidence: 0.0 to 1.0 (only trade above 0.7)

IMPORTANT: Always provide your response as a single JSON object.
SYSTEM;
    }

    /**
     * Build advanced trading prompt with market context
     */
    private function buildAdvancedPrompt(array $account): string
    {
        $cash = number_format($account['cash'], 2);
        $totalValue = number_format($account['total_value'], 2);
        $positions = $account['positions'];
        $positionCount = count($positions);

        // Market data
        $marketData = $this->marketContext;

        // Recent performance
        $recentPerformance = $this->getRecentPerformance();

        // Position analysis
        $positionsStr = $this->formatPositions($positions);

        // Trading settings
        $maxLeverage = BotSetting::get('max_leverage', 2);
        $positionSize = BotSetting::get('position_size_usdt', 100);
        $takeProfitPercent = BotSetting::get('take_profit_percent', 5);
        $stopLossPercent = BotSetting::get('stop_loss_percent', 3);

        return <<<PROMPT
# TRADING CONTEXT & MARKET ANALYSIS

## 📊 ACCOUNT STATUS
- Total Portfolio Value: \${$totalValue} USDT
- Available Cash: \${$cash} USDT
- Open Positions: {$positionCount}
- Portfolio Utilization: {$this->calculateUtilization($account)}%

{$positionsStr}

## 📈 MARKET DATA
{$this->formatMarketData($marketData)}

## 📉 RECENT PERFORMANCE
{$recentPerformance}

## ⚙️ TRADING RULES & CONSTRAINTS
- Maximum Leverage: {$maxLeverage}x
- Position Size: \${$positionSize} USDT per trade
- Take Profit Target: >{$takeProfitPercent}%
- Stop Loss: <-{$stopLossPercent}%
- Max Open Positions: 1 (to minimize risk)
- Trading Mode: Conservative with risk management

## 🎯 DECISION CRITERIA

### FOR BUY:
- Must have NO open positions
- Available cash > \${$positionSize}
- Market showing bullish momentum
- Technical indicators aligned
- Clear support levels identified
- Confidence level > 0.7

### FOR CLOSE_PROFITABLE:
- Position profit > {$takeProfitPercent}%
- Technical indicators showing weakness OR
- Strong resistance level reached

### FOR STOP_LOSS:
- Position loss < -{$stopLossPercent}%
- Cut losses early to preserve capital

### FOR HOLD:
- Uncertain market conditions
- Waiting for better entry/exit point
- Low confidence (<0.7)
- No clear setup

## 🤔 YOUR TASK
Analyze the current market situation and make a trading decision.

Consider:
1. Current market trend (bullish/bearish/sideways)
2. Technical indicators and price action
3. Risk/reward ratio of potential trades
4. Account size and risk management
5. Recent trading performance

Respond with JSON ONLY (no markdown, no code blocks):
{
  "action": "buy|close_profitable|stop_loss|hold",
  "symbol": "BTC/USDT",
  "reasoning": "Detailed analysis explaining your decision (50-150 words)",
  "confidence": 0.85,
  "technical_analysis": "Brief TA summary (RSI, trend, support/resistance)",
  "risk_level": "low|medium|high",
  "entry_price": 45000.50,
  "target_price": 47250.00,
  "stop_price": 43650.00
}

Make your decision now:
PROMPT;
    }

    /**
     * Gather market context and technical data
     */
    private function gatherMarketContext(): void
    {
        try {
            $binance = app(BinanceService::class);

            // Get multiple timeframes for BTC
            $symbols = ['BTC/USDT', 'ETH/USDT'];

            foreach ($symbols as $symbol) {
                try {
                    $ticker = $binance->fetchTicker($symbol);

                    $this->marketContext[$symbol] = [
                        'price' => $ticker['last'] ?? 0,
                        'high_24h' => $ticker['high'] ?? 0,
                        'low_24h' => $ticker['low'] ?? 0,
                        'volume_24h' => $ticker['volume'] ?? 0,
                        'change_24h' => $ticker['percentage'] ?? 0,
                        'bid' => $ticker['bid'] ?? 0,
                        'ask' => $ticker['ask'] ?? 0,
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch ticker for {$symbol}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to gather market context: ' . $e->getMessage());
            $this->marketContext = [];
        }
    }

    /**
     * Load recent trading history
     */
    private function loadTradingHistory(): void
    {
        $this->tradingHistory = TradeLog::recent(20)
            ->get()
            ->map(fn($log) => [
                'action' => $log->action,
                'success' => $log->success,
                'reasoning' => $log->message,
                'timestamp' => $log->executed_at->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    /**
     * Format market data for prompt
     */
    private function formatMarketData(array $marketData): string
    {
        if (empty($marketData)) {
            return "⚠️ Market data unavailable";
        }

        $output = "";
        foreach ($marketData as $symbol => $data) {
            $price = number_format($data['price'], 2);
            $change = number_format($data['change_24h'] ?? 0, 2);
            $changeEmoji = $change > 0 ? '📈' : ($change < 0 ? '📉' : '➡️');

            $output .= "### {$symbol}\n";
            $output .= "- Current Price: \${$price}\n";
            $output .= "- 24h Change: {$changeEmoji} {$change}%\n";
            $output .= "- 24h High: \$" . number_format($data['high_24h'], 2) . "\n";
            $output .= "- 24h Low: \$" . number_format($data['low_24h'], 2) . "\n";
            $output .= "- 24h Volume: " . number_format($data['volume_24h'], 2) . "\n\n";
        }

        return $output;
    }

    /**
     * Format positions for prompt
     */
    private function formatPositions(array $positions): string
    {
        if (empty($positions)) {
            return "## 💼 OPEN POSITIONS\nNone - Ready to enter new positions when conditions are favorable.";
        }

        $output = "## 💼 OPEN POSITIONS\n";
        foreach ($positions as $pos) {
            $pnl = $pos['profit_percent'];
            $pnlEmoji = $pnl > 0 ? '🟢' : '🔴';

            $output .= "### {$pos['symbol']}\n";
            $output .= "- Side: " . strtoupper($pos['side']) . "\n";
            $output .= "- Entry Price: \$" . number_format($pos['entry_price'], 2) . "\n";
            $output .= "- Current Price: \$" . number_format($pos['current_price'], 2) . "\n";
            $output .= "- P&L: {$pnlEmoji} " . number_format($pnl, 2) . "%\n";
            $output .= "- Unrealized P&L: \$" . number_format($pos['unrealized_pnl'], 2) . "\n\n";
        }

        return $output;
    }

    /**
     * Get recent trading performance
     */
    private function getRecentPerformance(): string
    {
        $recentTrades = Trade::recent(10)->success()->get();

        if ($recentTrades->isEmpty()) {
            return "No recent trading history.";
        }

        $winRate = $recentTrades->where('status', 'filled')->count() / $recentTrades->count() * 100;

        return "Recent 10 trades: Win rate " . number_format($winRate, 1) . "%";
    }

    /**
     * Calculate portfolio utilization
     */
    private function calculateUtilization(array $account): float
    {
        $total = $account['total_value'];
        $cash = $account['cash'];

        if ($total <= 0) return 0;

        return round((($total - $cash) / $total) * 100, 2);
    }

    /**
     * Validate AI decision before execution
     */
    private function validateDecision(array $decision, array $account): array
    {
        // Ensure required fields
        $decision['action'] = $decision['action'] ?? 'hold';
        $decision['symbol'] = $decision['symbol'] ?? 'BTC/USDT';
        $decision['reasoning'] = $decision['reasoning'] ?? 'No reasoning provided';
        $decision['confidence'] = $decision['confidence'] ?? 0;
        $decision['risk_level'] = $decision['risk_level'] ?? 'medium';

        // Validate confidence
        if ($decision['confidence'] < 0) $decision['confidence'] = 0;
        if ($decision['confidence'] > 1) $decision['confidence'] = 1;

        // Override low confidence decisions to hold
        if ($decision['confidence'] < 0.7 && $decision['action'] !== 'hold') {
            Log::warning('⚠️ AI confidence too low, overriding to HOLD', [
                'original_action' => $decision['action'],
                'confidence' => $decision['confidence']
            ]);

            $decision['action'] = 'hold';
            $decision['reasoning'] = 'Confidence below threshold (0.7), holding for safety. ' . $decision['reasoning'];
        }

        // Add trading parameters
        if ($decision['action'] === 'buy') {
            $decision['cost'] = BotSetting::get('position_size_usdt', 100);
            $decision['leverage'] = BotSetting::get('max_leverage', 2);
        }

        return $decision;
    }
}
