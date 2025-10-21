<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\Position;
use App\Models\Trade;
use App\Models\TradeLog;
use App\Services\BinanceService;
use App\Services\MockBinanceService;
use App\Services\TradingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionClass;

class TradingController extends Controller
{
    public function __construct(
        private readonly TradingService $trading
    )
    {
    }

    /**
     * Execute auto trade manually
     * POST /api/trade/execute
     */
    public function execute(): JsonResponse
    {
        try {
            $result = $this->trading->executeAutoTrade();

            return response()->json([
                'success' => true,
                'message' => 'Trade executed successfully',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trade execution failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account status
     * GET /api/trade/status
     */
    public function status(): JsonResponse
    {
        try {
            $binance = $this->getBinanceService();
            $balance = $binance->fetchBalance();
            $positions = Position::open()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => [
                        'free' => $balance['USDT']['free'] ?? 0,
                        'total' => $balance['USDT']['total'] ?? 0,
                    ],
                    'positions' => $positions->map(fn($p) => [
                        'id' => $p->id,
                        'symbol' => $p->symbol,
                        'side' => $p->side,
                        'quantity' => $p->quantity,
                        'entry_price' => $p->entry_price,
                        'current_price' => $p->current_price,
                        'unrealized_pnl' => $p->unrealized_pnl,
                        'profit_percent' => $p->profit_percent,
                        'opened_at' => $p->opened_at,
                    ]),
                    'settings' => [
                        'bot_enabled' => BotSetting::get('bot_enabled', true),
                        'use_ai' => BotSetting::get('use_ai', false),
                        'max_leverage' => BotSetting::get('max_leverage', 2),
                        'position_size' => BotSetting::get('position_size_usdt', 100),
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getBinanceService()
    {
        $mode = config('app.trading_mode', env('TRADING_MODE', 'mock'));

        return match ($mode) {
            'mock' => app(MockBinanceService::class),
            default => app(BinanceService::class),
        };
    }

    /**
     * Get recent trades
     * GET /api/trade/history
     */
    public function history(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 50);

        $trades = Trade::recent($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $trades
        ]);
    }

    /**
     * Get trade logs
     * GET /api/trade/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 100);

        $logs = TradeLog::recent($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Manual buy
     * POST /api/trade/buy
     */
    public function buy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'cost' => 'required|numeric|min:10',
            'leverage' => 'sometimes|integer|min:1|max:20',
        ]);

        try {
            $decision = [
                'action' => 'buy',
                'symbol' => $validated['symbol'],
                'cost' => $validated['cost'],
                'leverage' => $validated['leverage'] ?? BotSetting::get('max_leverage', 2),
                'reasoning' => 'Manual buy order',
            ];

            // Execute via TradingService
            $reflection = new ReflectionClass($this->trading);
            $method = $reflection->getMethod('executeBuy');
            $method->setAccessible(true);
            $result = $method->invoke($this->trading, $decision);

            return response()->json([
                'success' => true,
                'message' => 'Buy order executed',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Buy order failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual close position
     * POST /api/trade/close/{positionId}
     */
    public function close(int $positionId): JsonResponse
    {
        try {
            $position = Position::findOrFail($positionId);

            if (!$position->is_open) {
                return response()->json([
                    'success' => false,
                    'message' => 'Position is already closed'
                ], 400);
            }

            $decision = [
                'action' => 'close_profitable',
                'symbol' => $position->symbol,
                'reasoning' => 'Manual close order',
            ];

            // Execute via TradingService
            $reflection = new ReflectionClass($this->trading);
            $method = $reflection->getMethod('executeClose');
            $method->setAccessible(true);
            $result = $method->invoke($this->trading, $decision);

            return response()->json([
                'success' => true,
                'message' => 'Position closed',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Close order failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
