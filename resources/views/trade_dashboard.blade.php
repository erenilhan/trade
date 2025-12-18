<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Dashboard</title>

    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Enable dark mode support -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0a0e27',
                            800: '#18181b',
                            700: '#27272a',
                            600: '#3f3f46',
                            500: '#52525b',
                            400: '#a1a1aa',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tab-content.active {
            animation: fadeIn 0.3s ease-in-out;
        }
        #date-range-filter button.active-range {
            background-color: #2563eb; /* bg-blue-600 */
            color: white;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-dark-900 text-gray-200 min-h-screen">
    <div id="loading-overlay" class="fixed inset-0 z-[100] bg-dark-900 bg-opacity-75 flex items-center justify-center hidden">
        <div class="flex items-center space-x-3 p-4 bg-dark-800 rounded-lg shadow-lg border border-dark-700">
            <svg class="animate-spin h-6 w-6 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-white font-semibold">Loading Data...</span>
        </div>
    </div>
    <!-- Header -->
    <header class="bg-dark-800 border-b border-dark-700 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Logo & Title -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <span class="text-xl font-bold">🤖</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">AI Trading Bot</h1>
                        <p class="text-xs text-gray-400">Multi-Coin Futures</p>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Bot Status -->
                    <div class="relative group">
                        <div class="status-badge inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-red-900/50 text-red-300" id="bot-status">
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                            <span class="hidden sm:inline">Offline</span>
                        </div>
                        <div id="bot-status-tooltip" class="absolute bottom-full right-0 mb-2 px-3 py-2 text-sm text-gray-200 bg-dark-800 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none z-50 border border-dark-600 whitespace-nowrap">
                            Last run: Not available
                        </div>
                    </div>

                    <!-- AI Model Badge -->
                    <div class="hidden md:flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-indigo-900/50 text-indigo-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <span id="ai-model-text" class="text-xs">Loading...</span>
                    </div>

                    <!-- Admin Link -->
                    @auth
                    <a href="/admin" class="inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-purple-900/50 text-purple-300 hover:bg-purple-900/70 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="hidden sm:inline">Admin</span>
                    </a>
                    @endauth

                    <!-- Analytics Link -->
                    <a href="{{ route('analytics') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-indigo-900/50 text-indigo-300 hover:bg-indigo-900/70 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span class="hidden sm:inline">Analytics</span>
                    </a>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex space-x-1 overflow-x-auto pb-px">
                <button class="tab-button active px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="overview">
                    📊 Overview
                </button>
                <button class="tab-button px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="performance">
                    📈 Performance
                </button>
                <button class="tab-button px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="markets">
                    📡 Markets
                </button>
                <button class="tab-button px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="ai">
                    🤖 AI Decisions
                </button>
                <button class="tab-button px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="system">
                    ⚙️ System
                </button>
                <button class="tab-button px-4 py-3 text-sm font-medium text-gray-400 hover:text-white transition-colors whitespace-nowrap" data-tab="risk">
                    🛡️ Risk
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Date Range Filter -->
        <div class="mb-4 flex justify-end">
            <div id="date-range-filter" class="flex items-center bg-dark-800 border border-dark-700 rounded-lg p-1 space-x-1">
                <button data-range="7" class="px-3 py-1 text-sm rounded-md text-gray-300 hover:bg-dark-600 transition-colors">7D</button>
                <button data-range="30" class="px-3 py-1 text-sm rounded-md text-gray-300 hover:bg-dark-600 transition-colors">30D</button>
                <button data-range="90" class="px-3 py-1 text-sm rounded-md text-gray-300 hover:bg-dark-600 transition-colors">90D</button>
                <button data-range="all" class="px-3 py-1 text-sm rounded-md text-gray-300 hover:bg-dark-600 transition-colors">All</button>
            </div>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content active">
            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="text-2xl font-bold text-green-400" id="total-value">$0.00</div>
                    <div class="text-xs text-gray-400 mt-1">Total Value</div>
                </div>
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="text-2xl font-bold text-blue-400" id="cash-value">$0.00</div>
                    <div class="text-xs text-gray-400 mt-1">Available Cash</div>
                </div>
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="text-2xl font-bold text-yellow-400" id="roi-value">0%</div>
                    <div class="text-xs text-gray-400 mt-1">ROI</div>
                </div>
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="text-2xl font-bold" id="total-pnl-value">$0.00</div>
                    <div class="text-xs text-gray-400 mt-1">Total P&L</div>
                </div>
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="text-2xl font-bold text-purple-400" id="win-rate">0%</div>
                    <div class="text-xs text-gray-400 mt-1">Win Rate</div>
                </div>
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700 relative group cursor-help">
                    <div class="text-2xl font-bold text-emerald-400" id="trailing-stops">0</div>
                    <div class="text-xs text-gray-400 mt-1">🛡️ Protected</div>
                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-4 py-3 bg-dark-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none z-50 border border-dark-600 w-72 shadow-xl">
                        <div class="font-bold mb-2 text-emerald-400">🛡️ Risk Management</div>
                        <div class="text-gray-300 text-xs mb-3">
                            <div class="font-semibold text-orange-400 mb-1">⚡ Dynamic Stop Loss</div>
                            <div class="text-gray-400 ml-4">Max loss: 6% P&L</div>
                        </div>
                        <div class="text-gray-300 text-xs space-y-1 border-t border-dark-700 pt-2">
                            <div class="font-semibold text-emerald-400 mb-1">🛡️ Trailing Stops:</div>
                            <div>L1: +4.5% → stop at -0.5%</div>
                            <div>L2: +6% → stop at +2%</div>
                            <div>L3: +9% → stop at +5%</div>
                            <div>L4: +13% → stop at +8%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open Positions -->
            <div>
                <h2 class="text-lg font-semibold text-white mb-4">Open Positions</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="open-positions">
                    <div class="text-center py-8 text-gray-400 col-span-full">Loading positions...</div>
                </div>
            </div>
        </div>

        <!-- Performance Tab -->
        <div id="tab-performance" class="tab-content">
            <!-- PNL Chart -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-white mb-4">Performance Chart (Last 7 Days)</h2>
                <div class="bg-dark-800 rounded-lg p-6 border border-dark-700">
                    <canvas id="pnlChart" height="80"></canvas>
                </div>
            </div>

            <!-- Closed Positions -->
            <div>
                <h2 class="text-lg font-semibold text-white mb-4">Recent Closed Positions</h2>
                <div class="bg-dark-800 rounded-lg overflow-hidden border border-dark-700">
                    <div id="closed-positions">
                        <div class="text-center py-8 text-gray-400">Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Markets Tab -->
        <div id="tab-markets" class="tab-content">
            <h2 class="text-lg font-semibold text-white mb-4">Live Market Indicators</h2>
            <div class="bg-dark-800 rounded-lg overflow-hidden border border-dark-700">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-dark-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">Symbol</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">Price</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">EMA20/50</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">MACD</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">RSI(7)</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">Volume</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">4H Trend</th>
                                <th class="px-4 py-3 text-left text-gray-400 font-medium">Updated</th>
                            </tr>
                        </thead>
                        <tbody id="market-indicators" class="divide-y divide-dark-700">
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-400">Loading indicators...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AI Decisions Tab -->
        <div id="tab-ai" class="tab-content">
            <div class="space-y-6">
                <!-- AI Logs -->
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-white">Recent AI Decisions</h2>
                        <div class="text-sm text-gray-400">
                            Last run: <span id="last-ai-run" class="text-blue-400">N/A</span>
                        </div>
                    </div>
                    <div class="bg-dark-800 rounded-lg overflow-hidden border border-dark-700">
                        <div id="ai-logs" class="p-4">
                            <div class="text-center py-8 text-gray-400">Loading AI logs...</div>
                        </div>
                    </div>
                </div>

                <!-- HOLD Reasons -->
                <div>
                    <h2 class="text-lg font-semibold text-white mb-4">Why Not Bought (Last AI Run)</h2>
                    <div class="bg-dark-800 rounded-lg overflow-hidden border border-dark-700">
                        <table class="w-full">
                            <thead class="bg-dark-700 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left text-gray-400">Symbol</th>
                                    <th class="px-4 py-3 text-left text-gray-400">Confidence</th>
                                    <th class="px-4 py-3 text-left text-gray-400">Reason</th>
                                </tr>
                            </thead>
                            <tbody id="hold-reasons" class="divide-y divide-dark-700">
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Tab -->
        <div id="tab-system" class="tab-content">
            <h2 class="text-lg font-semibold text-white mb-4">How The System Works</h2>
            <div class="bg-gradient-to-br from-dark-800 to-dark-900 rounded-lg p-6 border border-dark-700">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-blue-900/50 rounded-full flex items-center justify-center">
                            <span class="text-3xl">📡</span>
                        </div>
                        <h3 class="text-lg font-semibold text-blue-400 mb-2">Data Collection</h3>
                        <p class="text-sm text-gray-400">Every 3 minutes, collect market data for 6 cryptocurrencies</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-purple-900/50 rounded-full flex items-center justify-center">
                            <span class="text-3xl">📈</span>
                        </div>
                        <h3 class="text-lg font-semibold text-purple-400 mb-2">Technical Analysis</h3>
                        <p class="text-sm text-gray-400">Calculate 10+ indicators: EMA, MACD, RSI, ATR, Volume</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-green-900/50 rounded-full flex items-center justify-center">
                            <span class="text-3xl">🤖</span>
                        </div>
                        <h3 class="text-lg font-semibold text-green-400 mb-2">AI Decision</h3>
                        <p class="text-sm text-gray-400">AI analyzes data and decides: BUY, HOLD, CLOSE, or STOP</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-orange-900/50 rounded-full flex items-center justify-center">
                            <span class="text-3xl">⚡</span>
                        </div>
                        <h3 class="text-lg font-semibold text-orange-400 mb-2">Execution</h3>
                        <p class="text-sm text-gray-400">Execute trades with 2x leverage and 24/7 monitoring</p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-6 border-t border-dark-700">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400">10min</div>
                        <div class="text-xs text-gray-500">AI Analysis Cycle</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-400">1min</div>
                        <div class="text-xs text-gray-500">Position Monitoring</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-400">6</div>
                        <div class="text-xs text-gray-500">Cryptocurrencies</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-400">2x</div>
                        <div class="text-xs text-gray-500">Max Leverage</div>
                    </div>
                </div>

                <!-- Strategy Details -->
                <div class="mt-6 pt-6 border-t border-dark-700">
                    <button id="strategy-detail-btn" class="w-full flex items-center justify-between px-4 py-3 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors">
                        <span class="font-semibold text-white">📋 View Detailed Trading Strategy</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Risk Tab -->
        <div id="tab-risk" class="tab-content">
            <h2 class="text-lg font-semibold text-white mb-4">Risk Management Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Sleep Mode -->
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-medium text-gray-400">🌙 Sleep Mode</h3>
                        <span id="sleep-mode-badge" class="px-2 py-1 text-xs rounded-md bg-gray-700 text-gray-300">Checking...</span>
                    </div>
                    <div id="sleep-mode-details" class="text-sm text-gray-300 space-y-1"></div>
                </div>

                <!-- Daily Drawdown -->
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-medium text-gray-400">📉 Daily Drawdown</h3>
                        <span id="drawdown-badge" class="px-2 py-1 text-xs rounded-md bg-gray-700 text-gray-300">Checking...</span>
                    </div>
                    <div id="drawdown-details" class="text-sm text-gray-300 space-y-1"></div>
                </div>

                <!-- Cluster Loss -->
                <div class="bg-dark-800 rounded-lg p-4 border border-dark-700">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-medium text-gray-400">⏸️ Cluster Loss</h3>
                        <span id="cluster-badge" class="px-2 py-1 text-xs rounded-md bg-gray-700 text-gray-300">Checking...</span>
                    </div>
                    <div id="cluster-details" class="text-sm text-gray-300 space-y-1"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Strategy Detail Modal -->
    <div id="strategy-modal" class="modal fixed inset-0 z-50 hidden bg-black/50">
        <div class="modal-content bg-dark-800 rounded-lg mx-auto my-20 p-6 w-11/12 max-w-3xl relative max-h-[80vh] overflow-y-auto">
            <button id="close-strategy-modal" class="absolute top-4 right-4 text-2xl text-white hover:text-gray-300">&times;</button>
            <h2 class="text-2xl font-bold text-white mb-4">📋 Trading Strategy Details</h2>
            <div class="bg-dark-700 rounded-lg p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-green-400 mb-3">BUY Criteria (ALL must be true)</h3>
                    <ul class="space-y-2 text-gray-300 text-sm">
                        <li>✓ Price > EMA20 by at least 0.3%</li>
                        <li>✓ MACD > Signal AND MACD > price × 0.00005</li>
                        <li>✓ RSI between 35-75</li>
                        <li>✓ 4H Trend: EMA20 > EMA50 × 0.999 AND ADX(14) > 20</li>
                        <li>✓ Volume > 20MA × 0.9 AND > previous bar × 1.05</li>
                        <li>✓ AI Confidence > 70%</li>
                    </ul>
                </div>
                <div class="border-t border-dark-600 pt-6">
                    <h3 class="text-lg font-semibold text-red-400 mb-3">EXIT Strategy</h3>
                    <ul class="space-y-2 text-gray-300 text-sm">
                        <li>🎯 Take Profit: +5% gain</li>
                        <li>🛑 Stop Loss: -3% maximum</li>
                        <li>⚠️ Trend Invalidation: Multiple signals + P&L < 2%</li>
                        <li>🔒 Multi-Level Trailing Stop</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Decision Modal -->
    <div id="ai-modal" class="modal fixed inset-0 z-50 hidden bg-black/50">
        <div class="modal-content bg-dark-800 rounded-lg mx-auto my-20 p-6 w-11/12 max-w-2xl relative max-h-[80vh] overflow-y-auto">
            <button id="close-ai-modal" class="absolute top-4 right-4 text-2xl text-white hover:text-gray-300">&times;</button>
            <h2 class="text-2xl font-bold text-white mb-4">AI Decision Details</h2>
            <div id="ai-modal-content"></div>
        </div>
    </div>

    <footer class="mt-12 py-8 text-center border-t border-dark-700">
        <p class="text-gray-500 text-sm">Eren Ilhan | erenilhan1@gmail.com</p>
        <a href="https://erenilhan.com" target="_blank" class="text-blue-500 hover:text-blue-400 text-sm mt-2 inline-block">erenilhan.com</a>
    </footer>

    <script>
        const API_URL = '/api/dashboard/data';
        let pnlChartInstance = null;
        let currentRange = '7';

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.dataset.tab;

                // Update buttons
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                button.classList.add('active');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById(`tab-${tab}`).classList.add('active');
            });
        });

        // Date range filter
        const rangeButtons = document.querySelectorAll('#date-range-filter button');
        rangeButtons.forEach(button => {
            button.addEventListener('click', () => {
                rangeButtons.forEach(btn => btn.classList.remove('active-range'));
                button.classList.add('active-range');
                const range = button.dataset.range;
                loadData(range);
            });
        });
        document.querySelector('#date-range-filter button[data-range="7"]').classList.add('active-range');

        // Helper functions
        function formatMoney(value) {
            return '$' + parseFloat(value || 0).toFixed(2);
        }

        function formatPercent(value) {
            return (parseFloat(value) || 0).toFixed(2) + '%';
        }

        function renderDashboard(data) {
            const { account, positions, closed_positions, ai_logs, hold_reasons, last_ai_run, ai_model, stats, pnl_chart, market_indicators } = data;

            // Update stats
            document.getElementById('total-value').textContent = formatMoney(account.total_value);
            document.getElementById('cash-value').textContent = formatMoney(account.cash);
            document.getElementById('roi-value').textContent = formatPercent(account.roi);
            document.getElementById('win-rate').textContent = formatPercent(stats.win_rate);

            const protectedCount = positions.filter(p => p.trailing_level).length;
            document.getElementById('trailing-stops').textContent = protectedCount;

            const totalPnl = (account.realized_pnl || 0) + (account.total_pnl || 0);
            const totalPnlEl = document.getElementById('total-pnl-value');
            totalPnlEl.className = `text-2xl font-bold ${totalPnl >= 0 ? 'text-green-400' : 'text-red-400'}`;
            totalPnlEl.textContent = (totalPnl >= 0 ? '+' : '') + formatMoney(totalPnl);

            // Update AI model
            document.getElementById('ai-model-text').textContent = ai_model || 'N/A';

            // Update bot status
            const botStatus = document.getElementById('bot-status');
            if (stats.bot_enabled) {
                botStatus.className = 'status-badge inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-green-900/50 text-green-300';
                botStatus.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span><span class="hidden sm:inline">Active</span>';
            }

            // Render positions
            renderPositions(positions);
            renderClosedPositions(closed_positions);

            const chartTitle = document.querySelector('#tab-performance h2');
            if (chartTitle) {
                let rangeText = `Last ${currentRange} Days`;
                if (currentRange === 'all') rangeText = 'All Time';
                else if (currentRange === '7') rangeText = 'Last 7 Days';
                chartTitle.textContent = `Performance Chart (${rangeText})`;
            }

            if (pnl_chart) renderPnlChart(pnl_chart);
            if (market_indicators) renderMarketIndicators(market_indicators);

            renderAiLogs(ai_logs);
            renderHoldReasons(hold_reasons);
            renderRiskManagement(data.risk_management);

            document.getElementById('last-ai-run').textContent = last_ai_run;
        }

        function renderPositions(positions) {
            const container = document.getElementById('open-positions');
            if (positions.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-400 col-span-full">No open positions</div>';
                return;
            }

            container.innerHTML = positions.map(pos => {
                const pnlColor = pos.pnl >= 0 ? 'text-green-400' : 'text-red-400';
                const pnlEmoji = pos.pnl >= 0 ? '🟢' : '🔴';
                const invested = (pos.position_size || (pos.quantity * pos.entry_price)) / pos.leverage;

                let trailingBadge = '';
                if (pos.trailing_level) {
                    const colors = {1: 'bg-yellow-600', 2: 'bg-green-600', 3: 'bg-emerald-600', 4: 'bg-teal-600'};
                    const emojis = {1: '🛡️', 2: '✅', 3: '🔒', 4: '💎'};
                    trailingBadge = `<div class="${colors[pos.trailing_level]} text-white px-2 py-1 rounded-full text-xs font-semibold">${emojis[pos.trailing_level]} L${pos.trailing_level}</div>`;
                }

                return `
                    <div class="bg-dark-800 border border-dark-700 rounded-lg p-4 hover:border-blue-500/50 transition-colors">
                        <div class="flex justify-between items-center pb-2 mb-3 border-b border-dark-700">
                            <div class="flex items-center gap-2">
                                <a href="https://tr.tradingview.com/symbols/${pos.symbol.replace('/', '')}" 
                                   target="_blank" 
                                   class="font-semibold text-lg text-white hover:text-blue-400 transition-colors cursor-pointer">
                                    ${pos.symbol}
                                </a>
                                <div class="${pos.side === 'long' ? 'bg-green-600' : 'bg-red-600'} text-white px-2 py-1 rounded-full text-xs font-bold">
                                    ${pos.side === 'long' ? '📈 LONG' : '📉 SHORT'}
                                </div>
                            </div>
                            <div class="flex gap-2">
                                ${trailingBadge}
                                <div class="bg-blue-600 text-white px-2 py-1 rounded-full text-xs">${pos.leverage}x</div>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">💵 Capital</span>
                                <span class="text-yellow-400 font-semibold">${formatMoney(invested)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Entry</span>
                                <span class="text-white">${formatMoney(pos.entry_price)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Current</span>
                                <span class="text-white font-semibold">${formatMoney(pos.current_price)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">P&L</span>
                                <span class="${pnlColor} font-bold">${pnlEmoji} ${formatMoney(pos.pnl)} (${formatPercent(pos.pnl_percent)})</span>
                            </div>
                            ${pos.confidence ? `
                            <div class="flex justify-between">
                                <span class="text-gray-400">Confidence</span>
                                <span class="text-blue-400 font-semibold">${Math.round(pos.confidence * 100)}%</span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Exit Plan -->
                        <div class="mt-3 pt-3 border-t border-dark-700">
                            <div class="text-xs text-gray-400 mb-2 font-semibold">📋 Exit Plan</div>
                            <div class="space-y-1 text-xs">
                                ${pos.exit_plan?.profit_target ? `
                                <div class="flex justify-between">
                                    <span class="text-green-400">🎯 Take Profit</span>
                                    <span class="text-green-400 font-semibold">${formatMoney(pos.exit_plan.profit_target)}</span>
                                </div>
                                ` : ''}
                                ${pos.exit_plan?.stop_loss ? `
                                <div class="flex justify-between">
                                    <span class="text-red-400">🛑 Stop Loss</span>
                                    <span class="text-red-400 font-semibold">${formatMoney(pos.exit_plan.stop_loss)}</span>
                                </div>
                                ` : ''}
                                ${pos.trailing_level ? `
                                <div class="flex justify-between">
                                    <span class="text-yellow-400">🛡️ Trailing Stop</span>
                                    <span class="text-yellow-400 font-semibold">Level ${pos.trailing_level} Active</span>
                                </div>
                                ` : `
                                <div class="flex justify-between">
                                    <span class="text-gray-500">🛡️ Trailing Stop</span>
                                    <span class="text-gray-500">Waiting for profit</span>
                                </div>
                                `}
                            </div>
                        </div>
                        
                        ${pos.ai_reasoning ? `
                        <div class="mt-3 pt-3 border-t border-dark-700">
                            <button onclick="showAiReasoning('${pos.symbol}', '${pos.ai_reasoning.replace(/'/g, "\\'")}', ${pos.confidence || 0})" 
                                    class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                                🤖 Why did AI choose this?
                            </button>
                        </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function renderClosedPositions(positions) {
            const container = document.getElementById('closed-positions');
            if (positions.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-400">No closed positions</div>';
                return;
            }

            container.innerHTML = `
                <div class="grid grid-cols-7 bg-dark-700 text-gray-300 font-semibold p-3 text-sm">
                    <div>Symbol</div>
                    <div>Side</div>
                    <div>Entry</div>
                    <div>Leverage</div>
                    <div>P&L</div>
                    <div>Closed</div>
                    <div>Reason</div>
                </div>
                ${positions.map(pos => {
                    const pnlColor = pos.pnl >= 0 ? 'text-green-400' : 'text-red-400';
                    const pnlEmoji = pos.pnl >= 0 ? '🟢' : '🔴';
                    const sideColor = pos.side === 'long' ? 'text-green-400' : 'text-red-400';
                    const sideText = pos.side === 'long' ? '📈 LONG' : '📉 SHORT';
                    return `
                        <div class="grid grid-cols-7 p-3 border-b border-dark-700 hover:bg-dark-700/30 text-sm">
                            <div class="font-medium">
                                <a href="https://tr.tradingview.com/symbols/${pos.symbol.replace('/', '')}" 
                                   target="_blank" 
                                   class="text-white hover:text-blue-400 transition-colors">
                                    ${pos.symbol}
                                </a>
                            </div>
                            <div class="${sideColor} font-semibold text-xs">${sideText}</div>
                            <div class="text-gray-300">${formatMoney(pos.entry_price)}</div>
                            <div class="text-blue-400">${pos.leverage}x</div>
                            <div class="${pnlColor} font-semibold">${pnlEmoji} ${formatMoney(pos.pnl)}</div>
                            <div class="text-gray-400 text-xs">${pos.closed_at || 'N/A'}</div>
                            <div class="text-gray-400 text-xs">${pos.close_reason || '-'}</div>
                        </div>
                    `;
                }).join('')}
            `;
        }

        function renderPnlChart(chartData) {
            const ctx = document.getElementById('pnlChart').getContext('2d');
            if (pnlChartInstance) pnlChartInstance.destroy();

            pnlChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(d => d.date),
                    datasets: [{
                        label: 'Daily P&L',
                        data: chartData.map(d => d.daily_pnl),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Cumulative P&L',
                        data: chartData.map(d => d.cumulative_pnl),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#9ca3af', font: { size: 12 } }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#374151' },
                            ticks: { color: '#9ca3af', font: { size: 11 } }
                        },
                        y: {
                            grid: { color: '#374151' },
                            ticks: {
                                color: '#9ca3af',
                                font: { size: 11 },
                                callback: value => '$' + value.toFixed(0)
                            }
                        }
                    }
                }
            });
        }

        function renderMarketIndicators(indicators) {
            const tbody = document.getElementById('market-indicators');
            if (indicators.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No market data</td></tr>';
                return;
            }

            const toNum = (val) => val ? parseFloat(val) : 0;

            tbody.innerHTML = indicators.map(ind => {
                const ema20 = toNum(ind.ema20);
                const ema50 = toNum(ind.ema50);
                const trendUp = ema20 > ema50 && ema50 > 0;
                const trendColor = trendUp ? 'text-green-400' : 'text-red-400';
                const emaDiff = ema50 > 0 ? ((ema20 / ema50 * 100) - 100).toFixed(2) : '0.00';

                const macd = toNum(ind.macd);
                const macdSignal = toNum(ind.macd_signal);
                const macdPositive = macd > macdSignal;
                const macdColor = macdPositive ? 'text-green-400' : 'text-red-400';

                const rsi = toNum(ind.rsi);
                let rsiColor = 'text-yellow-400';
                if (rsi < 30) rsiColor = 'text-green-400';
                else if (rsi > 70) rsiColor = 'text-red-400';

                return `
                    <tr class="hover:bg-dark-700/50">
                        <td class="px-4 py-3 font-medium text-white">${ind.symbol}</td>
                        <td class="px-4 py-3 text-gray-300">${formatMoney(ind.price)}</td>
                        <td class="px-4 py-3 ${trendColor}">${trendUp ? '📈' : '📉'} ${emaDiff}%</td>
                        <td class="px-4 py-3 ${macdColor}">${macdPositive ? '🟢' : '🔴'} ${(macd - macdSignal).toFixed(2)}</td>
                        <td class="px-4 py-3 ${rsiColor}">${rsi > 0 ? rsi.toFixed(1) : 'N/A'}</td>
                        <td class="px-4 py-3 text-gray-300">${toNum(ind.volume) > 0 ? (toNum(ind.volume) / 1000000).toFixed(2) + 'M' : 'N/A'}</td>
                        <td class="px-4 py-3 text-gray-400">${ind.trend_4h ? '🟢' : 'N/A'}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">${ind.updated_at || 'N/A'}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderAiLogs(logs) {
            const container = document.getElementById('ai-logs');
            if (logs.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-400">No AI logs</div>';
                return;
            }

            let items = [];
            for (const log of logs.slice(0, 10)) {
                if (!log.decisions || log.decisions.length === 0) continue;
                for (const decision of log.decisions.slice(0, 3)) {
                    const actionColors = {
                        buy: 'text-green-400',
                        close_profitable: 'text-blue-400',
                        stop_loss: 'text-red-400',
                        hold: 'text-yellow-400'
                    };
                    items.push(`
                        <div class="flex justify-between items-center py-2 border-b border-dark-700">
                            <div class="font-medium text-white">${decision.symbol}</div>
                            <div class="${actionColors[decision.action] || 'text-gray-400'}">${decision.action.toUpperCase()}</div>
                            <div class="text-gray-400">${(decision.confidence * 100).toFixed(0)}%</div>
                            <div class="text-gray-500 text-xs">${log.created_at}</div>
                        </div>
                    `);
                }
            }
            container.innerHTML = items.join('');
        }

        function renderHoldReasons(reasons) {
            const tbody = document.getElementById('hold-reasons');
            if (!reasons || reasons.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">No HOLD decisions</td></tr>';
                return;
            }

            tbody.innerHTML = reasons.map(hold => `
                <tr class="hover:bg-dark-700/50">
                    <td class="px-4 py-3 font-medium text-white">${hold.symbol}</td>
                    <td class="px-4 py-3 text-yellow-400">${hold.confidence}%</td>
                    <td class="px-4 py-3 text-gray-300 text-sm">${hold.reason}</td>
                </tr>
            `).join('');
        }

        function renderRiskManagement(risk) {
            if (!risk) return;

            const { sleep_mode, daily_drawdown, cluster_loss } = risk;

            // Sleep Mode
            const sleepBadge = document.getElementById('sleep-mode-badge');
            const sleepDetails = document.getElementById('sleep-mode-details');
            if (sleep_mode.active) {
                sleepBadge.className = 'px-2 py-1 text-xs rounded-md bg-yellow-900/50 text-yellow-300';
                sleepBadge.textContent = '🌙 Active';
                sleepDetails.innerHTML = `<div class="text-yellow-300">Low liquidity hours</div><div class="text-xs text-gray-400">UTC: ${sleep_mode.hours_utc}</div>`;
            } else {
                sleepBadge.className = 'px-2 py-1 text-xs rounded-md bg-green-900/50 text-green-300';
                sleepBadge.textContent = '✅ Normal';
                sleepDetails.innerHTML = `<div class="text-green-300">Full trading hours</div>`;
            }

            // Daily Drawdown
            const drawdownBadge = document.getElementById('drawdown-badge');
            const drawdownDetails = document.getElementById('drawdown-details');
            if (daily_drawdown.limit_hit) {
                drawdownBadge.className = 'px-2 py-1 text-xs rounded-md bg-red-900/50 text-red-300';
                drawdownBadge.textContent = '🚨 Limit Hit';
                drawdownDetails.innerHTML = `<div class="text-red-300">Trading paused</div>`;
            } else {
                drawdownBadge.className = 'px-2 py-1 text-xs rounded-md bg-green-900/50 text-green-300';
                drawdownBadge.textContent = '✅ OK';
                drawdownDetails.innerHTML = `<div class="text-green-300">Within limits</div>`;
            }

            // Cluster Loss
            const clusterBadge = document.getElementById('cluster-badge');
            const clusterDetails = document.getElementById('cluster-details');
            if (cluster_loss.in_cooldown) {
                clusterBadge.className = 'px-2 py-1 text-xs rounded-md bg-red-900/50 text-red-300';
                clusterBadge.textContent = '⏸️ Cooldown';
                clusterDetails.innerHTML = `<div class="text-red-300">Trading paused</div>`;
            } else {
                clusterBadge.className = 'px-2 py-1 text-xs rounded-md bg-green-900/50 text-green-300';
                clusterBadge.textContent = '✅ OK';
                clusterDetails.innerHTML = `<div class="text-green-300">No cluster losses</div>`;
            }
        }

        async function loadData(range = '7') {
            currentRange = range;
            const loadingOverlay = document.getElementById('loading-overlay');
            loadingOverlay.classList.remove('hidden');
            try {
                const response = await fetch(`${API_URL}?range=${range}`);
                const result = await response.json();
                if (result.success) {
                    renderDashboard(result.data);
                }
            } catch (error) {
                console.error('Failed to load data:', error);
            } finally {
                loadingOverlay.classList.add('hidden');
            }
        }

        // Modal handlers
        document.getElementById('strategy-detail-btn')?.addEventListener('click', () => {
            document.getElementById('strategy-modal').classList.remove('hidden');
        });
        document.getElementById('close-strategy-modal')?.addEventListener('click', () => {
            document.getElementById('strategy-modal').classList.add('hidden');
        });

        // Load data on start and refresh every minute
        loadData(currentRange);
        setInterval(() => loadData(currentRange), 60000);
    </script>

    <!-- AI Reasoning Modal -->
    <div id="aiReasoningModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-dark-800 border border-dark-700 rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-white">🤖 AI Decision Analysis</h3>
                    <button onclick="closeAiReasoning()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-dark-700 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400">Symbol:</span>
                            <span id="modalSymbol" class="text-white font-semibold"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">AI Confidence:</span>
                            <span id="modalConfidence" class="text-blue-400 font-semibold"></span>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-white font-semibold mb-2">AI Reasoning:</h4>
                        <div id="modalReasoning" class="bg-dark-700 rounded-lg p-4 text-gray-300 leading-relaxed"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAiReasoning(symbol, reasoning, confidence) {
            document.getElementById('modalSymbol').textContent = symbol;
            document.getElementById('modalConfidence').textContent = Math.round(confidence * 100) + '%';
            document.getElementById('modalReasoning').textContent = reasoning;
            document.getElementById('aiReasoningModal').classList.remove('hidden');
        }

        function closeAiReasoning() {
            document.getElementById('aiReasoningModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('aiReasoningModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAiReasoning();
            }
        });
    </script>
</body>
</html>
