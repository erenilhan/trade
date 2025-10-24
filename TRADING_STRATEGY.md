# Trading Strategy Documentation

## Overview

This document describes the complete trading strategy implemented in the multi-coin AI trading bot. The strategy uses dynamic risk management, volatility-based adjustments, and AI-powered decision making to trade 6+ cryptocurrencies simultaneously on Binance Futures.

---

## Core Strategy Components

### 1. AI-Powered Decision Making

**AI Provider**: DeepSeek Chat V3.1 (via OpenRouter)

**Decision Process**:
- AI analyzes market data for all supported coins every 10 minutes
- Reviews technical indicators: EMA, MACD, RSI, ATR, ADX, Funding Rate, Open Interest
- Analyzes both 3-minute (short-term) and 4-hour (long-term) timeframes
- Returns decisions: `buy` or `hold` with confidence score (0-1)

**Confidence Threshold**:
- Minimum confidence: **0.70** (70%)
- Trades with confidence < 0.70 are automatically rejected
- Higher confidence trades historically show better win rates

---

## 2. Fixed Position Sizing

**Strategy**: Use a fixed position size per trade

**Configuration**:
```php
'dynamic_position_sizing' => [
    'enabled' => false,  // DISABLED - Using fixed amount
]

BotSetting::set('position_size_usdt', 30);  // Fixed $30 per trade
```

**Benefits**:
- Simple and predictable position sizing
- No calculation overhead
- Easy to track exact risk per trade

---

## 3. AI-Powered Leverage Selection

**Strategy**: AI analyzes market conditions and selects optimal leverage (2-10x) for each trade

**AI Decision Criteria**:
- **Signal Strength**: Strong technical signals → higher leverage (up to 10x)
- **Volatility (ATR)**: High volatility → lower leverage (2-3x), Low volatility → higher leverage (5-10x)
- **Trend Strength (ADX)**: Strong trend (ADX > 25) → can use higher leverage (7-10x)
- **Confidence Level**: High confidence trades → can justify higher leverage

**Leverage Range**: 2x - 10x (configurable via `max_leverage` setting)

**Examples**:
- **Strong Setup**: Bullish trend + low volatility + ADX > 30 + confidence 0.85 → AI chooses **8-10x**
- **Moderate Setup**: Normal trend + medium volatility + ADX 20-25 + confidence 0.75 → AI chooses **4-6x**
- **Weak Setup**: Choppy market + high volatility + low ADX + confidence 0.70 → AI chooses **2-3x**

**Fallback**:
If AI doesn't specify leverage, system falls back to volatility-based calculation:
- Low volatility → 5x
- Medium volatility → 3x
- High volatility → 2x

**Benefits**:
- AI considers multiple factors simultaneously (volatility, trend, momentum, volume)
- Each coin gets custom leverage based on its specific setup
- More aggressive on high-probability setups
- More conservative on uncertain setups
- Adapts to real-time market conditions

---

## 4. Dynamic Cooldown (Volatility-Based)

**Strategy**: Trade more frequently in fast markets, less frequently in slow markets

**Configuration**:
```php
'dynamic_cooldown' => [
    'enabled' => true,
    'low_volatility_minutes' => 120,   // Wait 2 hours
    'medium_volatility_minutes' => 60,  // Wait 1 hour
    'high_volatility_minutes' => 30,    // Wait 30 minutes
]
```

**Logic**:
- After closing a position, bot won't trade the same coin for a cooldown period
- **Low volatility** (slow market): 120 min cooldown (avoid overtrading)
- **Medium volatility**: 60 min cooldown (normal frequency)
- **High volatility** (fast market): 30 min cooldown (capture opportunities)

**Benefits**:
- Prevents overtrading in choppy, directionless markets
- Allows multiple entries in strong trending (volatile) markets
- Reduces trading costs (fees) when opportunities are scarce

---

## 5. Market Cap Diversification (Volatility-Adjusted)

**Strategy**: Limit exposure per market cap segment, adjust based on volatility

**Market Cap Classification**:
```php
'large_cap' => ['BTC/USDT', 'ETH/USDT', 'BNB/USDT']
'mid_cap' => ['SOL/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT']
'small_cap' => ['XRP/USDT', 'DOGE/USDT', 'PEPE/USDT', 'HYPE/USDT', 'ZEC/USDT']
```

**Position Limits**:

**Normal Market Conditions**:
- Max 3 large cap positions
- Max 3 mid cap positions
- Max 4 small cap positions
- **Total**: Up to 10 positions

**High Volatility Conditions**:
- Max 4 large cap positions (increased safety)
- Max 3 mid cap positions
- Max 2 small cap positions (reduced risk)
- **Total**: Up to 9 positions

