# AI-Powered Multi-Coin Crypto Trading Bot

**⚠️ ATTENTION: THIS PROJECT IS UNDER ACTIVE DEVELOPMENT!**

This is an open-source project and is still in the development and testing phase. **Using it with real money in live mode is strongly discouraged.** Please conduct comprehensive tests in `mock` or `testnet` modes first. During development, there may be bugs, unexpected behavior, or issues that could lead to financial loss. **Use at your own risk.**

---

AI-driven cryptocurrency trading bot built with Laravel 12, FilamentPHP v4, and multi-provider AI support (DeepSeek/OpenRouter/OpenAI). Trades 6 cryptocurrencies simultaneously on Binance Futures with advanced technical analysis and risk management.

## Supported Coins (Active)

- BTC/USDT
- ETH/USDT
- SOL/USDT
- BNB/USDT
- XRP/USDT
- DOGE/USDT

**Note:** Coin list simplified from 19 to 6 main coins for better focus and stability.

## Recent Optimizations (Dec 2025)

### SHORT Trading Improvements
- **ADX Threshold**: Reduced from 25 to 20 (more opportunities)
- **RSI Range**: Expanded SHORT range from 28-55 to 25-60
- **Volume Filters**: Lowered from 1.3x/1.5x to 0.9x/1.0x (realistic for crypto)
- **Max Positions**: Reduced from 4 to 3 (prevent overtrading)
- **L2 Trailing Stop**: Moved trigger from 6% to 8% (avoid premature exits)
- **Coin Focus**: Simplified to 6 main coins for stability

### Expected Impact
- Increased SHORT trading opportunities
- Better trend detection with ADX 20
- More balanced LONG/SHORT ratio
- Improved overall win rate

## Technical Indicators (Active)

All indicators are calculated in pure PHP without external libraries in `MarketDataService.php:164-230`.

### Trend Indicators

1. **EMA (Exponential Moving Average)** - Lines 236-273
   - EMA20 (20-period)
   - EMA50 (50-period)
   - Used to identify trend direction and dynamic support/resistance

2. **MACD (Moving Average Convergence Divergence)** - Lines 278-313
   - Fast EMA: 12-period
   - Slow EMA: 26-period
   - Signal line: 9-period EMA of MACD
   - Histogram: MACD - Signal
   - Identifies momentum and trend changes

3. **ADX (Average Directional Index)** - Lines 407-479
   - Period: 14
   - +DI (Plus Directional Indicator)
   - -DI (Minus Directional Indicator)
   - Uses Wilder's Smoothing method
   - Measures trend strength (>20 = strong trend)

4. **Supertrend** - Lines 705-796
   - Period: 10
   - Multiplier: 3.0
   - ATR-based trend following indicator
   - Returns bullish/bearish trend signals

### Momentum Indicators

5. **RSI (Relative Strength Index)** - Lines 319-368
   - RSI7 (7-period)
   - RSI14 (14-period)
   - Uses Wilder's Smoothing method
   - Identifies overbought/oversold conditions

6. **Stochastic RSI** - Lines 639-692
   - RSI Period: 14
   - Stochastic Period: 14
   - %K and %D values
   - More sensitive than regular RSI for momentum shifts

### Volatility Indicators

7. **ATR (Average True Range)** - Lines 374-401
   - ATR3 (3-period) - short-term volatility
   - ATR14 (14-period) - standard volatility
   - Uses Wilder's Smoothing method
   - Used for dynamic stop loss calculation

8. **Bollinger Bands** - Lines 578-619
   - Period: 20
   - Standard Deviation: 2.0
   - Upper Band, Middle Band (SMA), Lower Band
   - Bandwidth: measures volatility
   - %B: price position relative to bands

### Volume Indicators

9. **Volume Ratio** - Lines 192-194, 624-632
   - Current volume vs 20-period moving average
   - Volume MA: 20-period
   - Used for liquidity and breakout confirmation

### Market Data (Futures)

