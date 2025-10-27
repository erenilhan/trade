<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\Position;
use App\Models\TradeLog;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class TradingService
{
    public function __construct(
        private BinanceService $binance,
        private ?AIService $ai = null
    ) {
        $this->ai = BotSetting::get('use_ai', false) ? app(AIService::class) : null;
    }

    public function executeAutoTrade(): array
    {
        $startTime = now();
        Log::info('🤖 Auto-trade started', ['timestamp' => $startTime]);

        try {
            // 1. Get account state
            $account = $this->getAccountState();

            Log::info('📊 Account state', $account);

            // 2. Make decision (AI or simple strategy)
            $decision = $this->ai
                ? $this->ai->makeDecision($account)
                : $this->simpleStrategy($account);

            Log::info('💡 Decision', $decision);

            // 3. Execute decision
            $result = $this->executeDecision($decision);

            // 4. Log the trade
            $this->logTrade($decision['action'], true, $account, $decision, $result);

            return [
                'success' => true,
                'action' => $decision['action'],
                'result' => $result,
                'duration' => now()->diffInSeconds($startTime) . 's',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Auto-trade error', ['error' => $e->getMessage()]);

            $this->logTrade('error', false, [], [], null, $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getAccountState(): array
    {
        $balance = $this->binance->fetchBalance();
        $positions = Position::open()->get();

        return [
            'cash' => $balance['USDT']['free'] ?? 0,
            'total_value' => $balance['USDT']['total'] ?? 0,
            'positions' => $positions->map(fn($p) => [
                'symbol' => $p->symbol,
                'side' => $p->side,
                'quantity' => $p->quantity,
                'entry_price' => $p->entry_price,
                'unrealized_pnl' => $p->unrealized_pnl,
                'profit_percent' => $p->profit_percent,
            ])->toArray(),
        ];
    }

    private function simpleStrategy(array $account): array
    {
        $cash = $account['cash'];
        $positions = $account['positions'];

        // Rule 1: Buy if no positions and have cash
        if (count($positions) === 0 && $cash > 100) {
            return [
                'action' => 'buy',
                'symbol' => 'BTC/USDT',
                'cost' => BotSetting::get('position_size_usdt', 100),
                'leverage' => BotSetting::get('max_leverage', 2),
                'reasoning' => 'No positions and sufficient cash available',
            ];
        }

        // Rule 2: Close profitable positions (>5%)
        foreach ($positions as $position) {
            if ($position['profit_percent'] > BotSetting::get('take_profit_percent', 5)) {
                return [
                    'action' => 'close_profitable',
                    'symbol' => $position['symbol'],
                    'reasoning' => "Take profit at {$position['profit_percent']}%",
                ];
            }
        }

        // Rule 3: Stop loss (<-3%)
        foreach ($positions as $position) {
            if ($position['profit_percent'] < -BotSetting::get('stop_loss_percent', 3)) {
                return [
                    'action' => 'stop_loss',
                    'symbol' => $position['symbol'],
                    'reasoning' => "Stop loss at {$position['profit_percent']}%",
                ];
            }
        }

        // Rule 4: Hold
        return [
            'action' => 'hold',
            'reasoning' => 'No trading conditions met',
        ];
    }

    private function executeDecision(array $decision): ?array
    {
        switch ($decision['action']) {
            case 'buy':
                return $this->executeBuy($decision);

            case 'close_profitable':
            case 'stop_loss':
                return $this->executeClose($decision);

            case 'hold':
            default:
                return null;
        }
    }

    private function executeBuy(array $decision): array
    {
        $symbol = $decision['symbol'];
        $cost = $decision['cost'];
        $leverage = $decision['leverage'];

        // Set leverage
        $this->binance->setLeverage($leverage, $symbol);

        // Get current price
        $ticker = $this->binance->fetchTicker($symbol);
        $price = $ticker['last'];

        // Calculate amount
        $amount = ($cost * $leverage) / $price;

        // Execute order
        $order = $this->binance->createMarketBuy($symbol, $amount);

        // Save trade
        Trade::create([
            'order_id' => $order['id'],
            'symbol' => $symbol,
            'side' => 'buy',
            'type' => 'market',
            'amount' => $amount,
            'price' => $price,
            'cost' => $cost,
            'leverage' => $leverage,
            'status' => 'filled',
            'response_data' => $order,
        ]);

        // Create position
        Position::create([
            'symbol' => $symbol,
            'side' => 'long',
            'quantity' => $amount,
            'entry_price' => $price,
            'current_price' => $price,
            'leverage' => $leverage,
            'notional_usd' => $cost * $leverage,
            'is_open' => true,
            'opened_at' => now(),
        ]);

        return [
            'order_id' => $order['id'],
            'symbol' => $symbol,
            'price' => $price,
            'amount' => $amount,
        ];
    }

    public function closePositionManually(string $symbol, string $closeReason = 'Manual closure'): array
    {
        $position = Position::where('symbol', $symbol)->where('is_open', true)->first();

        if (!$position) {
            throw new \Exception("No open position found for {$symbol} to close.");
        }

        $decision = [
            'action' => 'close_manual',
            'symbol' => $symbol,
            'reasoning' => $closeReason,
        ];

        return $this->executeClose($decision);
    }

    private function executeClose(array $decision): array
    {
        $symbol = $decision['symbol'];
        $position = Position::where('symbol', $symbol)->where('is_open', true)->first();

        if (!$position) {
            throw new \Exception("Position not found for {$symbol}");
        }

        // Execute close order (LONG: sell, SHORT: buy)
        if ($position->side === 'short') {
            $order = $this->binance->createMarketBuy($symbol, $position->quantity, [
                'reduceOnly' => true,
            ]);
            $closeSide = 'buy';
        } else {
            $order = $this->binance->createMarketSell($symbol, $position->quantity, [
                'reduceOnly' => true,
            ]);
            $closeSide = 'sell';
        }

        // Calculate PNL percentage
        $exitPrice = $order['price'] ?? $position->current_price;
        $priceDiff = $exitPrice - $position->entry_price;
        if ($position->side === 'short') {
            $priceDiff = -$priceDiff;
        }
        $pnlPercent = ($priceDiff / $position->entry_price) * 100 * $position->leverage;

        // Update position with close reason
        $position->update([
            'is_open' => false,
            'closed_at' => now(),
            'realized_pnl' => $position->unrealized_pnl,
            'close_reason' => 'manual', // Always 'manual' for manual closes
            'close_metadata' => [
                'profit_pct' => round($pnlPercent, 2),
                'exit_price' => $exitPrice,
                'order_id' => $order['id'] ?? null,
                'reason_detail' => $decision['reasoning'] ?? 'Manually closed via API',
            ],
        ]);

        // Save trade
        Trade::create([
            'order_id' => $order['id'],
            'symbol' => $symbol,
            'side' => $closeSide,
            'type' => 'market',
            'amount' => $position->quantity,
            'price' => $order['price'] ?? $position->current_price,
            'cost' => $position->notional_usd,
            'status' => 'filled',
            'response_data' => $order,
        ]);

        return [
            'order_id' => $order['id'],
            'symbol' => $symbol,
            'pnl' => $position->unrealized_pnl,
        ];
    }

    private function logTrade(
        string $action,
        bool $success,
        array $account,
        array $decision,
        ?array $result,
        ?string $error = null
    ): void {
        TradeLog::create([
            'action' => $action,
            'success' => $success,
            'message' => $decision['reasoning'] ?? null,
            'account_state' => $account,
            'decision_data' => $decision,
            'result_data' => $result,
            'error_message' => $error,
            'executed_at' => now(),
        ]);
    }
}
