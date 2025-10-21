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

            // Calculate ROI
            $roi = $initialCapital > 0
                ? (($totalValue - $initialCapital) / $initialCapital) * 100
                : 0;

            // Get active positions
            $positions = Position::active()->get()->map(function ($pos) {
                $currentPrice = $pos->current_price;
                $pnl = ($currentPrice - $pos->entry_price) * $pos->quantity;
                $pnlPercent = (($currentPrice - $pos->entry_price) / $pos->entry_price) * 100;

                return [
                    'symbol' => $pos->symbol,
                    'side' => $pos->side,
                    'entry_price' => $pos->entry_price,
                    'current_price' => $currentPrice,
                    'quantity' => $pos->quantity,
                    'leverage' => $pos->leverage,
                    'pnl' => $pnl,
                    'pnl_percent' => $pnlPercent,
                    'opened_at' => $pos->opened_at?->diffForHumans(),
                ];
            });

            // Get closed positions (last 10)
            $closedPositions = Position::where('is_open', false)
                ->orderBy('closed_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($pos) {
                    return [
                        'symbol' => $pos->symbol,
                        'side' => $pos->side,
                        'entry_price' => $pos->entry_price,
                        'pnl' => $pos->realized_pnl ?? 0,
                        'closed_at' => $pos->closed_at?->diffForHumans(),
                    ];
                });

            // Calculate stats
            $totalPnl = $positions->sum('pnl');
            $totalRealizedPnl = Position::where('is_open', false)->sum('realized_pnl');

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

            // Get AI model information from settings
            $aiModel = BotSetting::get('ai_model') 
                        ?? config('services.openrouter.model') 
                        ?? config('deepseek.model') 
                        ?? config('openai.model')
                        ?? 'unknown';
            
            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'total_value' => $totalValue,
                        'cash' => $cash,
                        'initial_capital' => $initialCapital,
                        'roi' => $roi,
                        'total_pnl' => $totalPnl,
                        'realized_pnl' => $totalRealizedPnl,
                    ],
                    'positions' => $positions,
                    'closed_positions' => $closedPositions,
                    'ai_logs' => $aiLogs,
                    'last_ai_run' => $lastAiRun ? $lastAiRun->created_at->diffForHumans() : 'Never',
                    'model' => $aiModel,
                    'stats' => [
                        'open_positions' => $positions->count(),
                        'win_rate' => $this->calculateWinRate(),
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
}