**Benefits**:
- Prevents concentration risk in one market segment
- Reduces small cap exposure during volatile periods
- Increases stable large cap exposure when markets are risky
- Ensures portfolio diversification

---

## 6. Multi-Level Trailing Stops

**Strategy**: Lock in profits progressively as position becomes more profitable

**Configuration**:
```php
'trailing_stops' => [
    'level_1' => ['trigger' => 3,  'target' => -1],  // +3% profit → stop at -1%
    'level_2' => ['trigger' => 5,  'target' => 0],   // +5% profit → stop at 0% (breakeven)
    'level_3' => ['trigger' => 8,  'target' => 3],   // +8% profit → stop at +3%
    'level_4' => ['trigger' => 12, 'target' => 6],   // +12% profit → stop at +6%
]
```

**How It Works**:
1. Position opened at $100 with 3x leverage
2. Price reaches +3% ($103) → Stop loss moved to $99 (-1%)
3. Price reaches +5% ($105) → Stop loss moved to $100 (breakeven)
4. Price reaches +8% ($108) → Stop loss moved to $103 (+3% locked)
5. Price reaches +12% ($112) → Stop loss moved to $106 (+6% locked)

**Benefits**:
- Protects profits automatically
- Lets winners run while securing gains
- Reduces emotional decision-making
- Maximizes profit on strong trends

**Monitoring**:
- Runs every 1 minute via `php artisan positions:monitor`
- Automatically updates stop loss orders on Binance
- Never moves stop loss backwards (only forward)

---

## 7. Pre-Filtering (Token Optimization)

**Strategy**: Only send coins with promising setups to AI (saves 70% of AI costs)

**Criteria** (Must pass 2 out of 4):
1. **Price > EMA20**: Price above 20-period EMA (uptrend)
2. **MACD > Signal**: MACD line above signal line (bullish momentum)
3. **RSI 35-75**: RSI not oversold or overbought
4. **4H Trend**: 4-hour EMA20 > EMA50 (long-term uptrend)

**Configuration**:
```php
'pre_filtering' => [
    'enabled' => true,
    'min_criteria' => 2,  // Must pass at least 2 criteria
]
```

**Example**:
- 15 coins monitored
- 8 coins pass pre-filtering (sent to AI)
- 7 coins filtered out (not sent to AI)
- **Result**: ~50% cost savings + faster decisions

---

## 8. Risk Management Rules

### Position Entry Rules:
1. **Minimum Confidence**: 0.70 (70%)
2. **Minimum Cash**: $1 USDT available
3. **Cooldown Check**: No recent trade on same coin
4. **Diversification Check**: Market cap limits not exceeded
5. **Existing Position Check**: No open position on same coin

### Position Exit Rules:
1. **Take Profit Target**: Set by AI (typically +5-10%)
2. **Stop Loss**: Set by AI (typically -3-5%)
3. **Trailing Stops**: 4 levels to lock profits
4. **Liquidation Distance**: Monitored continuously
5. **Invalidation Condition**: AI-defined exit criteria

### Emergency Exit Conditions:
- Liquidation price < 10% away → Close position
- Stop loss triggered → Immediate market close
- Bot disabled manually → Hold positions, stop trading

---

## 9. Supported Trading Pairs

**Primary Active Pairs** (Default):
1. **BTC/USDT** - Bitcoin ($13.2B volume)
2. **ETH/USDT** - Ethereum ($15.8B volume)
3. **SOL/USDT** - Solana ($4.4B volume)
4. **BNB/USDT** - BNB ($2.7B volume)
5. **XRP/USDT** - Ripple ($0.97B volume)
6. **DOGE/USDT** - Dogecoin ($0.99B volume)

**Additional Available Pairs**:
7. HYPE/USDT - Hype ($0.59B volume)
8. ZEC/USDT - Zcash ($0.55B volume)
9. PEPE/USDT - Pepe ($0.33B volume)
10. LINK/USDT - Chainlink ($0.31B volume)
11. ADA/USDT - Cardano ($0.26B volume)
12. BCH/USDT - Bitcoin Cash ($0.19B volume)
13. DOT/USDT - Polkadot ($0.18B volume)
14. LTC/USDT - Litecoin ($0.15B volume)
15. MATIC/USDT - Polygon ($0.14B volume)

**Configuration**: Managed in `config/trading.php` and Bot Settings page

---

## 10. Technical Indicators Used

