# Binance AI Trading Bot - Qwen Code Guidelines

## Project Overview

This is an AI-powered, multi-coin cryptocurrency trading bot built with Laravel 12 and FilamentPHP 4. The system uses configurable AI providers (OpenRouter, DeepSeek, or OpenAI) to make trading decisions across multiple cryptocurrencies simultaneously on Binance Futures.

### Key Technologies
- **Backend**: Laravel 12 (PHP 8.2+)
- **Admin Panel**: FilamentPHP 4
- **Frontend**: Vite, Tailwind CSS, Axios
- **Database**: MySQL/PostgreSQL (configurable)
- **AI Providers**: OpenRouter, DeepSeek, OpenAI
- **Trading**: Binance API via CCXT library
- **Queue System**: Laravel Queues (with Redis support)

## Project Architecture

### Core Components
- `app/Models/`: Database models (Position, Trade, AiLog, BotSetting, etc.)
- `app/Services/`: Business logic services (TradingService, AIService, BinanceService, etc.)
- `app/Http/Controllers/Api/`: API endpoints for trading operations
- `app/Filament/Resources/`: Admin panel resources for data management
- `config/trading.php`: Trading-specific configurations
- `routes/api.php`: API route definitions

### Key Features
- Multi-coin trading (BTC, ETH, SOL, BNB, XRP, DOGE, and others)
- AI-powered trading decisions
- Real-time dashboard and monitoring
- Configurable risk management (stop-loss, take-profit)
- Support for mock, testnet, and live trading modes
- Filament admin panel for monitoring and management

## Development Conventions

### Coding Style
- Follow PSR-12 coding standards
- Use Laravel conventions for naming and structure
- Model attributes should be properly cast (especially decimals and booleans)
- Controllers should return structured JSON responses for API endpoints
- Services should be focused and follow the single responsibility principle

### File Structure
- Model files in `app/Models/`
- Service files in `app/Services/`
- API Controllers in `app/Http/Controllers/Api/`
- Filament Resources in `app/Filament/Resources/`
- Database migrations in `database/migrations/`
- Configuration files in `config/`
- Tests in `tests/`

### Environment Variables
- All sensitive information (API keys, database credentials) must be stored in `.env`
- Key environment variables include:
  - `TRADING_MODE` (mock, testnet, live)
  - `AI_PROVIDER` (openrouter, deepseek, openai)
  - `BINANCE_API_KEY`, `BINANCE_API_SECRET`
  - `INITIAL_CAPITAL`
  - API keys for chosen AI provider

## API Endpoints

### Trading Endpoints
- `POST /api/trade/execute` - Execute a single auto-trade cycle
- `GET /api/trade/status` - Get current trading status
- `GET /api/trade/history` - Get trade history
- `GET /api/trade/logs` - Get trade logs
- `POST /api/trade/buy` - Manually buy a position
- `POST /api/trade/close/{positionId}` - Close a specific position

### Multi-Coin Trading Endpoints
- `POST /api/multi-coin/execute` - Execute trading cycle for all coins
- `GET /api/multi-coin/status` - Get status for all coins

### Dashboard Endpoints
- `GET /api/dashboard/balance` - Get current balance
- `GET /api/dashboard/purchases` - Get purchase history
- `GET /api/dashboard/sales` - Get sales history
- `GET /api/dashboard/all-data` - Get all dashboard data
- `GET /api/dashboard/status` - Get dashboard status

## Building and Running

### Installation
```bash
# Prerequisites
- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL or PostgreSQL database
- Binance API Key and Secret
- AI Provider API Key

# Clone and install
git clone <repository-url>
cd <repository-name>
composer install
npm install
npm run dev

# Set up environment
cp .env.example .env
php artisan key:generate

# Configure .env file with your settings

# Run migrations
php artisan migrate

# Start the server
php artisan serve
```

### Starting the Bot
1. Enable AI in database:
   ```bash
   php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true); echo 'AI enabled';"
   ```

2. Trigger trading cycle:
   ```bash
   curl -X POST http://localhost:8000/api/multi-coin/execute
   ```

### Development Commands
- `npm run dev` - Start development server with hot reload
- `npm run build` - Build production assets
- `composer setup` - Setup project (install deps, migrate, build assets)
- `composer dev` - Start development environment (server, queue, logs, vite)
- `composer test` - Run tests

### Admin Panel
- Dashboard: `http://127.0.0.1:8000/trade-dashboard`
- Filament Admin: `http://127.0.0.1:8000/admin`
- Create admin user: `php artisan make:filament-user`

## Risk Management

### Built-in Safeguards
- Sleep mode (23:00-04:00 UTC) - limits new trades during low liquidity
- Daily max drawdown protection (stops trading if daily loss > 8%)
- Cluster loss cooldown (pauses trading after 3 consecutive losses)
- Dynamic position sizing based on account balance
- Leverage limits based on market conditions

### Trading Modes
- `mock`: Virtual trading with simulated prices (default)
- `testnet`: Uses Binance Testnet API with test funds
- `live`: Uses real funds - use with extreme caution

## Testing
- Use Laravel's testing framework (Pest)
- Tests located in `tests/` directory
- Run with `composer test` or `php artisan test`

## Important Notes
- This project is under active development - use at your own risk
- Never use with real money without thorough testing in mock/testnet modes
- The system is designed for educational and experimental purposes
- Always backup your database before making significant changes