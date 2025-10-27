<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\Position;
use App\Models\Trade;
use App\Services\BinanceService;
use App\Services\MarketDataService;
use App\Models\AiLog;
use App\Services\MockBinanceService;
use Exception;

class TradeDashboardController extends Controller
{
    public function __construct(
        private BinanceService $binance,
        private MarketDataService $marketData
    ) {}

    /**
     * Show the trading dashboard
     */
    public function index()
    {
        return view('trade_dashboard');
    }

    /**
     * Show password entry page
     */
    public function showPasswordForm()
    {
        return view('dashboard_password');
    }

    /**
     * Verify dashboard password
     */
    public function verifyPassword(\Illuminate\Http\Request $request)
    {
        $password = $request->input('password');
        $correctPassword = env('DASHBOARD_PASSWORD', 'trading123');

        if ($password === $correctPassword) {
            session()->put('dashboard_authenticated', true);
            return redirect()->route('dashboard');
        }

        return redirect()->route('dashboard.password')->with('error', 'Incorrect password. Please try again.');
    }

    /**
     * Logout from dashboard
     */
    public function logout()
    {
        session()->forget('dashboard_authenticated');
        return redirect()->route('dashboard.password')->with('error', 'You have been logged out.');
    }

    /**
     * Show the documentation page
     */
    public function documentation()
    {
        $readmePath = base_path('readme.md');
        
        if (file_exists($readmePath)) {
            $readmeContent = file_get_contents($readmePath);
            // Convert markdown to HTML using a simple conversion
            $htmlContent = $this->convertMarkdownToHtml($readmeContent);
        } else {
            $htmlContent = '<p class="text-center py-8 text-red-400">Documentation file not found</p>';
        }

        return view('documentation', ['readmeContent' => $htmlContent]);
    }

    /**
     * Show the about page
     */
    public function about()
    {
        return view('about');
    }

    /**
     * Simple markdown to HTML conversion
     */
    private function convertMarkdownToHtml($markdown)
    {
        $html = $markdown;
        
        // Convert headers
        $html = preg_replace('/^### (.*$)/m', '<h3 class="text-xl font-bold mt-6 mb-3 text-white">$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2 class="text-2xl font-bold mt-8 mb-4 text-white border-b border-dark-600 pb-2">$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/m', '<h1 class="text-3xl font-bold mt-8 mb-6 text-white border-b border-dark-600 pb-3">$1</h1>', $html);
        
        // Convert bold text
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold">$1</strong>', $html);
        
        // Convert italics
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        
        // Convert code blocks
        $html = preg_replace('/```.*?\n(.*?)```/s', '<pre class="bg-dark-700 p-4 rounded my-4 overflow-x-auto"><code class="text-sm">$1</code></pre>', $html);
        
        // Convert inline code
        $html = preg_replace('/`(.*?)`/', '<code class="bg-dark-700 px-2 py-1 rounded text-sm">$1</code>', $html);
        
        // Convert links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="text-blue-400 hover:text-blue-300">$1</a>', $html);
        
        // Convert unordered lists
        $html = preg_replace('/^\s*-\s(.+)$/m', '<li class="ml-6 list-disc">$1</li>', $html);
        
        // Convert paragraphs - join consecutive lines that don't start with headers or lists
        // This is more complex, so we'll do a simple version
        $html = preg_replace('/^\s*([^#\s].*?)\s*$/m', '<p class="mb-4">$1</p>', $html);
        
        // Replace line breaks with <br> tags
        $html = nl2br($html);
        
        return $html;
    }