10. **Funding Rate** - Lines 484-499
    - Binance Futures funding rate
    - Indicates market sentiment (longs vs shorts)

11. **Open Interest** - Lines 504-518
    - Total open futures positions
    - Measures market participation

## Trading Logic

### Entry Logic (LONG Positions)

Located in `MultiCoinAIService.php:449-455`

**All 5 criteria must be TRUE:**

1. **MACD Bullish**: MACD > Signal AND MACD > 0
2. **RSI Healthy**: RSI(7) between 45-72 (not overbought)
3. **Price Above EMA**: 0-2% above EMA20 (riding uptrend)
4. **4H Strong Uptrend**: EMA20 > EMA50 AND ADX > 20
5. **Volume Confirmation**: Volume Ratio ≥ 1.0x

**Confidence-Based Filtering:**
- MIN confidence: 60% (below = HOLD)
- MAX confidence: 82% (above = HOLD due to inverse correlation)
- BEST range: 60-69% (57.1% win rate historically)
- WORST range: 80-84% (28.6% win rate - overconfidence trap)

### Entry Logic (SHORT Positions)

Located in `MultiCoinAIService.php:456-462`

**All 5 criteria must be TRUE:**

1. **MACD Bearish**: MACD < Signal AND MACD < 0
2. **RSI Healthy**: RSI(7) between 25-60 (not oversold)
3. **Price Below EMA**: 0-2% below EMA20 (riding downtrend)
4. **4H Strong Downtrend**: EMA20 < EMA50 AND ADX > 20
5. **Volume Confirmation**: Volume Ratio ≥ 1.0x

### Pre-Filtering System

Located in `MultiCoinAIService.php:99-195`

Reduces AI token usage by 70%+ by pre-filtering uninteresting coins:

**Time-Aware Volume Thresholds:**
- US trading hours (13:00-22:00 UTC): Min 0.9x volume
- Off-hours: Min 1.0x volume

**Scoring System (Need 3/5 to pass):**
- MACD alignment with 4H trend
- RSI in healthy range
- Price near EMA20 (±2-5%)
- Strong ADX (>25 preferred)
- Volume already checked above

**Additional Filters:**
- Skip coins with open positions
- Skip 4H ADX < 20 (too weak)
- Skip ATR > 8% (too volatile)
- Max 2 positions per coin in 6 hours (anti-overtrading)

### Exit Logic

Located in `MonitorPositions.php:53-314`

#### 1. Take Profit
- **LONG**: Current price ≥ Profit Target (+6% base)
- **SHORT**: Current price ≤ Profit Target (-6% base)

#### 2. Stop Loss
- **LONG**: ATR-based dynamic stop (2.5x ATR14, min 5%, max 15%)
- **SHORT**: ATR-based dynamic stop (2.5x ATR14, min 5%, max 15%)

#### 3. Multi-Level Trailing Stops

**Level 4** (L4) - Lines 232-250
- **Trigger**: +12% profit
- **Action**: Move stop to +6% (lock in big profit)

**Level 3** (L3) - Lines 251-269
- **Trigger**: +8% profit
- **Action**: Move stop to +3% (lock in profit)

**Level 2** (L2) - Lines 270-289
- **Trigger**: +8% profit (moved from 6% to avoid premature exits)
- **Action**: Move stop to +2% (preserve profit safely)

**Level 1** (L1) - Disabled - Line 229
- Historical data: 0% win rate (7 trades lost)
- Trigger set to 999% (effectively disabled)

#### 4. Trend Invalidation - Lines 160-208

Early warning system that closes positions when trend reverses:

**Invalidation Signals:**
- Price < EMA20
- MACD turned negative
- 4H ADX < 20 (weak trend)
- 4H EMA20 < EMA50 (trend reversal)

**Rules:**
- 2+ signals + PNL < 2% = Close immediately
- 3+ signals = Close regardless of PNL

#### 5. Liquidation Protection - Lines 136-157