### Short-Term Analysis (3-minute timeframe):
- **EMA 20**: 20-period exponential moving average
- **MACD (12,26,9)**: Trend and momentum indicator
- **RSI 7**: 7-period relative strength index
- **ATR 3**: 3-period average true range (volatility)
- **ADX**: Average directional index (trend strength)
- **Plus DI / Minus DI**: Directional indicators

### Long-Term Context (4-hour timeframe):
- **EMA 20 & 50**: Trend identification
- **ATR 14**: Volatility measurement
- **Volume**: Trading activity
- **Funding Rate**: Market sentiment (long/short bias)
- **Open Interest**: Total open positions

---

## 11. Trading Schedule

**Market Data Collection**:
- Runs every **3 minutes** via `php artisan schedule:work`
- Collects OHLCV data for all active pairs
- Calculates all technical indicators
- Stores in `market_data` table

**AI Trading Decisions**:
- Runs every **10 minutes** via scheduler
- Analyzes all coins with fresh data
- Executes buy orders based on AI recommendations
- Logs all decisions to `ai_logs` table

**Position Monitoring**:
- Runs every **1 minute** via scheduler
- Updates current prices for all open positions
- Applies trailing stop logic
- Checks for stop loss / take profit triggers

---

## 12. Performance Metrics Tracked

### Account Metrics:
- **Total Value**: Current account balance (free + locked)
- **Available Cash**: USDT available for trading
- **ROI**: Return on investment since start
- **Realized P&L**: Total profit/loss from closed trades
- **Unrealized P&L**: Current profit/loss from open positions

### Trading Statistics:
- **Win Rate**: % of profitable closed positions
- **Total Trades**: Number of completed trades
- **Wins / Losses**: Count of profitable vs unprofitable trades
- **Avg Win**: Average profit per winning trade
- **Avg Loss**: Average loss per losing trade
- **Profit Factor**: Total wins ÷ Total losses

### AI Performance Metrics:
- **Total AI Trades**: Trades executed by AI
- **Avg Confidence**: Average confidence score of all trades
- **High Confidence Win Rate**: Win rate for confidence ≥ 80%
- **Medium Confidence Win Rate**: Win rate for confidence 70-79%
- **Low Confidence Win Rate**: Win rate for confidence < 70%
- **Avg Confidence (Wins)**: Average confidence of winning trades
- **Avg Confidence (Losses)**: Average confidence of losing trades
- **Best Performing Range**: Which confidence level performs best
- **Confidence Correlation**: Difference between win/loss confidence

---

## 13. Strategy Benefits

### Automated Risk Management:
✅ Position sizing scales with account balance
✅ Leverage adjusts automatically to volatility
✅ Trailing stops lock in profits without manual intervention
✅ Diversification limits prevent concentration risk

### Adaptive to Market Conditions:
✅ Faster trading in volatile (opportunity-rich) markets
✅ Slower trading in choppy (low-opportunity) markets
✅ Lower leverage during uncertain periods
✅ Reduced small-cap exposure when markets are risky

### Cost Optimization:
✅ Pre-filtering reduces AI API costs by ~70%
✅ Cooldown periods prevent excessive trading fees
✅ Dynamic position sizing prevents overtrading small accounts

### AI-Powered Intelligence:
✅ Analyzes 15+ indicators across multiple timeframes
✅ Considers market sentiment (funding rate, open interest)
✅ Learns from confidence scores vs actual outcomes
✅ Provides reasoning for every decision

---

## 14. Configuration Files

### Main Configuration:
- **config/trading.php**: All strategy parameters
- **Bot Settings (Database)**: Runtime-adjustable settings
- **.env**: API keys, AI model selection, trading mode

### Key Settings:
```bash
# Trading Mode
TRADING_MODE=mock          # or 'testnet', 'live'

# AI Configuration
AI_PROVIDER=openrouter
OPENROUTER_MODEL=deepseek/deepseek-chat-v3.1

# Binance API
BINANCE_API_KEY=your_key
BINANCE_API_SECRET=your_secret
BINANCE_TESTNET=false
```

---

## 15. How to Adjust Strategy

### Via Admin Panel (`/admin`):
- Bot enabled/disabled
- Active trading pairs selection
- AI model configuration
- Initial capital tracking
- Max leverage limit
- Take profit / Stop loss percentages
- Trailing stop levels (4 levels)

### Via Config File (`config/trading.php`):
- Dynamic position sizing (on/off, risk %)
- Dynamic leverage (on/off, volatility thresholds)
- Dynamic cooldown (on/off, time periods)
- Market cap limits (normal/high volatility)
- Pre-filtering (on/off, criteria count)

### Via Environment Variables (`.env`):
- Trading mode (mock/testnet/live)
- AI provider and model
- Binance API credentials

---

## 16. Safety Features

