# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an AI-powered multi-coin cryptocurrency trading bot built with Laravel 12 and FilamentPHP v4. The system uses AI (DeepSeek/OpenRouter/OpenAI) to make trading decisions across 6 cryptocurrencies simultaneously (BTC, ETH, SOL, BNB, XRP, DOGE) on Binance Futures.

## Key Commands

### Development
```bash
# Start dev server with queue, logs, and vite (uses concurrently)
composer dev

# Or manually:
php artisan serve --port=8000
php artisan queue:listen
php artisan pail
npm run dev
```

### Testing
```bash
composer test
# or
php artisan test
```

### Database
```bash
php artisan migrate
php artisan migrate:fresh --seed
```

### AI Configuration
```bash
# Enable/disable AI trading
php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true);"

# Configure trading parameters
php artisan tinker --execute="BotSetting::set('position_size_usdt', 100);"
php artisan tinker --execute="BotSetting::set('max_leverage', 10);"
```

## Architecture

### Multi-Provider AI System

The bot supports 3 AI providers that can be switched via `.env`:

- **OpenRouter** (default): Access to 50+ models via single API
- **DeepSeek Direct**: Direct API access, lowest latency
- **OpenAI**: GPT-4 Turbo for highest quality

Switch providers by changing `AI_PROVIDER` in `.env` - no restart required.

### Service Layer Architecture

**Core Services:**
- `BinanceService`: CCXT wrapper for Binance Futures API
- `MockBinanceService`: Simulated trading for testing (TRADING_MODE=mock)
- `TradingService`: Single-coin trading logic (legacy)
- `MarketDataService`: Collects and calculates technical indicators (EMA, MACD, RSI, ATR)
- `MultiCoinAIService`: Multi-coin AI decision engine
- `AIService`: Multi-provider AI abstraction (OpenRouter/DeepSeek/OpenAI)

**Data Flow:**
1. `MarketDataService` fetches OHLCV from Binance → calculates indicators → stores in `market_data` table
2. `MultiCoinAIService` builds detailed prompt with all market data + account state
3. AI provider returns decisions per coin (buy/close_profitable/stop_loss/hold)
4. `MultiCoinTradingController` executes each decision

### Database Schema

**Key Tables:**
- `trades`: Individual order records (order_id, symbol, side, amount, leverage, status)
- `positions`: Open/closed positions with liquidation tracking, exit plans (JSON), confidence scores
- `trade_logs`: Decision logs with account state snapshots and AI reasoning
- `bot_settings`: Key-value configuration store (use_ai, position_size_usdt, max_leverage, etc.)
- `market_data`: Time-series technical indicator data (symbol, timeframe, price, ema20, macd, rsi7, funding_rate, open_interest, price_series JSON)

**Important Position Fields:**
- `exit_plan` (JSON): Contains profit_target, stop_loss, invalidation_condition
- `confidence` (decimal): AI confidence score (0-1), only trade if >0.7
- `liquidation_price`: Auto-calculated based on leverage
- `leverage`: Position leverage (1-20x)
- `sl_order_id`, `tp_order_id`, `entry_order_id`: Binance order tracking

### Multi-Coin Trading System

**Supported Pairs:** BTC/USDT, ETH/USDT, SOL/USDT, BNB/USDT, XRP/USDT, DOGE/USDT

**Workflow:**
1. `POST /api/multi-coin/execute` triggers analysis for all 6 coins
2. Collects 3-minute and 4-hour timeframe data for each coin
3. Calculates technical indicators:
   - EMA (20, 50 period)
   - MACD (12, 26, 9)
   - RSI (7, 14 period)
   - ATR (3, 14 period)
   - Funding rate & Open Interest
4. Builds comprehensive prompt similar to example format (see MULTI_COIN_USAGE.md)
5. AI returns array of decisions with reasoning
6. Executes trades independently per coin

**Safety Mechanisms:**
- Confidence threshold: 0.7 minimum
- Position size limits
- Liquidation distance monitoring
- One position per coin maximum
- Auto-fallback to HOLD on errors