Emergency exit system:
- Warning at 10% from liquidation price
- Auto-close at 3% from liquidation price

#### 6. Sleep Mode Tighter Stops - Lines 66-102

During low liquidity hours (23:00-04:00 UTC):
- Stop loss tightened by 0.75x multiplier (25% tighter)
- Reduces slippage risk during thin market conditions

### Position Sizing

Located in `MultiCoinTradingController.php:195-244`

- Base position: $10 USDT (configurable via `position_size_usdt`)
- Leverage: 2x (fixed based on historical performance data)
- Notional value: $10 × 2x = $20

**Historical Leverage Performance:**
- 2x leverage: BEST win rate
- 3x leverage: Lower win rate
- 5x+ leverage: Significantly worse results

### Risk Management

#### Daily Risk Limits - Lines 60-106

1. **Max Drawdown**: 8% per day (DailyStat model)
   - Stops all trading if daily loss exceeds 8%
   - Resets at midnight UTC

2. **Cluster Loss Cooldown**: 3 consecutive losses
   - Pauses trading for N hours after 3 straight losses
   - Prevents emotional revenge trading
   - Can be overridden via `manual_cooldown_override` setting

3. **Sleep Mode**: 23:00-04:00 UTC
   - No new trades during low liquidity hours
   - Existing positions monitored with tighter stops
   - Max 0 positions allowed during sleep

#### Position Limits

- Max 3 open positions total (reduced from 4 to prevent overtrading)
- Max 1 position per symbol
- Max 2 positions per symbol in 6 hours (anti-overtrading)

#### Cash Requirements

- Skip BTC/ETH/BNB if cash < $10
- Minimum $10 total cash to run AI analysis

## System Prompt Strategy

Located in `MultiCoinAIService.php:428-498`

**Philosophy**: KISS (Keep It Simple, Stupid) - Back to basics

### Core Strategy

- Trade WITH the 4H trend
- Time entries on 3m chart
- 5 simple rules for LONG, 5 for SHORT
- Volume confirmation critical
- Simple beats complex

### Volume Quality Tiers

- **Excellent** (≥1.5x): High liquidity, full confidence
- **Good** (1.2-1.5x): Normal liquidity, standard risk
- **Acceptable** (1.0-1.2x): Moderate liquidity, elevated risk
- **Weak** (<1.0x): Pre-filtered out

### HOLD Conditions

- Criteria not met (any of 5 rules fails)
- ATR > 8% (too volatile)
- Confidence < 60%
- 4H ADX < 20 (sideways market)
- Volume < 1.0x (already filtered)

### Portfolio Management

- Max 1-2 new positions per cycle
- Skip if 4+ positions already open
- Mix LONG and SHORT when possible (hedge risk)
- Use 2x leverage for all trades

## Execution Flow

### 1. Market Data Collection
`MarketDataService::collectAllMarketData()` - Line 46

- Fetch OHLCV for all 6 coins
- Calculate all technical indicators
- Get funding rate & open interest
- Store in `market_data` table

### 2. Pre-Filtering
`MultiCoinAIService::buildMultiCoinPrompt()` - Line 89

- Skip coins with open positions
- Apply time-aware volume filters
- Score coins (need 3/5 criteria)
- Filter out weak ADX (< 20)
- Check overtrading limits

### 3. AI Decision
`MultiCoinAIService::makeDecision()` - Line 38

- Build detailed prompt with market data
- Call AI provider (DeepSeek/OpenRouter)
- Parse JSON response
- Log to `ai_logs` table

### 4. Confidence Filtering
`MultiCoinTradingController::execute()` - Lines 124-141

- Block >82% confidence (inverse correlation trap)
- Block <60% confidence (too uncertain)
- Cap leverage at 2x for 75-82% range

### 5. Order Execution
`MultiCoinTradingController::executeBuy()/executeSell()` - Lines 181-459

