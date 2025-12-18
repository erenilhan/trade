<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\Position;
use App\Models\Trade;
use App\Models\DailyStat;
use App\Services\BinanceService;
use App\Services\MarketDataService;
use App\Models\AiLog;
use App\Services\MockBinanceService;
use Exception;
use Illuminate\Http\Request;

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
    public function getData(Request $request)
    {
        try {
            $range = $request->input('range', '7');
            $dateFrom = null;
            if ($range !== 'all') {
                $dateFrom = now()->subDays((int)$range);
            }

            // Get balance
            $balance = $this->binance->fetchBalance();
            $totalValue = $balance['USDT']['total'] ?? 0;
            $cash = $balance['USDT']['free'] ?? 0;

            // Get initial investment
            $initialCapital = BotSetting::get('initial_capital', config('app.initial_capital', 1000));

            // Get active positions
            $activePositions = Position::active()->get()->map(function ($pos) {
                $currentPrice = (float) $pos->current_price;
                $entryPrice = (float) $pos->entry_price;

                // Calculate PNL
                $priceDiff = $currentPrice - $entryPrice;
                if ($pos->side === 'short') {
                    $priceDiff = -$priceDiff;
                }
                $pnl = $priceDiff * $pos->quantity * $pos->leverage;
                $pnlPercent = ($entryPrice > 0) ? ($priceDiff / $entryPrice) * 100 * $pos->leverage : 0;

                // Get exit plan targets
                $exitPlan = $pos->exit_plan ?? [];
                $profitTarget = isset($exitPlan['profit_target']) ? (float) $exitPlan['profit_target'] : null;
                $stopLoss = isset($exitPlan['stop_loss']) ? (float) $exitPlan['stop_loss'] : null;

                // Calculate position size (how much $ invested)
                $positionSize = $pos->quantity * $entryPrice;
                $notionalSize = $pos->notional_usd ?? ($positionSize * $pos->leverage);

                // Detect trailing stop level
                $trailingLevel = null;
                if ($stopLoss && $entryPrice > 0) {
                    $stopDiff = (($pos->side === 'short' ? $entryPrice - $stopLoss : $stopLoss - $entryPrice) / $entryPrice) * 100;
                    if ($stopDiff >= 7) $trailingLevel = 4;
                    elseif ($stopDiff >= 4) $trailingLevel = 3;
                    elseif ($stopDiff >= 1.5) $trailingLevel = 2;
                    elseif ($stopDiff >= -0.5) $trailingLevel = 1;
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
                    'opened_at' => $pos->opened_at?->diffForHumans(),
                    'price_updated_at' => $pos->price_updated_at?->diffForHumans() ?? 'Never',
                    'trailing_level' => $trailingLevel,
                    'confidence' => $pos->confidence,
                    'ai_reasoning' => $this->getAiReasoningForPosition($pos->symbol, $pos->opened_at),
                    'exit_plan' => $pos->exit_plan,
                ];
            });

            // Get closed positions (last 10 in range)
            $closedPositionsQuery = Position::where('is_open', false)->orderBy('closed_at', 'desc');
            if ($dateFrom) {
                $closedPositionsQuery->where('closed_at', '>=', $dateFrom);
            }
            $closedPositions = $closedPositionsQuery->limit(10)->get()->map(function ($pos) {
                 return [
                    'symbol' => $pos->symbol,
                    'side' => $pos->side,
                    'entry_price' => (float)$pos->entry_price,
                    'quantity' => $pos->quantity,
                    'leverage' => $pos->leverage ?? 1,
                    'pnl' => (float)($pos->realized_pnl ?? 0),
                    'closed_at' => $pos->closed_at?->diffForHumans(),
                    'close_reason' => $pos->close_reason,
                ];
            });

            // Detailed closed positions stats for the period
            $closedPositionsAllQuery = Position::where('is_open', false);
            if ($dateFrom) {
                $closedPositionsAllQuery->where('closed_at', '>=', $dateFrom);
            }
            $closedPositionsAll = $closedPositionsAllQuery->get();

            $totalRealizedPnl = $closedPositionsAll->sum('realized_pnl');
            $totalWins = $closedPositionsAll->where('realized_pnl', '>', 0)->count();
            $totalLosses = $closedPositionsAll->where('realized_pnl', '<=', 0)->count();
            $totalWinAmount = $closedPositionsAll->where('realized_pnl', '>', 0)->sum('realized_pnl');
            $totalLossAmount = abs($closedPositionsAll->where('realized_pnl', '<=', 0)->sum('realized_pnl'));
            $avgWin = $totalWins > 0 ? $totalWinAmount / $totalWins : 0;
            $avgLoss = $totalLosses > 0 ? $totalLossAmount / $totalLosses : 0;
            $profitFactor = $totalLossAmount > 0 ? $totalWinAmount / $totalLossAmount : 0;
            
            // ROI Calculation
            $totalUnrealizedPnl = $activePositions->sum('pnl');
            $totalProfit = $totalUnrealizedPnl + $totalRealizedPnl;
            $roi = $initialCapital > 0 ? ($totalProfit / $initialCapital) * 100 : 0;

            // Get AI logs for the period
            $aiLogsQuery = AiLog::orderBy('created_at', 'desc');
            if ($dateFrom) {
                $aiLogsQuery->where('created_at', '>=', $dateFrom);
            }
            $aiLogs = $aiLogsQuery->limit(10)->get()->map(function ($log) {
                return [
                    'provider' => $log->provider,
                    'model' => $log->model,
                    'decisions' => $log->decision['decisions'] ?? [],
                    'created_at' => $log->created_at->diffForHumans(),
                ];
            });

            $lastAiRun = AiLog::latest()->first();
            $aiProvider = $lastAiRun?->provider ?? config('app.ai_provider', 'openrouter');
            $aiModel = $lastAiRun?->model ?? config('openrouter.model', 'deepseek/deepseek-chat-v3.1');
            
            $aiPerformance = $this->calculateAiPerformance($dateFrom);

            // PNL Chart Data
            $pnlChartDays = 7;
            if ($range === 'all') {
                $firstTrade = Position::orderBy('opened_at', 'asc')->first();
                $pnlChartDays = $firstTrade ? $firstTrade->opened_at->diffInDays(now()) + 1 : 7;
            } else {
                $pnlChartDays = (int)$range;
            }
            $pnlChartData = $this->getPnlChartData($pnlChartDays);


            // Other data points...
            $riskManagement = $this->getRiskManagementStatus();
            $marketIndicators = $this->getMarketIndicators();
            $holdReasons = $this->getHoldReasons($lastAiRun);

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
                    'positions' => $activePositions,
                    'closed_positions' => $closedPositions,
                    'ai_logs' => $aiLogs,
                    'hold_reasons' => $holdReasons,
                    'risk_management' => $riskManagement,
                    'last_ai_run' => $lastAiRun ? $lastAiRun->created_at->diffForHumans() : 'Never',
                    'ai_provider' => $aiProvider,
                    'ai_model' => $aiModel,
                    'ai_performance' => $aiPerformance,
                    'pnl_chart' => $pnlChartData,
                    'market_indicators' => $marketIndicators,
                    'stats' => [
                        'open_positions' => $activePositions->count(),
                        'total_trades' => $totalWins + $totalLosses,
                        'wins' => $totalWins,
                        'losses' => $totalLosses,
                        'win_rate' => $this->calculateWinRate($dateFrom),
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

    private function calculateWinRate($dateFrom = null): float
    {
        $query = Position::where('is_open', false);
        if ($dateFrom) {
            $query->where('closed_at', '>=', $dateFrom);
        }
        $closed = $query->get();

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
    private function calculateAiPerformance($dateFrom = null): array
    {
        $query = Position::where('is_open', false)->whereNotNull('confidence');
        if ($dateFrom) {
            $query->where('closed_at', '>=', $dateFrom);
        }
        $aiTrades = $query->get();

        if ($aiTrades->isEmpty()) {
            return [
                'total_ai_trades' => 0, 'avg_confidence' => 0, 'high_confidence_win_rate' => 0,
                'medium_confidence_win_rate' => 0, 'low_confidence_win_rate' => 0, 'avg_confidence_wins' => 0,
                'avg_confidence_losses' => 0, 'best_performing_confidence_range' => 'N/A',
            ];
        }

        $highConfidence = $aiTrades->where('confidence', '>=', 0.80);
        $mediumConfidence = $aiTrades->whereBetween('confidence', [0.70, 0.80]);
        $lowConfidence = $aiTrades->where('confidence', '<', 0.70);

        $calcWinRate = function ($collection) {
            if ($collection->isEmpty()) return 0;
            return ($collection->where('realized_pnl', '>', 0)->count() / $collection->count()) * 100;
        };

        $wins = $aiTrades->where('realized_pnl', '>', 0);
        $losses = $aiTrades->where('realized_pnl', '<=', 0);

        return [
            'total_ai_trades' => $aiTrades->count(),
            'avg_confidence' => round($aiTrades->avg('confidence') * 100, 2),
            'high_confidence_win_rate' => round($calcWinRate($highConfidence), 2),
            'medium_confidence_win_rate' => round($calcWinRate($mediumConfidence), 2),
            'low_confidence_win_rate' => round($calcWinRate($lowConfidence), 2),
            'avg_confidence_wins' => round(($wins->avg('confidence') ?? 0) * 100, 2),
            'avg_confidence_losses' => round(($losses->avg('confidence') ?? 0) * 100, 2),
        ];
    }

    /**
     * Check for cluster loss cooldown (consecutive losses trigger trading pause)
     */
    private function checkClusterLossCooldown(): ?string
    {
        // Check if manual cooldown override is enabled
        if (BotSetting::get('manual_cooldown_override', false)) {
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

    /**
     * Get PNL chart data for last N days
     */
    private function getPnlChartData(int $days = 7): array
    {
        if ($days > 1000) $days = 1000; // Safety cap

        $startDate = now()->subDays($days - 1)->startOfDay();

        $pnlByDay = Position::where('is_open', false)
            ->where('closed_at', '>=', $startDate)
            ->selectRaw('DATE(closed_at) as date, SUM(realized_pnl) as pnl')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $pnlBefore = Position::where('is_open', false)
            ->where('closed_at', '<', $startDate)
            ->sum('realized_pnl');

        $chartData = [];
        $cumulativePnl = $pnlBefore;

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');
            
            $dailyPnl = $pnlByDay->get($dateString)->pnl ?? 0;
            $cumulativePnl += $dailyPnl;

            $chartData[] = [
                'date' => $date->format('M d'),
                'daily_pnl' => round($dailyPnl, 2),
                'cumulative_pnl' => round($cumulativePnl, 2),
            ];
        }

        return $chartData;
    }

    private function getRiskManagementStatus(): array
    {
        $sleepModeConfig = config('trading.sleep_mode');
        $currentHourUTC = now()->utc()->hour;
        $startHour = $sleepModeConfig['start_hour'];
        $endHour = $sleepModeConfig['end_hour'];

        $inSleepMode = ($startHour > $endHour)
            ? ($currentHourUTC >= $startHour || $currentHourUTC < $endHour)
            : ($currentHourUTC >= $startHour && $currentHourUTC < $endHour);

        $dailyStat = DailyStat::today();

        return [
            'sleep_mode' => [
                'enabled' => $sleepModeConfig['enabled'],
                'active' => $inSleepMode,
                'hours_utc' => "{$startHour}:00-{$endHour}:00",
            ],
            'daily_drawdown' => [
                'enabled' => config('trading.daily_max_drawdown.enabled'),
                'limit_hit' => $dailyStat->max_drawdown_hit,
            ],
            'cluster_loss' => [
                'enabled' => config('trading.cluster_loss_cooldown.enabled'),
                'in_cooldown' => $this->checkClusterLossCooldown() !== null,
            ],
        ];
    }
    
    private function getHoldReasons($lastAiRun): array
    {
        $holdReasons = [];
        if ($lastAiRun && isset($lastAiRun->decision['decisions'])) {
            foreach ($lastAiRun->decision['decisions'] as $decision) {
                if ($decision['action'] === 'hold') {
                    $holdReasons[] = [
                        'symbol' => $decision['symbol'],
                        'confidence' => round(($decision['confidence'] ?? 0) * 100),
                        'reason' => $decision['reasoning'] ?? 'No reason provided',
                    ];
                }
            }
        }
        return $holdReasons;
    }

    /**
     * Get current market indicators for all trading symbols
     */
    private function getMarketIndicators(): array
    {
        $symbols = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT'];
        $indicators = [];

        foreach ($symbols as $symbol) {
            // Get latest 3m timeframe data using model's static method
            $latest3m = \App\Models\MarketData::getLatest($symbol, '3m');

            // Get latest 4h timeframe data
            $latest4h = \App\Models\MarketData::getLatest($symbol, '4h');

            if ($latest3m) {
                // Get ADX from indicators JSON if available
                $indicatorsData = $latest4h->indicators ?? [];
                $adx = $indicatorsData['adx'] ?? null;

                $indicators[] = [
                    'symbol' => $symbol,
                    'price' => $latest3m->price,
                    'ema20' => $latest3m->ema20,
                    'ema50' => $latest3m->ema50,
                    'macd' => $latest3m->macd,
                    'macd_signal' => $indicatorsData['macd_signal'] ?? 0,
                    'rsi' => $latest3m->rsi7,
                    'rsi14' => $latest3m->rsi14,
                    'atr' => $latest3m->atr3,
                    'volume' => $latest3m->volume,
                    'funding_rate' => $latest3m->funding_rate,
                    'open_interest' => $latest3m->open_interest,
                    'trend_4h' => $latest4h ? [
                        'ema20' => $latest4h->ema20,
                        'ema50' => $latest4h->ema50,
                        'adx' => $adx,
                    ] : null,
                    'updated_at' => $latest3m->data_timestamp?->diffForHumans() ?? 'Never',
                ];
            }
        }

        return $indicators;
    }

    /**
     * Get AI reasoning for a specific position from recent logs
     */
    private function getAiReasoningForPosition(string $symbol, $openedAt): ?string
    {
        // Look for AI logs around the time the position was opened (±10 minutes)
        $timeWindow = 10; // minutes
        $startTime = clone $openedAt;
        $startTime->subMinutes($timeWindow);
        $endTime = clone $openedAt;
        $endTime->addMinutes($timeWindow * 2);

        $aiLog = \App\Models\AiLog::whereBetween('created_at', [$startTime, $endTime])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$aiLog) {
            return null;
        }

        try {
            $response = json_decode($aiLog->response, true);
            $content = $response['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                return null;
            }

            $decisions = json_decode($content, true);
            
            if (!isset($decisions['decisions'])) {
                return null;
            }

            // Find the decision for this symbol
            foreach ($decisions['decisions'] as $decision) {
                if ($decision['symbol'] === $symbol && in_array($decision['action'], ['buy', 'sell'])) {
                    return $decision['reasoning'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