    /**
     * Get dashboard data (real multi-coin system)
     */
    public function getData()
    {
        try {
            // Get balance
            $balance = $this->binance->fetchBalance();
            $totalValue = $balance['USDT']['total'] ?? 0;
            $cash = $balance['USDT']['free'] ?? 0;

            // Get initial investment
            $initialCapital = BotSetting::get('initial_capital', config('app.initial_capital', 1000));

            // Get active positions
            $positions = Position::active()->get()->map(function ($pos) {
                $currentPrice = (float) $pos->current_price;
                $entryPrice = (float) $pos->entry_price;

                // Calculate PNL
                $priceDiff = $currentPrice - $entryPrice;
                if ($pos->side === 'short') {
                    $priceDiff = -$priceDiff;
                }
                $pnl = $priceDiff * $pos->quantity * $pos->leverage;
                $pnlPercent = ($priceDiff / $entryPrice) * 100 * $pos->leverage;

                // Get exit plan targets
                $exitPlan = $pos->exit_plan ?? [];
                $profitTarget = isset($exitPlan['profit_target']) ? (float) $exitPlan['profit_target'] : null;
                $stopLoss = isset($exitPlan['stop_loss']) ? (float) $exitPlan['stop_loss'] : null;

                // Calculate distance to targets (handle SHORT positions)
                $distanceToProfit = null;
                $distanceToStop = null;
                $profitNeeded = null;
                $stopDistance = null;

                if ($profitTarget) {
                    if ($pos->side === 'short') {
                        // SHORT: profit target is BELOW current price
                        $distanceToProfit = (($currentPrice - $profitTarget) / $currentPrice) * 100;
                        $profitNeeded = $currentPrice - $profitTarget;
                    } else {
                        // LONG: profit target is ABOVE current price
                        $distanceToProfit = (($profitTarget - $currentPrice) / $currentPrice) * 100;
                        $profitNeeded = $profitTarget - $currentPrice;
                    }
                }

                if ($stopLoss) {
                    if ($pos->side === 'short') {
                        // SHORT: stop loss is ABOVE current price
                        $distanceToStop = (($stopLoss - $currentPrice) / $currentPrice) * 100;
                        $stopDistance = $stopLoss - $currentPrice;
                    } else {
                        // LONG: stop loss is BELOW current price
                        $distanceToStop = (($currentPrice - $stopLoss) / $currentPrice) * 100;
                        $stopDistance = $currentPrice - $stopLoss;
                    }
                }

                // Calculate position size (how much $ invested)
                $positionSize = $pos->quantity * $entryPrice;
                $notionalSize = $pos->notional_usd ?? ($positionSize * $pos->leverage);

                // Detect trailing stop level (handle SHORT positions)
                $trailingLevel = null;
                if ($stopLoss && $entryPrice > 0) {
                    if ($pos->side === 'short') {
                        // SHORT: stop loss below entry = profit locked
                        $stopDiff = (($entryPrice - $stopLoss) / $entryPrice) * 100;
                    } else {
                        // LONG: stop loss above entry = profit locked
                        $stopDiff = (($stopLoss - $entryPrice) / $entryPrice) * 100;
                    }

                    if ($stopDiff >= 7) {
                        $trailingLevel = 4; // Level 4: Stop at +8%
                    } elseif ($stopDiff >= 4) {
                        $trailingLevel = 3; // Level 3: Stop at +5%
                    } elseif ($stopDiff >= 1.5) {
                        $trailingLevel = 2; // Level 2: +2%
                    } elseif ($stopDiff >= -0.5 && $stopDiff <= 0.5) {
                        $trailingLevel = 1; // Level 1: -1%
                    }
                }

                return [
                    'symbol' => $pos->symbol,
                    'side' => $pos->side,
                    'entry_price' => $entryPrice,
                    'current_price' => $currentPrice,
                    'quantity' => $pos->quantity,
                    'leverage' => $pos->leverage,
                    'position_size' => $positionSize,
                    'notional_size' => $notionalSize,
                    'pnl' => $pnl,
                    'pnl_percent' => $pnlPercent,
                    'unrealized_pnl' => $pos->unrealized_pnl ?? $pnl,
                    'opened_at' => $pos->opened_at?->diffForHumans(),
                    'price_updated_at' => $pos->price_updated_at?->diffForHumans() ?? 'Never',
                    'liquidation_price' => $pos->liquidation_price,
                    'trailing_level' => $trailingLevel,
                    'targets' => [
                        'profit_target' => $profitTarget,
                        'stop_loss' => $stopLoss,
                        'distance_to_profit_pct' => $distanceToProfit,
                        'distance_to_stop_pct' => $distanceToStop,
                        'profit_needed' => $profitNeeded,
                        'stop_distance' => $stopDistance,
                    ],
                ];
            });

            // Calculate ROI based on actual profit (total P&L) rather than total account value
            $totalUnrealizedPnl = $positions->sum('pnl');
            $totalRealizedPnl = Position::where('is_open', false)->sum('realized_pnl');
            $totalProfit = $totalUnrealizedPnl + $totalRealizedPnl;
            
            $roi = $initialCapital > 0
                ? ($totalProfit / $initialCapital) * 100
                : 0;

            // Get active positions
            $positions = Position::active()->get()->map(function ($pos) {
                $currentPrice = (float) $pos->current_price;
                $entryPrice = (float) $pos->entry_price;

                // Calculate PNL
                $priceDiff = $currentPrice - $entryPrice;
                if ($pos->side === 'short') {
                    $priceDiff = -$priceDiff;
                }
                $pnl = $priceDiff * $pos->quantity * $pos->leverage;
                $pnlPercent = ($priceDiff / $entryPrice) * 100 * $pos->leverage;

                // Get exit plan targets
                $exitPlan = $pos->exit_plan ?? [];
                $profitTarget = isset($exitPlan['profit_target']) ? (float) $exitPlan['profit_target'] : null;
                $stopLoss = isset($exitPlan['stop_loss']) ? (float) $exitPlan['stop_loss'] : null;

                // Calculate distance to targets (handle SHORT positions)
                $distanceToProfit = null;
                $distanceToStop = null;
                $profitNeeded = null;
                $stopDistance = null;

                if ($profitTarget) {
                    if ($pos->side === 'short') {
                        // SHORT: profit target is BELOW current price
                        $distanceToProfit = (($currentPrice - $profitTarget) / $currentPrice) * 100;
                        $profitNeeded = $currentPrice - $profitTarget;
                    } else {
                        // LONG: profit target is ABOVE current price
                        $distanceToProfit = (($profitTarget - $currentPrice) / $currentPrice) * 100;
                        $profitNeeded = $profitTarget - $currentPrice;
                    }
                }

                if ($stopLoss) {
                    if ($pos->side === 'short') {
                        // SHORT: stop loss is ABOVE current price
                        $distanceToStop = (($stopLoss - $currentPrice) / $currentPrice) * 100;
                        $stopDistance = $stopLoss - $currentPrice;
                    } else {
                        // LONG: stop loss is BELOW current price
                        $distanceToStop = (($currentPrice - $stopLoss) / $currentPrice) * 100;
                        $stopDistance = $currentPrice - $stopLoss;
                    }
                }

                // Calculate position size (how much $ invested)
                $positionSize = $pos->quantity * $entryPrice;
                $notionalSize = $pos->notional_usd ?? ($positionSize * $pos->leverage);

                // Detect trailing stop level (handle SHORT positions)
                $trailingLevel = null;
                if ($stopLoss && $entryPrice > 0) {
                    if ($pos->side === 'short') {
                        // SHORT: stop loss below entry = profit locked
                        $stopDiff = (($entryPrice - $stopLoss) / $entryPrice) * 100;
                    } else {
                        // LONG: stop loss above entry = profit locked
                        $stopDiff = (($stopLoss - $entryPrice) / $entryPrice) * 100;
                    }

                    if ($stopDiff >= 7) {
                        $trailingLevel = 4; // Level 4: Stop at +8%
                    } elseif ($stopDiff >= 4) {
                        $trailingLevel = 3; // Level 3: Stop at +5%
                    } elseif ($stopDiff >= 1.5) {
                        $trailingLevel = 2; // Level 2: +2%
                    } elseif ($stopDiff >= -0.5 && $stopDiff <= 0.5) {
                        $trailingLevel = 1; // Level 1: -1%
                    }
                }

                return [
                    'symbol' => $pos->symbol,
                    'side' => $pos->side,
                    'entry_price' => $entryPrice,
                    'current_price' => $currentPrice,
                    'quantity' => $pos->quantity,
                    'leverage' => $pos->leverage,
                    'position_size' => $positionSize,
                    'notional_size' => $notionalSize,
                    'pnl' => $pnl,
                    'pnl_percent' => $pnlPercent,
                    'unrealized_pnl' => $pos->unrealized_pnl ?? $pnl,
                    'opened_at' => $pos->opened_at?->diffForHumans(),
                    'price_updated_at' => $pos->price_updated_at?->diffForHumans() ?? 'Never',
                    'liquidation_price' => $pos->liquidation_price,
                    'trailing_level' => $trailingLevel,
                    'targets' => [
                        'profit_target' => $profitTarget,
                        'stop_loss' => $stopLoss,
                        'distance_to_profit_pct' => $distanceToProfit,
                        'distance_to_stop_pct' => $distanceToStop,
                        'profit_needed' => $profitNeeded,
                        'stop_distance' => $stopDistance,
                    ],
                ];
            });

            // Get closed positions (last 10)
            $closedPositions = Position::where('is_open', false)
                ->orderBy('closed_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($pos) {
                    $pnl = (float) ($pos->realized_pnl ?? 0);
                    $entryPrice = (float) $pos->entry_price;

                    // Calculate PNL percentage with leverage
                    $pnlPercent = 0;
                    if ($entryPrice > 0 && $pos->quantity > 0) {
                        $priceDiff = $pnl / ($pos->quantity * ($pos->leverage ?? 1));
                        $pnlPercent = ($priceDiff / $entryPrice) * 100 * ($pos->leverage ?? 1);
                    }

                    // Calculate position size (how much $ invested)
                    $positionSize = $pos->quantity * $entryPrice;
                    $notionalSize = $pos->notional_usd ?? ($positionSize * ($pos->leverage ?? 1));

                    return [
                        'symbol' => $pos->symbol,
                        'side' => $pos->side,
                        'entry_price' => $entryPrice,
                        'quantity' => $pos->quantity,
                        'leverage' => $pos->leverage ?? 1,
                        'position_size' => $positionSize,
                        'notional_size' => $notionalSize,
                        'pnl' => $pnl,
                        'pnl_percent' => $pnlPercent,
                        'closed_at' => $pos->closed_at?->diffForHumans(),
                        'close_reason' => $pos->close_reason,
                        'close_metadata' => $pos->close_metadata,
                    ];
                });

            // Calculate stats
            $totalPnl = $positions->sum('pnl');

            // Detailed closed positions stats
            $closedPositionsAll = Position::where('is_open', false)->get();
            $totalRealizedPnl = $closedPositionsAll->sum('realized_pnl');
            $totalWins = $closedPositionsAll->where('realized_pnl', '>', 0)->count();
            $totalLosses = $closedPositionsAll->where('realized_pnl', '<', 0)->count();
            $totalWinAmount = $closedPositionsAll->where('realized_pnl', '>', 0)->sum('realized_pnl');
            $totalLossAmount = abs($closedPositionsAll->where('realized_pnl', '<', 0)->sum('realized_pnl'));
            $avgWin = $totalWins > 0 ? $totalWinAmount / $totalWins : 0;
            $avgLoss = $totalLosses > 0 ? $totalLossAmount / $totalLosses : 0;
            $profitFactor = $totalLossAmount > 0 ? $totalWinAmount / $totalLossAmount : 0;

            // Get AI logs
            $aiLogs = AiLog::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    $decisions = $log->decision['decisions'] ?? [];
                    return [
                        'provider' => $log->provider,
                        'model' => $log->model,
                        'decisions_count' => count($decisions),
                        'decisions' => $decisions,
                        'created_at' => $log->created_at->diffForHumans(),
                    ];
                });

            $lastAiRun = AiLog::latest()->first();

            // Get AI model information from latest log or config
            $aiProvider = $lastAiRun?->provider ?? config('app.ai_provider', 'openrouter');
            $aiModel = $lastAiRun?->model ?? config('openrouter.model', 'deepseek/deepseek-chat-v3.1');

            // Calculate AI performance metrics
            $aiPerformance = $this->calculateAiPerformance();

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'total_value' => $totalValue,
                        'cash' => $cash,
                        'initial_capital' => $initialCapital,
                        'roi' => $roi,
                        'total_pnl' => $totalProfit,
                        'realized_pnl' => $totalRealizedPnl,
                    ],
                    'positions' => $positions,
                    'closed_positions' => $closedPositions,
                    'ai_logs' => $aiLogs,
                    'last_ai_run' => $lastAiRun ? $lastAiRun->created_at->diffForHumans() : 'Never',
                    'ai_provider' => $aiProvider,
                    'ai_model' => $aiModel,
                    'ai_performance' => $aiPerformance,
                    'stats' => [
                        'open_positions' => $positions->count(),
                        'total_trades' => $totalWins + $totalLosses,
                        'wins' => $totalWins,
                        'losses' => $totalLosses,
                        'win_rate' => $this->calculateWinRate(),
                        'total_win_amount' => $totalWinAmount,
                        'total_loss_amount' => $totalLossAmount,
                        'avg_win' => $avgWin,
                        'avg_loss' => $avgLoss,
                        'profit_factor' => $profitFactor,
                        'net_profit' => $totalRealizedPnl,
                        'bot_enabled' => BotSetting::get('bot_enabled', false),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function calculateWinRate(): float
    {
        $closed = Position::where('is_open', false)->get();
        if ($closed->count() === 0) return 0;

        $wins = $closed->where('realized_pnl', '>', 0)->count();
        return ($wins / $closed->count()) * 100;
    }

    /**
     * Get account balance from Binance (OLD - DEPRECATED)
     */
    public function getBalance(bool $raw = false)
    {
        try {
            $binance = $this->getBinanceService();
            $balance = $binance->fetchBalance();

            $usdtBalance = $balance['total']['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];

            // Calculate additional metrics
            $positions = Position::active()->get();
            $totalPositionValue = 0;
            foreach ($positions as $position) {
                $totalPositionValue += abs($position->quantity * $position->current_price);
            }

            // Calculate P&L Today (simplified: sum of realized P&L from trades today)
            $pnlToday = Trade::whereDate('created_at', today())
                             ->where('status', 'filled')
                             ->sum('pnl');

            $data = [
                'success' => true,
                'data' => [
                    'free' => $usdtBalance['free'] ?? 0,
                    'used' => $usdtBalance['used'] ?? 0,
                    'total' => $usdtBalance['total'] ?? 0,
                    'total_position_value' => $totalPositionValue,
                    'net_worth' => ($usdtBalance['total'] ?? 0) + $totalPositionValue,
                    'pnl_today' => $pnlToday,
                    'open_positions_count' => $positions->count(),
                ]
            ];

            if ($raw) {
                return $data;
            }

            return response()->json($data);
        } catch (Exception $e) {
            if ($raw) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch balance',
                    'error' => $e->getMessage()
                ];
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent purchases (buys) from Binance
     */
    public function getPurchases(bool $raw = false)
    {
        try {
            // First, try to get recent trades from the database
            $dbTrades = Trade::where('side', 'buy')
                             ->where('status', 'filled')
                             ->orderBy('created_at', 'desc')
                             ->limit(10)
                             ->get();

            // Format the database trades
            $recentBuys = $dbTrades->map(function ($trade) {
                return [
                    'id' => $trade->order_id,
                    'symbol' => $trade->symbol,
                    'amount' => $trade->amount,
                    'price' => $trade->price,
                    'total' => $trade->cost,
                    'timestamp' => $trade->created_at->toISOString(),
                    'side' => 'buy'
                ];
            })->toArray();

            // If not using the live Binance mode, only return database data
            $mode = config('app.trading_mode', env('TRADING_MODE', 'mock'));
            if ($mode !== 'live') {
                $data = [
                    'success' => true,
                    'data' => $recentBuys
                ];
                if ($raw) return $data;
                return response()->json($data);
            }

            // For live mode, get from Binance API and merge with database data
            $binance = $this->getBinanceService();
            $exchange = $binance->getExchange();

            try {
                // Fetch recent trades from Binance
                $allTrades = $exchange->fetch_my_trades();
                $apiBuyTrades = array_filter($allTrades, function($trade) {
                    return strtoupper($trade['side'] ?? '') === 'BUY';
                });

                // Format API trades
                $apiBuys = [];
                foreach (array_slice($apiBuyTrades, 0, 10) as $trade) {
                    $apiBuys[] = [
                        'id' => $trade['id'] ?? null,
                        'symbol' => $trade['symbol'] ?? 'N/A',
                        'amount' => $trade['amount'] ?? 0,
                        'price' => $trade['price'] ?? 0,
                        'total' => ($trade['amount'] ?? 0) * ($trade['price'] ?? 0),
                        'timestamp' => date('c', (int)($trade['timestamp'] / 1000)), // Convert ms to seconds
                        'side' => 'buy'
                    ];
                }

                // Merge API and DB data, giving preference to API data if available
                $mergedBuys = array_merge($apiBuys, $recentBuys);

                // Remove duplicates based on ID if available
                $uniqueBuys = [];
                $seenIds = [];

                foreach ($mergedBuys as $trade) {
                    $id = $trade['id'] ?? 'unknown';
                    if (!in_array($id, $seenIds)) {
                        $uniqueBuys[] = $trade;
                        $seenIds[] = $id;
                    }
                }

                // Return only the most recent 10
                $finalBuys = array_slice($uniqueBuys, 0, 10);

                $data = [
                    'success' => true,
                    'data' => $finalBuys
                ];
                if ($raw) return $data;
                return response()->json($data);
            } catch (Exception $apiException) {
                // If API fails, return database data as fallback
                $data = [
                    'success' => true,
                    'data' => $recentBuys
                ];
                if ($raw) return $data;
                return response()->json($data);
            }
        } catch (Exception $e) {
            if ($raw) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch purchases',
                    'error' => $e->getMessage()
                ];
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchases',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent sales (sells) from Binance
     */
    public function getSales(bool $raw = false)
    {
        try {
            // First, try to get recent trades from the database
            $dbTrades = Trade::where('side', 'sell')
                             ->where('status', 'filled')
                             ->orderBy('created_at', 'desc')
                             ->limit(10)
                             ->get();

            // Format the database trades
            $recentSells = $dbTrades->map(function ($trade) {
                return [
                    'id' => $trade->order_id,
                    'symbol' => $trade->symbol,
                    'amount' => $trade->amount,
                    'price' => $trade->price,
                    'total' => $trade->cost,
                    'timestamp' => $trade->created_at->toISOString(),
                    'side' => 'sell'
                ];
            })->toArray();

            // If not using the live Binance mode, only return database data
            $mode = config('app.trading_mode', env('TRADING_MODE', 'mock'));
            if ($mode !== 'live') {
                $data = [
                    'success' => true,
                    'data' => $recentSells
                ];
                if ($raw) return $data;
                return response()->json($data);
            }

            // For live mode, get from Binance API and merge with database data
            $binance = $this->getBinanceService();
            $exchange = $binance->getExchange();

            try {
                // Fetch recent trades from Binance
                $allTrades = $exchange->fetch_my_trades();
                $apiSellTrades = array_filter($allTrades, function($trade) {
                    return strtoupper($trade['side'] ?? '') === 'SELL';
                });

                // Format API trades
                $apiSells = [];
                foreach (array_slice($apiSellTrades, 0, 10) as $trade) {
                    $apiSells[] = [
                        'id' => $trade['id'] ?? null,
                        'symbol' => $trade['symbol'] ?? 'N/A',
                        'amount' => $trade['amount'] ?? 0,
                        'price' => $trade['price'] ?? 0,
                        'total' => ($trade['amount'] ?? 0) * ($trade['price'] ?? 0),
                        'timestamp' => date('c', (int)($trade['timestamp'] / 1000)), // Convert ms to seconds
                        'side' => 'sell'
                    ];
                }

                // Merge API and DB data, giving preference to API data if available
                $mergedSells = array_merge($apiSells, $recentSells);

                // Remove duplicates based on ID if available
                $uniqueSells = [];
                $seenIds = [];

                foreach ($mergedSells as $trade) {
                    $id = $trade['id'] ?? 'unknown';
                    if (!in_array($id, $seenIds)) {
                        $uniqueSells[] = $trade;
                        $seenIds[] = $id;
                    }
                }

                // Return only the most recent 10
                $finalSells = array_slice($uniqueSells, 0, 10);

                $data = [
                    'success' => true,
                    'data' => $finalSells
                ];
                if ($raw) return $data;
                return response()->json($data);
            } catch (Exception $apiException) {
                // If API fails, return database data as fallback
                $data = [
                    'success' => true,
                    'data' => $recentSells
                ];
                if ($raw) return $data;
                return response()->json($data);
            }
        } catch (Exception $e) {
            if ($raw) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch sales',
                    'error' => $e->getMessage()
                ];
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all trade data (balance, purchases, sales)
     */
    public function getAllData()
    {
        $balanceResponse = $this->getBalance()->getData(true);
        $purchasesResponse = $this->getPurchases()->getData(true);
        $salesResponse = $this->getSales()->getData(true);

        $totalPurchases = collect($purchasesResponse['data'])->sum('total');
        $totalSales = collect($salesResponse['data'])->sum('total');
        $netProfit = $totalSales - $totalPurchases;

        $aiLogsData = $this->getAiLogsData();

        return response()->json([
            'balance' => $balanceResponse,
            'purchases' => $purchasesResponse,
            'sales' => $salesResponse,
            'summary' => [
                'total_purchases' => $totalPurchases,
                'total_sales' => $totalSales,
                'net_profit' => $netProfit,
            ],
            'ai_logs' => $aiLogsData['last_ten_logs'],
            'last_ai_run' => $aiLogsData['last_run_at'],
        ]);
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
     * Get dashboard status (matches TradingController format but with AI data)
     */
    public function getStatus()
    {
        try {
            $binance = $this->getBinanceService();
            $balance = $binance->fetchBalance();
            $positions = Position::open()->get();
            
            // Get AI logs
            $aiLogs = AiLog::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    $decisions = $log->decision['decisions'] ?? [];
                    return [
                        'provider' => $log->provider,
                        'decisions_count' => count($decisions),
                        'decisions' => $decisions,
                        'created_at' => $log->created_at->diffForHumans(),
                    ];
                });

            $lastAiRun = AiLog::latest()->first();
            
            // Get AI model information
            $aiModel = BotSetting::get('ai_model') 
                        ?? config('services.openrouter.model') 
                        ?? config('deepseek.model') 
                        ?? config('openai.model')
                        ?? 'unknown';

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'total_value' => $balance['USDT']['total'] ?? 0,
                        'cash' => $balance['USDT']['free'] ?? 0,
                        'initial_capital' => BotSetting::get('initial_capital', config('app.initial_capital', 1000)),
                        'roi' => 0, // Calculate ROI if needed
                        'total_pnl' => 0, // Calculate PNL if needed
                        'realized_pnl' => 0, // Calculate realized PNL if needed
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
                    'balance' => [
                        'free' => $balance['USDT']['free'] ?? 0,
                        'total' => $balance['USDT']['total'] ?? 0,
                    ],
                    'settings' => [
                        'bot_enabled' => BotSetting::get('bot_enabled', true),
                        'use_ai' => BotSetting::get('use_ai', false),
                        'max_leverage' => BotSetting::get('max_leverage', 2),
                        'position_size' => BotSetting::get('position_size_usdt', 100),
                    ],
                    'ai_logs' => $aiLogs,
                    'last_ai_run' => $lastAiRun ? $lastAiRun->created_at->diffForHumans() : 'Never',
                    'model' => $aiModel,
                    'stats' => [
                        'open_positions' => $positions->count(),
                        'win_rate' => $this->calculateWinRate(),
                        'bot_enabled' => BotSetting::get('bot_enabled', false),
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
    
    private function getAiLogsData(): array
    {
        $lastTenAiLogs = AiLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'provider' => $log->provider,
                    'decision' => $log->decision,
                    'created_at' => $log->created_at->diffForHumans(),
                ];
            });

        $lastAiRun = AiLog::orderBy('created_at', 'desc')->first();

        return [
            'last_ten_logs' => $lastTenAiLogs,
            'last_run_at' => $lastAiRun ? $lastAiRun->created_at->diffForHumans() : 'Never',
        ];
    }

    /**
     * Calculate AI performance metrics
     */
    private function calculateAiPerformance(): array
    {
        // Get all closed positions with AI confidence scores
        $aiTrades = Position::where('is_open', false)
            ->whereNotNull('confidence')
            ->get();

        if ($aiTrades->isEmpty()) {
            return [
                'total_ai_trades' => 0,
                'avg_confidence' => 0,
                'high_confidence_win_rate' => 0,
                'medium_confidence_win_rate' => 0,
                'low_confidence_win_rate' => 0,
                'avg_confidence_wins' => 0,
                'avg_confidence_losses' => 0,
                'best_performing_confidence_range' => 'N/A',
            ];
        }

        // Split by confidence levels
        $highConfidence = $aiTrades->where('confidence', '>=', 0.80);
        $mediumConfidence = $aiTrades->where('confidence', '>=', 0.70)->where('confidence', '<', 0.80);
        $lowConfidence = $aiTrades->where('confidence', '<', 0.70);

        // Calculate win rates per confidence level
        $highConfWinRate = $highConfidence->count() > 0
            ? ($highConfidence->where('realized_pnl', '>', 0)->count() / $highConfidence->count()) * 100
            : 0;

        $mediumConfWinRate = $mediumConfidence->count() > 0
            ? ($mediumConfidence->where('realized_pnl', '>', 0)->count() / $mediumConfidence->count()) * 100
            : 0;

        $lowConfWinRate = $lowConfidence->count() > 0
            ? ($lowConfidence->where('realized_pnl', '>', 0)->count() / $lowConfidence->count()) * 100
            : 0;

        // Average confidence for wins vs losses
        $wins = $aiTrades->where('realized_pnl', '>', 0);
        $losses = $aiTrades->where('realized_pnl', '<', 0);

        $avgConfidenceWins = $wins->count() > 0 ? $wins->avg('confidence') : 0;
        $avgConfidenceLosses = $losses->count() > 0 ? $losses->avg('confidence') : 0;

        // Determine best performing confidence range
        $bestRange = 'N/A';
        $maxWinRate = max($highConfWinRate, $mediumConfWinRate, $lowConfWinRate);

        if ($maxWinRate > 0) {
            if ($highConfWinRate === $maxWinRate) {
                $bestRange = 'High (≥80%)';
            } elseif ($mediumConfWinRate === $maxWinRate) {
                $bestRange = 'Medium (70-79%)';
            } else {
                $bestRange = 'Low (<70%)';
            }
        }

        return [
            'total_ai_trades' => $aiTrades->count(),
            'avg_confidence' => round($aiTrades->avg('confidence') * 100, 2),
            'high_confidence_win_rate' => round($highConfWinRate, 2),
            'high_confidence_trades' => $highConfidence->count(),
            'medium_confidence_win_rate' => round($mediumConfWinRate, 2),
            'medium_confidence_trades' => $mediumConfidence->count(),
            'low_confidence_win_rate' => round($lowConfWinRate, 2),
            'low_confidence_trades' => $lowConfidence->count(),
            'avg_confidence_wins' => round($avgConfidenceWins * 100, 2),
            'avg_confidence_losses' => round($avgConfidenceLosses * 100, 2),
            'best_performing_confidence_range' => $bestRange,
            'confidence_correlation' => round(($avgConfidenceWins - $avgConfidenceLosses) * 100, 2),
        ];
    }
}