### Pre-Trade Checks:
- Sufficient balance verification
- Existing position check
- Cooldown period enforcement
- Confidence threshold validation
- Diversification limit enforcement

### During Trade:
- Real-time price monitoring (every 1 minute)
- Liquidation distance tracking
- Trailing stop adjustments
- Stop loss / Take profit order management

### Post-Trade:
- Realized P&L calculation
- Performance metrics update
- AI decision logging with reasoning
- Position history archival

---

## 17. Example Trade Flow

**Scenario**: BTC/USDT Buy Signal

1. **Market Data Collection** (every 3 minutes)
   - Fetch OHLCV data for BTC
   - Calculate EMA, MACD, RSI, ATR, ADX
   - Store in database

2. **AI Analysis** (every 10 minutes)
   - Pre-filter: BTC passes 3/4 criteria ✅
   - Send BTC data to DeepSeek AI
   - AI returns: `buy`, confidence: 0.85, entry: $95,000, target: $98,000, stop: $93,000

3. **Pre-Trade Validation**
   - Confidence 0.85 > 0.70 ✅
   - Available cash: $1,500 ✅
   - No existing BTC position ✅
   - Cooldown expired (last trade 90 min ago) ✅
   - Large cap limit: 1/3 ✅

4. **Position Sizing**
   - Calculate volatility: ATR ratio = 0.85 → Medium volatility
   - Dynamic leverage: 3x
   - Position size: $1,500 × 2.5% = $37.50
   - Quantity: ($37.50 × 3) / $95,000 = 0.001184 BTC

5. **Order Execution**
   - Set leverage on Binance: 3x
   - Send market buy order: 0.001184 BTC
   - Order filled at: $95,100 (avg)
   - Create position record with targets

6. **Position Monitoring** (every 1 minute)
   - Update current price
   - Check trailing stops:
     - Price reaches $97,954 (+3%) → Move stop to $94,149 (-1%)
     - Price reaches $99,855 (+5%) → Move stop to $95,100 (breakeven)
     - Price reaches $102,708 (+8%) → Move stop to $98,003 (+3%)

7. **Position Exit**
   - Price hits $98,000 (take profit) ✅
   - Close position via market sell
   - Calculate realized P&L: +$2.85 (+2.5% with 3x leverage = +7.5%)
   - Update statistics, cooldown activated

---

## 18. Monitoring & Logs

### Log Files:
- **storage/logs/laravel.log**: All trading activity
- Search for: `🤖` (AI decisions), `✅` (successful trades), `⚠️` (warnings)

### Database Tables:
- **positions**: All open/closed positions with P&L
- **ai_logs**: AI decisions with reasoning
- **market_data**: Time-series indicator data
- **bot_settings**: Configuration values

### Dashboard (`/dashboard`):
- Real-time account balance
- Open positions with current P&L
- Recent closed positions
- Win rate, ROI, profit factor
- AI performance metrics
- Last AI run timestamp

---

## 19. Best Practices

### For Maximum Safety:
1. Start with **mock mode** to test strategy
2. Use **testnet mode** with fake money
3. Begin with **small position sizes** (1-2%)
4. Set **conservative leverage** (2-3x max)
5. Enable **all safety features** (trailing stops, diversification)

### For Optimal Performance:
1. Monitor **AI performance metrics** weekly
2. Adjust **confidence threshold** based on results
3. Review **closed positions** to identify patterns
4. Update **active pairs** based on market conditions
5. Keep **sufficient balance** to avoid missing opportunities

### For Cost Efficiency:
1. Enable **pre-filtering** to reduce AI costs
2. Adjust **cooldown periods** to reduce overtrading
3. Focus on **high-volume pairs** for better liquidity
4. Review **trailing stops** to avoid premature exits

---

## 20. Future Enhancements (Planned)

- [ ] Short positions (sell signals)
- [ ] Multiple AI model voting (ensemble approach)
- [ ] Bollinger Bands indicator integration
- [ ] Backtesting framework
- [ ] Custom indicator builder
- [ ] Telegram notifications
- [ ] Advanced position sizing (Kelly Criterion)
- [ ] Market regime detection (trending vs ranging)

---

## Summary

This trading strategy combines:
- **AI intelligence** for decision-making
- **Dynamic risk management** for safety
- **Volatility adaptation** for market conditions
- **Cost optimization** for profitability
- **Automated execution** for consistency

The result is a robust, adaptive trading system that can operate 24/7 with minimal manual intervention while maintaining strict risk controls.

---

**Last Updated**: October 24, 2025
**Strategy Version**: 2.0
**Bot Version**: Multi-Coin AI Trading Bot v1.0
