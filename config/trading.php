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
        'DOGE/USDT' => 'Dogecoin ($0.99B)',
        'XRP/USDT' => 'Ripple ($0.97B)',
        'HYPE/USDT' => 'Hype ($0.59B)',
        'ZEC/USDT' => 'Zcash ($0.55B)',
        'PEPE/USDT' => 'Pepe ($0.33B)',
        'LINK/USDT' => 'Chainlink ($0.31B)',
        'ADA/USDT' => 'Cardano ($0.26B)',
        'BCH/USDT' => 'Bitcoin Cash ($0.19B)',
        'DOT/USDT' => 'Polkadot ($0.18B)',
        'LTC/USDT' => 'Litecoin ($0.15B)',
        'MATIC/USDT' => 'Polygon ($0.14B)',
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
            'trigger' => 3,   // Activate at +3% profit
            'target' => -1,   // Move stop to -1%
        ],
        'level_2' => [
            'trigger' => 5,   // Activate at +5% profit
            'target' => 0,    // Move stop to breakeven (0%)
        ],
        'level_3' => [
            'trigger' => 8,   // Activate at +8% profit
            'target' => 3,    // Move stop to +3%
        ],
        'level_4' => [
            'trigger' => 12,  // Activate at +12% profit
            'target' => 6,    // Move stop to +6%
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
        'enabled' => env('DYNAMIC_POSITION_SIZE', false), // DISABLED - Using fixed $30
        'risk_percent' => env('RISK_PERCENT', 2.5), // 2.5% of balance per trade
        'min_position_size' => 10,  // Minimum $10
        'max_position_size' => 500, // Maximum $500
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
        'mid_cap' => ['SOL/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT'],
        'small_cap' => ['XRP/USDT', 'DOGE/USDT', 'PEPE/USDT', 'HYPE/USDT', 'ZEC/USDT'],

        // Normal volatility limits
        'normal' => [
            'max_large_cap' => 3,
            'max_mid_cap' => 3,
            'max_small_cap' => 4,
        ],

        // High volatility limits (reduce small cap exposure)
        'high_volatility' => [
            'max_large_cap' => 4,
            'max_mid_cap' => 3,
            'max_small_cap' => 2,
        ],
    ],
];