### AI Prompt Structure

The system generates detailed prompts matching the reference format:

```
It has been X minutes since trading started...

ALL BTC DATA
current_price = X, current_ema20 = Y, current_macd = Z, current_rsi = W
Funding Rate: ...
Open Interest: ...

Intraday series (3-minute intervals, oldest → latest):
Mid prices: [...]
EMA indicators (20-period): [...]
MACD indicators: [...]
RSI indicators (7-Period): [...]

Longer-term context (4-hour timeframe):
20-Period EMA vs 50-Period EMA
ATR, Volume, etc.

[Repeat for ETH, SOL, BNB, XRP, DOGE]

ACCOUNT INFORMATION:
Available Cash, Total Value, Return %
Current Positions: {...}

YOUR TASK: Analyze and decide...
```

Response format expects JSON with `decisions` array and `chain_of_thought`.

## Configuration Files

### Environment Variables
- `TRADING_MODE`: mock/testnet/live (controls real money usage)
- `AI_PROVIDER`: openrouter/deepseek/openai (switch AI backend)
- `OPENROUTER_API_KEY`, `DEEPSEEK_API_KEY`, `OPENAI_API_KEY`
- `BINANCE_API_KEY`, `BINANCE_API_SECRET`
- `BINANCE_TESTNET`: true/false

### Config Files
- `config/app.php`: Contains `ai_provider` and `trading_mode` settings
- `config/openrouter.php`: OpenRouter model selection
- `config/deepseek.php`: DeepSeek direct API settings
- `config/services.php`: Binance API credentials

## API Endpoints

### Single-Coin (Legacy)
- `POST /api/trade/execute`: Auto-trade BTC/ETH
- `GET /api/trade/status`: Current positions and settings
- `POST /api/trade/buy`: Manual buy order
- `POST /api/trade/close/{id}`: Close position

### Multi-Coin (Primary)
- `POST /api/multi-coin/execute`: Execute for all 6 coins
- `GET /api/multi-coin/status`: All positions + market data

## FilamentPHP Resources

Located in `app/Filament/Resources/`:
- `TradeResource`: View trade history
- `PositionResource`: Monitor open/closed positions
- `TradeLogResource`: Decision logs and AI reasoning
- `BotSettingResource`: Configure bot parameters

Access admin panel at `/admin` after creating user:
```bash
php artisan make:filament-user
```

## Testing Workflow

1. **Set mock mode**: `TRADING_MODE=mock` in `.env`
2. **Test API**: `curl -X POST http://localhost:8000/api/multi-coin/execute`
3. **Monitor logs**: `tail -f storage/logs/laravel.log | grep "🤖"`
4. **Check database**: Query `positions`, `market_data`, `trade_logs` tables
5. **Switch to live**: Change `TRADING_MODE=live` when ready

## Important Notes

- **Position Model**: Has `toPromptFormat()` method that formats data for AI prompt
- **MarketData Model**: Stores time-series indicator data, use `getLatest()` and `getRecent()` static methods
- **BotSetting Model**: Uses static `get()`/`set()` methods for key-value storage
- **Technical Indicators**: All calculated in `MarketDataService` (pure PHP, no external libs)
- **Provider Switching**: Change `AI_PROVIDER` in `.env` - takes effect immediately on next request
- **Mock Trading**: `MockBinanceService` simulates price movements and balance changes without real API calls

## Documentation

- `MULTI_COIN_USAGE.md`: Multi-coin system usage guide
- `AI_PROVIDER_GUIDE.md`: Detailed AI provider comparison and switching guide
- `QUICK_START.md`: Quick reference for common tasks
- `API_USAGE.md`: All API endpoints with examples
- `OPENROUTER_SETUP.md`: OpenRouter + DeepSeek setup details
- `TEST_AI_TRADING.md`: AI testing scenarios
- `MULTI_COIN_PLAN.md`: Implementation roadmap and architecture decisions