**LONG Flow:**
- Check existing position
- Calculate ATR-based stop loss (2.5x ATR14)
- Set leverage on Binance
- Send MARKET BUY order
- Create position record with exit plan
- Log trade to `trades` table

**SHORT Flow:**
- Check existing position
- Calculate ATR-based stop loss (2.5x ATR14)
- Set leverage on Binance
- Send MARKET SELL order (open SHORT)
- Create position record with exit plan
- Log trade to `trades` table

### 6. Position Monitoring
`MonitorPositions` command (runs every 1-3 minutes)

- Update current prices
- Check take profit
- Check stop loss
- Check liquidation danger
- Check trend invalidation
- Apply multi-level trailing stops
- Auto-close when conditions met

## Key Files

### Services
- `app/Services/MarketDataService.php` - Technical indicator calculations
- `app/Services/MultiCoinAIService.php` - AI decision engine
- `app/Services/BinanceService.php` - Exchange API wrapper
- `app/Services/AIService.php` - Multi-provider AI abstraction

### Controllers
- `app/Http/Controllers/Api/MultiCoinTradingController.php` - Trade execution

### Commands
- `app/Console/Commands/MonitorPositions.php` - Position monitoring & auto-close

### Models
- `app/Models/Position.php` - Position data model
- `app/Models/Trade.php` - Individual order records
- `app/Models/MarketData.php` - Time-series indicator data
- `app/Models/BotSetting.php` - Key-value configuration
- `app/Models/DailyStat.php` - Daily performance tracking

## Configuration

### Environment Variables

```bash
# Trading Mode
TRADING_MODE=mock|testnet|live

# AI Provider
AI_PROVIDER=openrouter|deepseek|openai
OPENROUTER_API_KEY=your_key
DEEPSEEK_API_KEY=your_key
OPENAI_API_KEY=your_key

# Binance
BINANCE_API_KEY=your_key
BINANCE_API_SECRET=your_secret
BINANCE_TESTNET=false
```

### Bot Settings (Dynamic)

Configurable via `BotSetting` model or Filament admin:

```php
// Trading Parameters
position_size_usdt: 10
max_leverage: 2
initial_capital: 10000

// AI Settings
ai_provider: "openrouter"
use_ai: true
enable_pre_filtering: true

// Trailing Stops
trailing_stop_l2_trigger: 6
trailing_stop_l2_target: 1
trailing_stop_l3_trigger: 8
trailing_stop_l3_target: 3
trailing_stop_l4_trigger: 12
trailing_stop_l4_target: 6

// Cooldown
manual_cooldown_override: false
```

### Config Files

- `config/trading.php` - Sleep mode, cluster loss cooldown
- `config/openrouter.php` - OpenRouter model selection
- `config/deepseek.php` - DeepSeek API settings

## 🚀 Quick Start

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL or PostgreSQL database
- Binance API Key and Secret (for `testnet` or `live` mode)
- AI Provider API Key (OpenRouter, DeepSeek, or OpenAI)

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/your-repo-name.git
   cd your-repo-name
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies:**
   ```bash
   npm install
   npm run dev
   ```

4. **Set up environment variables:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Run database migrations:**
   ```bash
   php artisan migrate
   ```

6. **Create Filament admin user:**
   ```bash
   php artisan make:filament-user
   ```

7. **Start development servers:**
   ```bash
   composer dev
   # or manually:
   php artisan serve --port=8000
   php artisan queue:listen
   php artisan pail
   npm run dev
   ```

8. **Enable AI trading:**
   ```bash
   php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true);"
   ```

9. **Start trading cycle:**
   ```bash
   curl -X POST http://localhost:8000/api/multi-coin/execute
   ```

## API Endpoints

### Multi-Coin Trading
```bash
# Execute trading for all coins
POST /api/multi-coin/execute

# Get current status
GET /api/multi-coin/status
```

### Legacy Single-Coin
```bash
POST /api/trade/execute
GET /api/trade/status
POST /api/trade/buy
POST /api/trade/close/{id}
```

