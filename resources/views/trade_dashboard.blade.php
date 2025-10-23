<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Dashboard</title>
    
    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
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
    
    <style type="text/tailwindcss">
        @layer base {
            body {
                @apply transition-colors duration-300;
            }
        }
    </style>
</head>
<body class="bg-dark-900 text-gray-200 min-h-screen transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <header class="flex justify-between items-center py-4 border-b border-dark-700 mb-6">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-white">Trading Dashboard</h1>
                <button id="menu-toggle" class="text-gray-200 hover:text-white focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Current Strategy Button -->
                <button id="strategy-btn" class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium bg-purple-900/50 text-purple-300 hover:bg-purple-900/70 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Strategy</span>
                </button>

                <!-- Bot Status with Tooltip -->
                <div class="relative group">
                    <div class="status-badge inactive inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium bg-red-900/50 text-red-300" id="bot-status">
                        <span class="status-dot w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <span>Bot Offline</span>
                    </div>
                    <div id="bot-status-tooltip" class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-sm text-gray-200 bg-dark-800 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none z-50 border border-dark-600">
                        Last run: Not available
                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-l-transparent border-r-transparent border-t-dark-800"></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-green-400" id="total-value">$0.00</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Total Value</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-blue-400" id="cash-value">$0.00</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Cash</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-yellow-400" id="roi-value">0%</div>
                <div class="stat-label text-sm text-gray-400 mt-1">ROI</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-2xl font-bold" id="total-pnl-value">$0.00</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Total P&L</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-purple-400" id="win-rate">0%</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Win Rate</div>
            </div>
        </div>

        <!-- Open Positions -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Open Positions</h2>
            <div class="positions-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="open-positions">
                <div class="loading text-center py-8 text-gray-400">Loading positions...</div>
            </div>
        </div>

        <!-- Closed Positions -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Closed Positions (Last 10)</h2>
            <div class="bg-dark-800 rounded-lg overflow-hidden">
                <div class="history-body p-4" id="closed-positions">
                    <div class="loading text-center py-8 text-gray-400">Loading positions...</div>
                </div>
            </div>
        </div>

        <!-- AI Logs Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-white">Recent AI Decisions (Last 10)</h2>
                <div class="text-sm text-gray-400">
                    <span>Provider: </span><span id="ai-provider" class="text-blue-400">-</span> |
                    <span>Last run: </span><span id="last-ai-run" class="text-blue-400">N/A</span>
                </div>
            </div>

            <div class="bg-dark-800 rounded-lg overflow-hidden">
                <div class="history-body p-4" id="ai-logs">
                    <div class="loading text-center py-8 text-gray-400">Loading AI logs...</div>
                </div>
            </div>
        </div>



        <!-- AI Decision Detail Modal -->
        <div id="ai-modal" class="modal fixed inset-0 z-50 hidden bg-black/50">
            <div class="modal-content bg-dark-800 rounded-lg mx-auto my-20 p-6 w-11/12 max-w-2xl relative max-h-[80vh] overflow-y-auto">
                <button id="close-ai-modal" class="close absolute top-4 right-4 text-2xl text-white hover:text-gray-300">&times;</button>
                <h2 class="text-2xl font-bold text-white mb-4">AI Decision Details</h2>
                <div id="ai-modal-content">
                    <!-- Modal content will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Strategy Modal -->
        <div id="strategy-modal" class="modal fixed inset-0 z-50 hidden bg-black/50">
            <div class="modal-content bg-dark-800 rounded-lg mx-auto my-20 p-6 w-11/12 max-w-3xl relative max-h-[80vh] overflow-y-auto">
                <button id="close-strategy-modal" class="close absolute top-4 right-4 text-2xl text-white hover:text-gray-300">&times;</button>
                <h2 class="text-2xl font-bold text-white mb-4">📋 Current Trading Strategy</h2>
                <div class="bg-dark-700 rounded-lg p-6 space-y-6">
                    <div class="strategy-section">
                        <h3 class="text-lg font-semibold text-purple-400 mb-3">BUY Criteria (ALL must be true)</h3>
                        <ul class="space-y-2 text-gray-300">
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>Price > EMA20</strong> by at least 0.3% (early entry)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>MACD > Signal</strong> AND MACD > price × 0.00005 (looser momentum)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>RSI between 35-75</strong> (catches rally starts, was 40-70)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>4H Trend:</strong> EMA20 > EMA50 × 0.999 AND ADX(14) > 20 (moderate trend, was >25)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>Volume Confirmation:</strong> Volume > 20MA × 0.9 AND > previous bar × 1.05</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-1">✓</span>
                                <span><strong>AI Confidence > 70%</strong> (balanced threshold)</span>
                            </li>
                        </ul>
                    </div>

                    <div class="strategy-section border-t border-dark-600 pt-6">
                        <h3 class="text-lg font-semibold text-red-400 mb-3">EXIT Strategy</h3>
                        <ul class="space-y-2 text-gray-300">
                            <li class="flex items-start gap-2">
                                <span class="text-yellow-400 mt-1">🎯</span>
                                <span><strong>Take Profit:</strong> +5% gain target</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-red-400 mt-1">🛑</span>
                                <span><strong>Stop Loss:</strong> -3% maximum loss</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-orange-400 mt-1">⚠️</span>
                                <span><strong>Trend Invalidation:</strong> Close if 2+ signals (Price < EMA20, MACD < 0, 4H ADX < 20, 4H trend reversed) AND P&L < 2%</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">🔒</span>
                                <span><strong>Trailing Stop:</strong> Move stop to breakeven at +5% profit</span>
                            </li>
                        </ul>
                    </div>

                    <div class="strategy-section border-t border-dark-600 pt-6">
                        <h3 class="text-lg font-semibold text-blue-400 mb-3">Risk Management</h3>
                        <ul class="space-y-2 text-gray-300">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">📊</span>
                                <span><strong>Leverage:</strong> Fixed 2x (safe leverage)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">💰</span>
                                <span><strong>Position Size:</strong> ~$20 per trade</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">⏰</span>
                                <span><strong>Trading Frequency:</strong> Every 10 minutes (AI analysis)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">🔄</span>
                                <span><strong>Monitoring:</strong> Every 1 minute (price updates, position monitoring)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-1">🎲</span>
                                <span><strong>Max Positions:</strong> One position per coin (10 coins max)</span>
                            </li>
                        </ul>
                    </div>

                    <div class="strategy-section border-t border-dark-600 pt-6">
                        <h3 class="text-lg font-semibold text-green-400 mb-3">Technical Indicators</h3>
                        <ul class="space-y-2 text-gray-300 text-sm">
                            <li><strong>EMA 20/50:</strong> Trend direction (3m and 4H timeframes)</li>
                            <li><strong>MACD (12,26,9):</strong> Momentum with signal line crossover</li>
                            <li><strong>RSI (7,14):</strong> Overbought/oversold conditions</li>
                            <li><strong>ATR (3,14):</strong> Volatility measurement</li>
                            <li><strong>ADX (14):</strong> Trend strength (Wilder's smoothing)</li>
                            <li><strong>Volume:</strong> 20-period MA confirmation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Modal -->
        <div id="menu-modal" class="menu-modal fixed inset-0 z-[60] hidden bg-black/50">
            <div class="menu-modal-content bg-dark-800 rounded-lg mx-auto my-20 p-6 w-11/12 max-w-md relative">
                <button id="close-menu" class="menu-close absolute top-4 right-4 text-2xl text-white hover:text-gray-300">&times;</button>
                <h2 class="text-2xl font-bold text-white mb-6 text-center">Navigation Menu</h2>
                <ul class="space-y-4">
                    <li><a href="/" class="block p-3 rounded-md hover:bg-dark-700 transition-colors text-gray-200">Home</a></li>
                    <li><a href="/documentation" class="block p-3 rounded-md hover:bg-dark-700 transition-colors text-gray-200">Documentation</a></li>
                    <li><a href="/about" class="block p-3 rounded-md hover:bg-dark-700 transition-colors text-gray-200">About Me</a></li>
                    <li><a href="https://github.com/erenilhan/trade" target="_blank" class="block p-3 rounded-md hover:bg-dark-700 transition-colors text-gray-200">GitHub Repository</a></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const API_URL = '/api/dashboard/data';

        function formatMoney(value) {
            return '$' + parseFloat(value).toFixed(2);
        }

        function formatPercent(value) {
            return (parseFloat(value) || 0).toFixed(2) + '%';
        }

        function renderDashboard(data) {
            const { account, positions, closed_positions, ai_logs, last_ai_run, stats } = data;

            // Update account stats
            document.getElementById('total-value').textContent = formatMoney(account.total_value);
            document.getElementById('cash-value').textContent = formatMoney(account.cash);
            document.getElementById('roi-value').textContent = formatPercent(account.roi);
            document.getElementById('win-rate').textContent = formatPercent(stats.win_rate);

            // Update Total P&L
            const totalPnl = account.realized_pnl || 0;
            const totalPnlEl = document.getElementById('total-pnl-value');
            const pnlColor = totalPnl >= 0 ? 'text-green-400' : 'text-red-400';
            totalPnlEl.className = `stat-value text-2xl font-bold ${pnlColor}`;
            totalPnlEl.textContent = (totalPnl >= 0 ? '+' : '') + formatMoney(totalPnl);

            // Update bot status
            const botStatus = document.getElementById('bot-status');
            const botStatusTooltip = document.getElementById('bot-status-tooltip');
            
            botStatusTooltip.textContent = `Last run: ${last_ai_run || 'Not available'}`;
            
            if (stats.bot_enabled) {
                botStatus.className = 'status-badge active inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium bg-green-900/50 text-green-300';
                botStatus.innerHTML = '<span class="status-dot w-2 h-2 rounded-full bg-green-500 animate-pulse"></span><span>Bot Active</span>';
            } else {
                botStatus.className = 'status-badge inactive inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium bg-red-900/50 text-red-300';
                botStatus.innerHTML = '<span class="status-dot w-2 h-2 rounded-full bg-red-500 animate-pulse"></span><span>Bot Offline</span>';
            }

            // Update AI provider info
            if (ai_logs && ai_logs.length > 0) {
                const firstLog = ai_logs[0];
                document.getElementById('ai-provider').textContent = firstLog.provider || 'N/A';
            }

            // Render open positions
            const openPositionsEl = document.getElementById('open-positions');
            if (positions.length === 0) {
                openPositionsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No open positions</div>';
            } else {
                openPositionsEl.innerHTML = positions.map(pos => {
                    const pnlColor = pos.pnl >= 0 ? 'text-green-400' : 'text-red-400';
                    const pnlEmoji = pos.pnl >= 0 ? '🟢' : '🔴';

                    let targetsHtml = '';
                    if (pos.targets) {
                        if (pos.targets.profit_target) {
                            const distPct = pos.targets.distance_to_profit_pct?.toFixed(2) || '0.00';
                            const priceNeeded = pos.targets.profit_needed?.toFixed(2) || '0.00';
                            targetsHtml += `
                                <div class="position-row flex justify-between py-1 bg-green-900/20 px-2 rounded mt-2">
                                    <span class="position-label text-green-400 text-sm">🎯 Target</span>
                                    <span class="position-value text-green-300 text-sm">
                                        $${pos.targets.profit_target.toFixed(2)} (${distPct}% / +$${priceNeeded})
                                    </span>
                                </div>
                            `;
                        }
                        if (pos.targets.stop_loss) {
                            const stopPct = pos.targets.distance_to_stop_pct?.toFixed(2) || '0.00';
                            const stopDist = pos.targets.stop_distance?.toFixed(2) || '0.00';
                            targetsHtml += `
                                <div class="position-row flex justify-between py-1 bg-red-900/20 px-2 rounded mt-1">
                                    <span class="position-label text-red-400 text-sm">🛑 Stop</span>
                                    <span class="position-value text-red-300 text-sm">
                                        $${pos.targets.stop_loss.toFixed(2)} (${stopPct}% buffer / $${stopDist})
                                    </span>
                                </div>
                            `;
                        }
                    }

                    const positionSize = pos.position_size || (pos.quantity * pos.entry_price);
                    const invested = positionSize / pos.leverage; // Real capital used (with leverage)

                    return `
                        <div class="position-card bg-dark-800 border border-dark-700 rounded-lg p-4 hover:shadow-lg transition-shadow">
                            <div class="position-header flex justify-between items-center pb-2 mb-2 border-b border-dark-700">
                                <div class="symbol font-semibold text-lg text-white">${pos.symbol}</div>
                                <div class="leverage-badge bg-blue-600 text-white px-2 py-1 rounded-full text-xs">${pos.leverage}x</div>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-400">💵 Capital</span>
                                <span class="position-value text-yellow-400 font-semibold">${formatMoney(invested)}</span>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-400">Entry</span>
                                <span class="position-value text-white">${formatMoney(pos.entry_price)}</span>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-400">Current</span>
                                <span class="position-value text-white font-semibold">${formatMoney(pos.current_price)}</span>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-400">P&L</span>
                                <span class="position-value ${pnlColor} font-bold">
                                    ${pnlEmoji} ${formatMoney(pos.pnl)} (${formatPercent(pos.pnl_percent)})
                                </span>
                            </div>
                            ${targetsHtml}
                            <div class="position-row flex justify-between py-1 mt-2 pt-2 border-t border-dark-700">
                                <span class="position-label text-gray-500 text-xs">Liq Price</span>
                                <span class="position-value text-gray-400 text-xs">${pos.liquidation_price ? formatMoney(pos.liquidation_price) : 'N/A'}</span>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-500 text-xs">Opened</span>
                                <span class="position-value text-gray-400 text-xs">${pos.opened_at || 'N/A'}</span>
                            </div>
                            <div class="position-row flex justify-between py-1">
                                <span class="position-label text-gray-500 text-xs">🔄 Updated</span>
                                <span class="position-value text-blue-400 text-xs font-semibold">${pos.price_updated_at || 'Never'}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // Render closed positions
            const closedPositionsEl = document.getElementById('closed-positions');
            if (closed_positions.length === 0) {
                closedPositionsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No closed positions</div>';
            } else {
                closedPositionsEl.innerHTML = `
                    <div class="grid grid-cols-6 bg-dark-700 text-gray-300 font-semibold p-3 text-sm">
                        <div>Symbol</div>
                        <div>💵 Invested</div>
                        <div>Entry</div>
                        <div>Leverage</div>
                        <div>💸 P&L</div>
                        <div>📅 Closed</div>
                    </div>
                    ${closed_positions.map(pos => {
                        const pnlColor = pos.pnl >= 0 ? 'text-green-400' : 'text-red-400';
                        const pnlEmoji = pos.pnl >= 0 ? '🟢' : '🔴';
                        const positionSize = pos.position_size || (pos.quantity * pos.entry_price);
                        const invested = positionSize / pos.leverage; // Real capital used (with leverage)
                        const closeReason = pos.pnl >= 0 ? '🎯 Take Profit' : '🛑 Stop Loss';

                        return `
                            <div class="grid grid-cols-6 p-3 border-b border-dark-700 hover:bg-dark-700/30 text-sm">
                                <div class="font-medium text-white">${pos.symbol}</div>
                                <div class="text-yellow-400 font-semibold">${formatMoney(invested)}</div>
                                <div class="text-gray-300">${formatMoney(pos.entry_price)}</div>
                                <div class="text-blue-400">${pos.leverage}x</div>
                                <div class="${pnlColor} font-semibold">
                                    ${pnlEmoji} ${formatMoney(pos.pnl)} (${formatPercent(pos.pnl_percent)})
                                </div>
                                <div>
                                    <div class="text-gray-400 text-xs">${pos.closed_at || 'N/A'}</div>
                                    <div class="text-gray-500 text-xs mt-1">${closeReason}</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;
            }

            // Render AI logs
            const aiLogsEl = document.getElementById('ai-logs');
            if (ai_logs.length === 0) {
                aiLogsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No AI logs available</div>';
            } else {
                // Process decisions from AI logs (limit to 10 total decisions)
                let aiLogItems = [];
                let decisionCount = 0;
                const maxDecisions = 10;

                for (const log of ai_logs) {
                    if (decisionCount >= maxDecisions) break;

                    if (log.decisions && log.decisions.length > 0) {
                        for (const decision of log.decisions) {
                            if (decisionCount >= maxDecisions) break;
                            // Color code actions
                            let actionClass = 'text-gray-400';
                            let actionBadge = decision.action.toUpperCase();
                            if (decision.action === 'buy') {
                                actionClass = 'text-green-400 font-bold';
                                actionBadge = '🟢 BUY';
                            } else if (decision.action === 'close_profitable') {
                                actionClass = 'text-blue-400 font-bold';
                                actionBadge = '🔵 CLOSE';
                            } else if (decision.action === 'stop_loss') {
                                actionClass = 'text-red-400 font-bold';
                                actionBadge = '🔴 STOP';
                            } else if (decision.action === 'hold') {
                                actionClass = 'text-yellow-400';
                                actionBadge = '⚪ HOLD';
                            }

                            aiLogItems.push(`
                                <div class="history-item grid grid-cols-5 p-3 border-b border-dark-700 hover:bg-dark-700/50 cursor-pointer ai-decision"
                                     data-provider="${log.provider.replace(/"/g, '&quot;')}"
                                     data-decision="${encodeURIComponent(JSON.stringify(decision))}"
                                     data-created-at="${log.created_at.replace(/"/g, '&quot;')}">
                                    <div class="font-medium text-white">${decision.symbol}</div>
                                    <div class="${actionClass}">${actionBadge}</div>
                                    <div class="text-gray-300">${(decision.confidence * 100).toFixed(0)}%</div>
                                    <div class="text-gray-400 text-sm">${log.created_at}</div>
                                    <div class="text-gray-400 truncate text-sm" title="${decision.reasoning}">${decision.reasoning.substring(0, 50)}...</div>
                                </div>
                            `);
                            decisionCount++;
                        }
                    }
                }
                
                aiLogsEl.innerHTML = `
                    <div class="grid grid-cols-5 bg-dark-700 text-gray-300 font-semibold p-3">
                        <div>Symbol</div>
                        <div>Action</div>
                        <div>Confidence</div>
                        <div>Time</div>
                        <div>Reasoning</div>
                    </div>
                    ${aiLogItems.join('')}
                `;
            }

            // Last AI run
            document.getElementById('last-ai-run').textContent = last_ai_run;
        }

        async function loadData() {
            try {
                const response = await fetch(API_URL);
                const result = await response.json();

                if (result.success) {
                    renderDashboard(result.data);
                } else {
                    console.error('Error:', result.error);
                }
            } catch (error) {
                console.error('Failed to load data:', error);
            }
        }

        // Modal functionality
        function openAiModal(provider, decision, createdAt) {
            const modal = document.getElementById('ai-modal');
            const modalContent = document.getElementById('ai-modal-content');
            
            // Create detailed view of the decision
            modalContent.innerHTML = `
                <div class="decision-detail bg-dark-700 rounded-lg p-4 mb-6">
                    <div class="decision-symbol text-xl font-bold text-white mb-4">${decision.symbol} AI Decision</div>
                    
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Provider:</span>
                        <span class="decision-value text-white">${provider}</span>
                    </div>
                    
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Action:</span>
                        <span class="decision-value text-white">${decision.action}</span>
                    </div>
                    
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Confidence:</span>
                        <span class="decision-value text-white">${(decision.confidence * 100).toFixed(2)}%</span>
                    </div>
                    
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Created:</span>
                        <span class="decision-value text-white">${createdAt}</span>
                    </div>
                    
                    ${decision.entry_price ? `
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Entry Price:</span>
                        <span class="decision-value text-white">${formatMoney(decision.entry_price)}</span>
                    </div>
                    ` : ''}
                    
                    ${decision.target_price ? `
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Target Price:</span>
                        <span class="decision-value text-white">${formatMoney(decision.target_price)}</span>
                    </div>
                    ` : ''}
                    
                    ${decision.stop_price ? `
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Stop Price:</span>
                        <span class="decision-value text-white">${formatMoney(decision.stop_price)}</span>
                    </div>
                    ` : ''}
                    
                    ${decision.invalidation ? `
                    <div class="decision-field flex justify-between py-2 border-b border-dark-600">
                        <span class="decision-label w-32 text-gray-400">Invalidation:</span>
                        <span class="decision-value text-white">${decision.invalidation}</span>
                    </div>
                    ` : ''}
                    
                    <div class="decision-reasoning bg-dark-600 rounded p-3 mt-4 italic">
                        <strong class="text-gray-300">Reasoning:</strong><br>
                        <span class="text-gray-200">${decision.reasoning}</span>
                    </div>
                </div>
            `;
            
            // Show the modal
            modal.classList.remove('hidden');
        }
        
        // Set up event listeners after a short delay to ensure DOM is loaded
        setTimeout(function() {
            // Initial load
            loadData();
            
            // Auto refresh every 60 seconds
            setInterval(loadData, 60000);
            
            // AI decision click handlers
            document.addEventListener('click', function(e) {
                if (e.target.closest('.ai-decision')) {
                    const decisionElement = e.target.closest('.ai-decision');
                    const provider = decisionElement.getAttribute('data-provider');
                    const decision = JSON.parse(decodeURIComponent(decisionElement.getAttribute('data-decision')));
                    const createdAt = decisionElement.getAttribute('data-created-at');
                    
                    openAiModal(provider, decision, createdAt);
                }
            });
            
            // Close AI modal
            document.getElementById('close-ai-modal').addEventListener('click', function() {
                document.getElementById('ai-modal').classList.add('hidden');
            });
            
            // Close AI modal when clicking outside
            document.getElementById('ai-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });

            // Strategy button click handler
            document.getElementById('strategy-btn').addEventListener('click', function() {
                document.getElementById('strategy-modal').classList.remove('hidden');
            });

            // Close strategy modal
            document.getElementById('close-strategy-modal').addEventListener('click', function() {
                document.getElementById('strategy-modal').classList.add('hidden');
            });

            // Close strategy modal when clicking outside
            document.getElementById('strategy-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });

            // Menu toggle functionality
            const menuToggle = document.getElementById('menu-toggle');
            const menuModal = document.getElementById('menu-modal');
            const closeMenu = document.getElementById('close-menu');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    if (menuModal) menuModal.classList.remove('hidden');
                });
            }
            
            if (closeMenu) {
                closeMenu.addEventListener('click', function() {
                    if (menuModal) menuModal.classList.add('hidden');
                });
            }
            
            // Close menu modal when clicking outside
            if (menuModal) {
                menuModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        this.classList.add('hidden');
                    }
                });
            }
            

        }, 100); // Small delay to ensure DOM is loaded
    </script>
    
    <footer class="mt-12 py-8 text-center">
        <p class="text-gray-500 text-sm">
            Eren Ilhan <br>erenilhan1(at)gmail.com
        </p>
        <a href="https://erenilhan.com" target="_blank" class="block text-blue-500 hover:text-blue-400 text-sm mt-2">
            erenilhan.com
        </a>
    </footer>
</body>
</html>