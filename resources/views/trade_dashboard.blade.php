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
                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-dark-700 focus:outline-none">
                    <span id="theme-icon" class="text-xl">🌙</span>
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-green-400" id="total-value">$0.00</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Total Value</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-blue-400" id="cash-value">$0.00</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Cash</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-yellow-400" id="pnl-value">0%</div>
                <div class="stat-label text-sm text-gray-400 mt-1">ROI</div>
            </div>
            <div class="stat-card bg-dark-800 rounded-lg p-6 text-center">
                <div class="stat-value text-3xl font-bold text-purple-400" id="win-rate">0%</div>
                <div class="stat-label text-sm text-gray-400 mt-1">Win Rate</div>
            </div>
        </div>

        <!-- AI Logs Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-white">Recent AI Decisions</h2>
                <div class="text-sm text-gray-400">
                    <span>Provider: </span><span id="ai-provider" class="text-blue-400">-</span> | 
                    <span>Model: </span><span id="ai-model" class="text-blue-400">-</span>
                </div>
            </div>
            
            <div class="bg-dark-800 rounded-lg overflow-hidden">
                <div class="history-body p-4" id="ai-logs">
                    <div class="loading text-center py-8 text-gray-400">Loading AI logs...</div>
                </div>
            </div>
            
            <div class="text-right text-sm text-gray-400 mt-2">
                Last AI run: <span id="last-ai-run">N/A</span>
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
            <h2 class="text-xl font-semibold text-white mb-4">Closed Positions</h2>
            <div class="bg-dark-800 rounded-lg overflow-hidden">
                <div class="history-body p-4" id="closed-positions">
                    <div class="loading text-center py-8 text-gray-400">Loading positions...</div>
                </div>
            </div>
        </div>

        <!-- Refresh Button -->
        <div class="flex justify-center mb-8">
            <button id="refresh-btn" class="btn-primary px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                Refresh Data
            </button>
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
            document.getElementById('pnl-value').textContent = formatPercent(account.roi);
            document.getElementById('win-rate').textContent = formatPercent(stats.win_rate);

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

            // Update AI provider and model info
            if (ai_logs && ai_logs.length > 0) {
                const firstLog = ai_logs[0];
                document.getElementById('ai-provider').textContent = firstLog.provider || 'N/A';
                
                // If model information is available in the response data, use it
                // This assumes the API might include model info - if not available, we'll show N/A
                document.getElementById('ai-model').textContent = data.model || firstLog.provider || 'N/A';
            }

            // Render open positions
            const openPositionsEl = document.getElementById('open-positions');
            if (positions.length === 0) {
                openPositionsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No open positions</div>';
            } else {
                openPositionsEl.innerHTML = positions.map(pos => `
                    <div class="position-card bg-dark-800 border border-dark-700 rounded-lg p-4 hover:shadow-lg transition-shadow">
                        <div class="position-header flex justify-between items-center pb-2 mb-2 border-b border-dark-700">
                            <div class="symbol font-semibold text-lg text-white">${pos.symbol}</div>
                            <div class="leverage-badge bg-blue-600 text-white px-2 py-1 rounded-full text-xs">${pos.leverage}x</div>
                        </div>
                        <div class="position-row flex justify-between py-1">
                            <span class="position-label text-gray-400">Entry</span>
                            <span class="position-value text-white">${formatMoney(pos.entry_price)}</span>
                        </div>
                        <div class="position-row flex justify-between py-1">
                            <span class="position-label text-gray-400">Current</span>
                            <span class="position-value text-white">${formatMoney(pos.current_price)}</span>
                        </div>
                        <div class="position-row flex justify-between py-1">
                            <span class="position-label text-gray-400">P&L</span>
                            <span class="position-value ${pos.pnl >= 0 ? 'text-green-400' : 'text-red-400'}">
                                ${formatMoney(pos.pnl)} (${formatPercent(pos.pnl_percent)})
                            </span>
                        </div>
                        <div class="position-row flex justify-between py-1">
                            <span class="position-label text-gray-400">Opened</span>
                            <span class="position-value text-gray-300">${pos.opened_at || 'N/A'}</span>
                        </div>
                    </div>
                `).join('');
            }

            // Render closed positions
            const closedPositionsEl = document.getElementById('closed-positions');
            if (closed_positions.length === 0) {
                closedPositionsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No closed positions</div>';
            } else {
                closedPositionsEl.innerHTML = `
                    <div class="grid grid-cols-4 bg-dark-700 text-gray-300 font-semibold p-3">
                        <div>Symbol</div>
                        <div>Entry Price</div>
                        <div>P&L</div>
                        <div>Closed</div>
                    </div>
                    ${closed_positions.map(pos => `
                        <div class="grid grid-cols-4 p-3 border-b border-dark-700">
                            <div class="font-medium text-white">${pos.symbol}</div>
                            <div>${formatMoney(pos.entry_price)}</div>
                            <div class="${pos.pnl >= 0 ? 'text-green-400' : 'text-red-400'}">
                                ${formatMoney(pos.pnl)}
                            </div>
                            <div class="text-gray-400">${pos.closed_at || 'N/A'}</div>
                        </div>
                    `).join('')}
                `;
            }

            // Render AI logs
            const aiLogsEl = document.getElementById('ai-logs');
            if (ai_logs.length === 0) {
                aiLogsEl.innerHTML = '<div class="empty-state text-center py-8 text-gray-400">No AI logs available</div>';
            } else {
                // Process all decisions from all AI logs
                let aiLogItems = [];
                ai_logs.forEach(log => {
                    if (log.decisions && log.decisions.length > 0) {
                        log.decisions.forEach(decision => {
                            aiLogItems.push(`
                                <div class="history-item grid grid-cols-4 p-3 border-b border-dark-700 hover:bg-dark-700/50 cursor-pointer ai-decision"
                                     data-provider="${log.provider.replace(/"/g, '&quot;')}" 
                                     data-decision="${encodeURIComponent(JSON.stringify(decision))}" 
                                     data-created-at="${log.created_at.replace(/"/g, '&quot;')}">
                                    <div class="font-medium text-white">${log.provider}</div>
                                    <div>${decision.symbol} - ${decision.action} (${(decision.confidence * 100).toFixed(0)}%)</div>
                                    <div class="text-gray-400">${log.created_at}</div>
                                    <div class="text-gray-400 truncate" title="${decision.reasoning}">${decision.reasoning.substring(0, 60)}...</div>
                                </div>
                            `);
                        });
                    }
                });
                
                aiLogsEl.innerHTML = `
                    <div class="grid grid-cols-4 bg-dark-700 text-gray-300 font-semibold p-3">
                        <div>Provider</div>
                        <div>Action (Confidence)</div>
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
            // Refresh button
            document.getElementById('refresh-btn').addEventListener('click', loadData);

            // Auto refresh every 30 seconds
            setInterval(loadData, 30000);

            // Initial load
            loadData();
            
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
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            const body = document.body;
            
            // Check for saved theme preference or respect OS preference
            const savedTheme = localStorage.getItem('theme');
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
            
            if (savedTheme === 'light' || (!savedTheme && !prefersDarkScheme.matches)) {
                body.classList.remove('dark');
                themeIcon.textContent = '☀️'; // Sun icon for light theme
            } else {
                body.classList.add('dark');
                themeIcon.textContent = '🌙'; // Moon icon for dark theme
            }
            
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    if (body.classList.contains('dark')) {
                        body.classList.remove('dark');
                        themeIcon.textContent = '☀️'; // Sun icon for light theme
                        localStorage.setItem('theme', 'light');
                    } else {
                        body.classList.add('dark');
                        themeIcon.textContent = '🌙'; // Moon icon for dark theme
                        localStorage.setItem('theme', 'dark');
                    }
                });
            }
            
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