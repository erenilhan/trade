<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-dark-900 text-gray-200 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header -->
        <header class="flex justify-between items-center mb-8 border-b border-dark-700 pb-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-white">📊 Performance Analytics</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-400" id="last-update">Loading...</div>
                <button onclick="loadAnalytics()" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 rounded text-sm transition-colors">
                    Refresh
                </button>
            </div>
        </header>

        <!-- Overall Stats Grid -->
        <div id="overall-stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-dark-700 rounded-lg p-4 animate-pulse">
                <div class="h-4 bg-dark-600 rounded w-24 mb-2"></div>
                <div class="h-8 bg-dark-600 rounded w-16"></div>
            </div>
            <div class="bg-dark-700 rounded-lg p-4 animate-pulse">
                <div class="h-4 bg-dark-600 rounded w-24 mb-2"></div>
                <div class="h-8 bg-dark-600 rounded w-16"></div>
            </div>
            <div class="bg-dark-700 rounded-lg p-4 animate-pulse">
                <div class="h-4 bg-dark-600 rounded w-24 mb-2"></div>
                <div class="h-8 bg-dark-600 rounded w-16"></div>
            </div>
            <div class="bg-dark-700 rounded-lg p-4 animate-pulse">
                <div class="h-4 bg-dark-600 rounded w-24 mb-2"></div>
                <div class="h-8 bg-dark-600 rounded w-16"></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- AI Confidence Analysis -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700">
                <h2 class="text-xl font-semibold text-white mb-4">🤖 AI Confidence vs Win Rate</h2>
                <div id="ai-confidence-chart" class="space-y-3">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>

            <!-- Close Reason Breakdown -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700">
                <h2 class="text-xl font-semibold text-white mb-4">🚪 Exit Reasons</h2>
                <div id="close-reasons-chart" class="space-y-3">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>

            <!-- Coin Performance -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700 lg:col-span-2">
                <h2 class="text-xl font-semibold text-white mb-4">💰 Coin Performance Comparison</h2>
                <div id="coin-performance-table" class="overflow-x-auto">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>

            <!-- Best Trades -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700">
                <h2 class="text-xl font-semibold text-white mb-4">🏆 Best Trades (Top 5)</h2>
                <div id="best-trades" class="space-y-2">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>

            <!-- Worst Trades -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700">
                <h2 class="text-xl font-semibold text-white mb-4">❌ Worst Trades (Top 5)</h2>
                <div id="worst-trades" class="space-y-2">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>

            <!-- Leverage Analysis -->
            <div class="bg-dark-800 rounded-lg p-6 border border-dark-700 lg:col-span-2">
                <h2 class="text-xl font-semibold text-white mb-4">⚖️ Leverage Analysis</h2>
                <div id="leverage-analysis" class="space-y-3">
                    <div class="text-gray-500 text-center py-8">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadAnalytics() {
            try {
                const response = await fetch('{{ route('analytics.data') }}');
                const result = await response.json();

                if (!result.success) {
                    console.error('Failed to load analytics');
                    return;
                }

                const data = result.data;

                renderOverallStats(data.overall);
                renderAIConfidence(data.ai_confidence);
                renderCloseReasons(data.close_reasons);
                renderCoinPerformance(data.coin_performance);
                renderBestWorstTrades(data.best_worst_trades);
                renderLeverageAnalysis(data.leverage_analysis);

                document.getElementById('last-update').textContent = 'Updated: ' + new Date().toLocaleTimeString();
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
        }

        function renderOverallStats(stats) {
            const container = document.getElementById('overall-stats');
            const winRateColor = stats.win_rate >= 50 ? 'text-green-400' : 'text-yellow-400';
            const pnlColor = stats.total_pnl >= 0 ? 'text-green-400' : 'text-red-400';

            container.innerHTML = `
                <div class="bg-dark-700 rounded-lg p-4">
                    <div class="text-gray-400 text-sm mb-1">Total Trades</div>
                    <div class="text-2xl font-bold text-white">${stats.total_trades}</div>
                    <div class="text-xs text-gray-500">${stats.wins}W / ${stats.losses}L</div>
                </div>
                <div class="bg-dark-700 rounded-lg p-4">
                    <div class="text-gray-400 text-sm mb-1">Win Rate</div>
                    <div class="text-2xl font-bold ${winRateColor}">${stats.win_rate.toFixed(1)}%</div>
                    <div class="text-xs text-gray-500">Target: 50%+</div>
                </div>
                <div class="bg-dark-700 rounded-lg p-4">
                    <div class="text-gray-400 text-sm mb-1">Total P&L</div>
                    <div class="text-2xl font-bold ${pnlColor}">$${stats.total_pnl.toFixed(2)}</div>
                    <div class="text-xs text-gray-500">PF: ${stats.profit_factor.toFixed(2)}</div>
                </div>
                <div class="bg-dark-700 rounded-lg p-4">
                    <div class="text-gray-400 text-sm mb-1">Avg Win/Loss</div>
                    <div class="text-sm font-bold text-green-400">+$${stats.avg_win.toFixed(2)}</div>
                    <div class="text-sm font-bold text-red-400">$${stats.avg_loss.toFixed(2)}</div>
                </div>
            `;
        }

        function renderAIConfidence(aiData) {
            const container = document.getElementById('ai-confidence-chart');
            const ranges = aiData.by_range || [];

            if (ranges.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No AI confidence data available</div>';
                return;
            }

            let html = '';
            ranges.forEach(range => {
                const winRateColor = range.win_rate >= 50 ? 'bg-green-600' : 'bg-red-600';
                const barWidth = (range.count / Math.max(...ranges.map(r => r.count))) * 100;

                html += `
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-300">${range.range}</span>
                            <span class="text-gray-400">${range.count} trades</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-dark-600 rounded-full h-6 overflow-hidden">
                                <div class="${winRateColor} h-full flex items-center justify-center text-xs text-white font-semibold transition-all"
                                     style="width: ${range.win_rate}%">
                                    ${range.win_rate.toFixed(1)}%
                                </div>
                            </div>
                            <div class="text-sm ${range.total_pnl >= 0 ? 'text-green-400' : 'text-red-400'} w-20 text-right">
                                $${range.total_pnl.toFixed(2)}
                            </div>
                        </div>
                    </div>
                `;
            });

            // Add insight
            const correlation = aiData.overall.correlation;
            html += `
                <div class="mt-4 p-3 ${correlation > 0 ? 'bg-green-900/30 border-green-700' : 'bg-yellow-900/30 border-yellow-700'} border rounded text-sm">
                    <div class="font-semibold ${correlation > 0 ? 'text-green-300' : 'text-yellow-300'} mb-1">💡 Insight</div>
                    <div class="text-gray-300">
                        Wins avg: ${aiData.overall.avg_confidence_wins}% | Losses avg: ${aiData.overall.avg_confidence_losses}%<br>
                        ${correlation > 0 ?
                            '<span class="text-green-400">✓ Higher confidence correlates with better results!</span>' :
                            '<span class="text-yellow-400">⚠️ Warning: Confidence does NOT correlate with success!</span>'
                        }
                    </div>
                </div>
            `;

            container.innerHTML = html;
        }

        function renderCloseReasons(reasons) {
            const container = document.getElementById('close-reasons-chart');

            if (reasons.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No close reason data yet (new feature)</div>';
                return;
            }

            const reasonLabels = {
                'take_profit': '🎯 Take Profit',
                'stop_loss': '🛑 Stop Loss',
                'trailing_stop_l1': '🔒 Trailing L1',
                'trailing_stop_l2': '🔒 Trailing L2',
                'trailing_stop_l3': '🔒🔒 Trailing L3',
                'trailing_stop_l4': '🔒🔒🔒 Trailing L4',
                'manual': '👤 Manual',
                'liquidated': '⚠️ Liquidated',
                'unknown': '❓ Unknown'
            };

            let html = '';
            reasons.forEach(reason => {
                const winRateColor = reason.win_rate >= 50 ? 'text-green-400' : 'text-red-400';
                const pnlColor = reason.total_pnl >= 0 ? 'text-green-400' : 'text-red-400';
                const label = reasonLabels[reason.reason] || reason.reason;

                html += `
                    <div class="flex justify-between items-center py-2 border-b border-dark-600 last:border-0">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-200">${label}</div>
                            <div class="text-xs text-gray-500">${reason.count} trades (${reason.wins}W/${reason.losses}L)</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold ${winRateColor}">${reason.win_rate}%</div>
                            <div class="text-xs ${pnlColor}">$${reason.total_pnl.toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function renderCoinPerformance(coins) {
            const container = document.getElementById('coin-performance-table');

            if (coins.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No coin data available</div>';
                return;
            }

            let html = `
                <table class="w-full text-sm">
                    <thead class="bg-dark-700 text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Coin</th>
                            <th class="px-4 py-3 text-center">Trades</th>
                            <th class="px-4 py-3 text-center">W/L</th>
                            <th class="px-4 py-3 text-center">Win Rate</th>
                            <th class="px-4 py-3 text-right">Total P&L</th>
                            <th class="px-4 py-3 text-right">Avg P&L</th>
                            <th class="px-4 py-3 text-center">Profit Factor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-700">
            `;

            coins.forEach(coin => {
                const winRateColor = coin.win_rate >= 50 ? 'text-green-400' : (coin.win_rate >= 40 ? 'text-yellow-400' : 'text-red-400');
                const pnlColor = coin.total_pnl >= 0 ? 'text-green-400' : 'text-red-400';
                const pfColor = coin.profit_factor >= 1.5 ? 'text-green-400' : (coin.profit_factor >= 1 ? 'text-yellow-400' : 'text-red-400');

                html += `
                    <tr class="hover:bg-dark-700/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-white">${coin.symbol}</td>
                        <td class="px-4 py-3 text-center text-gray-300">${coin.trades}</td>
                        <td class="px-4 py-3 text-center text-gray-400 text-xs">${coin.wins}/${coin.losses}</td>
                        <td class="px-4 py-3 text-center font-semibold ${winRateColor}">${coin.win_rate}%</td>
                        <td class="px-4 py-3 text-right font-semibold ${pnlColor}">$${coin.total_pnl.toFixed(2)}</td>
                        <td class="px-4 py-3 text-right text-gray-300">$${coin.avg_pnl.toFixed(2)}</td>
                        <td class="px-4 py-3 text-center font-semibold ${pfColor}">${coin.profit_factor.toFixed(2)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function renderBestWorstTrades({ best, worst }) {
            renderTradeList('best-trades', best);
            renderTradeList('worst-trades', worst);
        }

        function renderTradeList(containerId, trades) {
            const container = document.getElementById(containerId);

            if (trades.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-sm">No trades</div>';
                return;
            }

            let html = '';
            trades.forEach(trade => {
                const pnlColor = trade.pnl >= 0 ? 'text-green-400' : 'text-red-400';
                const pctColor = trade.pnl_percent >= 0 ? 'text-green-300' : 'text-red-300';

                html += `
                    <div class="bg-dark-700 rounded p-3 hover:bg-dark-600 transition-colors">
                        <div class="flex justify-between items-start mb-1">
                            <div>
                                <span class="font-semibold text-white">${trade.symbol}</span>
                                <span class="text-gray-400 text-xs ml-2">${trade.leverage}x</span>
                                ${trade.confidence ? `<span class="text-indigo-400 text-xs ml-1">${trade.confidence}%</span>` : ''}
                            </div>
                            <div class="text-right">
                                <div class="font-bold ${pnlColor}">$${trade.pnl.toFixed(2)}</div>
                                <div class="text-xs ${pctColor}">${trade.pnl_percent > 0 ? '+' : ''}${trade.pnl_percent.toFixed(1)}%</div>
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>${trade.closed_at || 'N/A'}</span>
                            ${trade.close_reason ? `<span class="text-gray-400">${trade.close_reason}</span>` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function renderLeverageAnalysis(leverages) {
            const container = document.getElementById('leverage-analysis');

            if (leverages.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No leverage data available</div>';
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';

            leverages.forEach(lev => {
                const winRateColor = lev.win_rate >= 50 ? 'text-green-400' : 'text-red-400';
                const pnlColor = lev.total_pnl >= 0 ? 'text-green-400' : 'text-red-400';

                html += `
                    <div class="bg-dark-700 rounded-lg p-4 border border-dark-600">
                        <div class="text-center mb-3">
                            <div class="text-2xl font-bold text-white">${lev.leverage}</div>
                            <div class="text-xs text-gray-500">${lev.count} trades</div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Win Rate:</span>
                                <span class="font-semibold ${winRateColor}">${lev.win_rate}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">W/L:</span>
                                <span class="text-gray-300">${lev.wins}/${lev.losses}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Total P&L:</span>
                                <span class="font-semibold ${pnlColor}">$${lev.total_pnl.toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Avg P&L:</span>
                                <span class="text-gray-300">$${lev.avg_pnl.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        // Load analytics on page load
        loadAnalytics();

        // Refresh every 30 seconds
        setInterval(loadAnalytics, 30000);
    </script>
</body>
</html>
