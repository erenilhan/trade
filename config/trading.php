<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Trading Pairs (By 24h Volume)
    |--------------------------------------------------------------------------
    |
    | List of all supported cryptocurrency pairs for trading.
    | Sorted by 24h trading volume for best liquidity.
    | This is the single source of truth for all supported coins.
    |
    */
    'supported_pairs' => [
        'ETH/USDT' => 'Ethereum ($15.8B)',
        'BTC/USDT' => 'Bitcoin ($13.2B)',
        'SOL/USDT' => 'Solana ($4.4B)',
        'BNB/USDT' => 'BNB ($2.7B)',
        'ZEC/USDT' => 'Zcash ($1.38B)',
        'XRP/USDT' => 'Ripple ($1.28B)',
        'DOGE/USDT' => 'Dogecoin ($0.80B)',
        'HYPE/USDT' => 'Hype ($0.65B)',
        'SUI/USDT' => 'Sui ($0.33B)',
        'LINK/USDT' => 'Chainlink ($0.23B)',
        'ADA/USDT' => 'Cardano ($0.26B)',
        'AVAX/USDT' => 'Avalanche ($0.27B)',
        'TAO/USDT' => 'Bittensor ($0.23B)',
        'BCH/USDT' => 'Bitcoin Cash ($0.21B)',
        'DOT/USDT' => 'Polkadot ($0.20B)',
        'ZEN/USDT' => 'Horizen ($0.17B)',
        'LTC/USDT' => 'Litecoin ($0.15B)',
        // 'PEPE/USDT' => 'Pepe ($0.26B)', // Not available on Binance Futures
        // 'MATIC/USDT' => 'Polygon ($0.14B)', // Not available on Binance Futures
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Active Pairs
    |--------------------------------------------------------------------------
    |
    | Default coins to trade when no custom selection is made.
    |
    */
    'default_active_pairs' => [
        'BTC/USDT',
        'ETH/USDT',
        'SOL/USDT',
        'BNB/USDT',
        'XRP/USDT',
        'DOGE/USDT',
        'ETH/USDT',
        'BTC/USDT',
        'SOL/USDT',
        'BNB/USDT',
        'ZEC/USDT',
        'XRP/USDT',
        'DOGE/USDT',
        'HYPE/USDT',
        'SUI/USDT',
        'LINK/USDT',
        'ADA/USDT',
        'AVAX/USDT',
        'TAO/USDT',
        'BCH/USDT',
        'DOT/USDT',
        'ZEN/USDT',
        'PEPE/USDT',
        'LTC/USDT',
        'MATIC/USDT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-Filtering Settings
    |--------------------------------------------------------------------------
    |
    | Settings for AI token optimization via pre-filtering
    |
    */
    'pre_filtering' => [
        'enabled' => env('ENABLE_PRE_FILTERING', true),
        'min_criteria' => 2, // Must pass at least 2 out of 4 criteria
    ],

    /*
    |--------------------------------------------------------------------------
    | Trailing Stop Levels
    |--------------------------------------------------------------------------
    |
    | Multi-level trailing stop configuration
    |
    */
    'trailing_stops' => [
        'level_1' => [
            'trigger' => 999,   // DISABLED - Historically 0% win rate (7 trades lost)
            'target' => -0.5,   // Not used (disabled)
        ],
        'level_2' => [
            'trigger' => 6,   // Activate at +6% profit (was +5%, increased to avoid premature triggers)
            'target' => 1,    // Move stop to +1% (was 0%, now preserves small profit instead of breakeven)
        ],
        'level_3' => [
            'trigger' => 8,   // Activate at +8% profit (unchanged, performs well - 100% win rate)
            'target' => 5,    // Move stop to +5% (unchanged, performs well)
        ],
        'level_4' => [
            'trigger' => 12,  // Activate at +12% profit (unchanged, performs well)
            'target' => 8,    // Move stop to +8% (unchanged, performs well)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Position Sizing
    |--------------------------------------------------------------------------
    |
    | Position size as percentage of account balance
    |
    */
    'dynamic_position_sizing' => [
        'enabled' => env('DYNAMIC_POSITION_SIZE', true), // ENABLED - Using confidence-based sizing
        'risk_percent' => env('RISK_PERCENT', 2.5), // 2.5% of balance per trade
        'min_position_size' => 10,  // Minimum $10
        'max_position_size' => 500, // Maximum $500
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence-Based Position Sizing
    |--------------------------------------------------------------------------
    |
    | Adjust position size based on AI confidence level
    |
    */
    'confidence_position_sizing' => [
        'enabled' => true,
        'low_confidence_range' => [0.65, 0.69],      // 65-69% confidence
        'low_confidence_size_factor' => 0.75,        // 75% of normal position size
        'medium_confidence_range' => [0.70, 0.79],   // 70-79% confidence
        'medium_confidence_size_factor' => 1.0,      // 100% of normal position size
        'high_confidence_range' => [0.80, 1.0],      // 80-100% confidence
        'high_confidence_size_factor' => 1.25,       // 125% of normal position size (capped at max leverage)
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Leverage (Volatility Based)
    |--------------------------------------------------------------------------
    |
    | Adjust leverage based on market volatility (ATR)
    |
    */
    'dynamic_leverage' => [
        'enabled' => env('DYNAMIC_LEVERAGE', true),
        'low_volatility_leverage' => 5,   // ATR < 70% of average
        'medium_volatility_leverage' => 3, // ATR between 70-130%
        'high_volatility_leverage' => 2,   // ATR > 130% of average
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Cooldown (Volatility Based)
    |--------------------------------------------------------------------------
    |
    | Adjust cooldown period based on market volatility
    |
    */
    'dynamic_cooldown' => [
        'enabled' => env('DYNAMIC_COOLDOWN', true),
        'low_volatility_minutes' => 120,  // Slow market, wait longer
        'medium_volatility_minutes' => 60, // Normal market
        'high_volatility_minutes' => 30,   // Fast market, trade more
    ],

    /*
    |--------------------------------------------------------------------------
    | Market Cap Diversification (Volatility Based)
    |--------------------------------------------------------------------------
    |
    | Adjust max positions per market cap based on volatility
    |
    */
    'market_cap_limits' => [
        'large_cap' => ['BTC/USDT', 'ETH/USDT', 'BNB/USDT'],
        'mid_cap' => ['SOL/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT', 'TAO/USDT', 'ZEN/USDT', 'BCH/USDT', 'LTC/USDT', 'MATIC/USDT'],
        'small_cap' => ['XRP/USDT', 'DOGE/USDT', 'PEPE/USDT', 'HYPE/USDT', 'ZEC/USDT', 'SUI/USDT'],

        // Normal volatility limits
        'normal' => [
            'max_large_cap' => 3,
            'max_mid_cap' => 5,
            'max_small_cap' => 4,
        ],

        // High volatility limits (reduce small cap exposure)
        'high_volatility' => [
            'max_large_cap' => 4,
            'max_mid_cap' => 4,
            'max_small_cap' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sleep Mode (Low Liquidity Hours - UTC Based)
    |--------------------------------------------------------------------------
    |
    | Trading restrictions during low liquidity hours (23:00-04:00 UTC)
    | This is when US markets are closed and Asian markets haven't opened yet.
    |
    */
    'sleep_mode' => [
        'enabled' => env('SLEEP_MODE_ENABLED', true),

        // UTC time range for sleep mode (23:00 - 04:00 UTC)
        'start_hour' => 23, // 23:00 UTC
        'end_hour' => 4,    // 04:00 UTC

        // During sleep mode
        'allow_new_trades' => false,        // No new entries during sleep
        'max_positions' => 3,               // Max 2-3 positions during sleep
        'tighter_stops' => true,            // Tighten stop losses
        'stop_multiplier' => 0.75,          // 25% tighter stops (3% → 2.25% for 2x)
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Max Drawdown Protection
    |--------------------------------------------------------------------------
    |
    | Auto-stop trading if daily drawdown exceeds limit
    |
    */
    'daily_max_drawdown' => [
        'enabled' => env('DAILY_MAX_DRAWDOWN_ENABLED', true),
        'max_drawdown_percent' => 8.0,      // Stop trading if daily loss > 8%
        'reset_hour_utc' => 0,              // Reset at midnight UTC
        'cooldown_hours' => 24,             // 24-hour cooldown after limit hit
    ],

    /*
    |--------------------------------------------------------------------------
    | Cluster Loss Cooldown
    |--------------------------------------------------------------------------
    |
    | Prevent emotional trading after consecutive losses
    |
    */
    'cluster_loss_cooldown' => [
        'enabled' => env('CLUSTER_LOSS_COOLDOWN_ENABLED', true),
        'consecutive_losses_trigger' => 3,  // Trigger after 3 consecutive losses
        'cooldown_hours' => 24,             // 24-hour trading pause
        'lookback_hours' => 24,             // Look at last 24 hours of trades
    ],
];