## Commands

```bash
# Start development servers
composer dev

# Monitor positions (auto-close)
php artisan positions:monitor

# Manage coins (add/remove/sync from Binance)
php artisan coins:manage
php artisan coins:sync-binance

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Testing
composer test
```

## Development Workflow

### 1. Mock Trading (Safe Testing)

```bash
# .env
TRADING_MODE=mock

# Test execution
curl -X POST http://localhost:8000/api/multi-coin/execute

# Monitor logs
php artisan pail | grep "🤖"
```

### 2. Check Results

```bash
# Database queries
php artisan tinker

Position::with('trades')->latest()->first();
MarketData::where('symbol', 'BTC/USDT')->latest()->first();
BotSetting::get('position_size_usdt');
```

### 3. Admin Panel

Visit `http://localhost:8000/admin`

View:
- Positions (open/closed)
- Trades (order history)
- Trade Logs (AI decisions)
- Bot Settings (configuration)

## 📊 Monitoring

- **Dashboard:** View real-time balance, recent trades, and P&L at `/trade-dashboard`
- **Filament Admin Panel:** Access detailed logs at `/admin`
- **Log Files:** Monitor bot activities:
  ```bash
  tail -f storage/logs/laravel.log | grep "🤖"
  ```

## Performance Metrics (Historical)

### Confidence Analysis
- **60-69%**: 57.1% win rate (BEST)
- **70-74%**: Moderate performance
- **75-79%**: Declining performance
- **80-84%**: 28.6% win rate (WORST - inverse correlation)
- **85%+**: Blocked (overconfidence trap)

### Leverage Analysis
- **2x**: Highest win rate (CURRENT)
- **3x**: Lower win rate
- **5x+**: Poor win rate

### Trailing Stop Performance
- **L1** (3% trigger): 0% win rate - DISABLED
- **L2** (6% trigger): Good performance
- **L3** (8% trigger): Good performance
- **L4** (12% trigger): Excellent performance

### Pre-Filtering Impact
- Reduces AI calls by 70%+
- Focuses AI on high-probability setups
- Saves token costs
- Prevents trading in choppy markets

## Safety Features

1. **Sandboxed Testing**: Mock mode with simulated balance
2. **Testnet Support**: Test with Binance testnet before live
3. **Dynamic Stop Loss**: ATR-based, adapts to volatility
4. **Liquidation Protection**: Emergency close at 3% distance
5. **Trend Invalidation**: Early exit when setup breaks
6. **Daily Drawdown Limit**: 8% max loss per day
7. **Cluster Loss Cooldown**: Pause after 3 consecutive losses
8. **Sleep Mode**: No trading during low liquidity hours
9. **Confidence Filtering**: Block overconfident AI (>82%)
10. **Anti-Overtrading**: Max 2 same-coin trades per 6h

## 🤝 Contributing

Contributions are welcome! Please open an issue or submit a pull request.

See `CLAUDE.md` for development guidelines and architecture details.

## Documentation

- `CLAUDE.md` - Project instructions for Claude Code
- `MULTI_COIN_USAGE.md` - Multi-coin system guide
- `AI_PROVIDER_GUIDE.md` - AI provider setup
- `QUICK_START.md` - Common tasks
- `API_USAGE.md` - API endpoints
- `OPENROUTER_SETUP.md` - OpenRouter + DeepSeek setup
- `TEST_AI_TRADING.md` - AI testing scenarios
- `MULTI_COIN_PLAN.md` - Implementation roadmap

## 📄 License

MIT License - see LICENSE file for details

## 🔒 Security

- **API Keys**: Never commit API keys to the repository. Always use `.env` file
- **Trading Mode**: Always start with `mock` or `testnet` mode before using `live` mode

---

**Note:** This bot is for educational and experimental purposes. Cryptocurrency trading involves significant risks, and you could lose money. Use at your own risk. The system is currently under development and testing - proceed with caution.
