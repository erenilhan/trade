# Binance AI Trading Bot

**⚠️ ATTENTION: THIS PROJECT IS UNDER ACTIVE DEVELOPMENT!**

This is an open-source project and is still in the development and testing phase. **Using it with real money in live mode is strongly discouraged.** Please conduct comprehensive tests in `mock` or `testnet` modes first. During development, there may be bugs, unexpected behavior, or issues that could lead to financial loss. **Use at your own risk.**

---

This is an AI-powered, multi-coin cryptocurrency trading bot built with Laravel and FilamentPHP. The system uses configurable AI providers (OpenRouter, DeepSeek, or OpenAI) to make trading decisions across multiple cryptocurrencies simultaneously on Binance Futures.

## ✨ Features

- **Multi-Coin Trading:** Simultaneously manage trades for multiple cryptocurrencies.
- **AI-Powered Decisions:** Utilizes configurable AI providers for intelligent trading decisions.
- **Real-time Monitoring:** A dashboard for live tracking of account balance, positions, and trade history.
- **Filament Admin Panel:** Easily manage bot settings, positions, trades, and AI logs.
- **Configurable Trading Modes:** Supports `mock`, `testnet`, and `live` trading modes.
- **Automated Risk Management:** Includes take-profit and stop-loss mechanisms.

## 🚀 Quick Start

### 1. Choose Your AI Provider (.env)

The project supports 3 different AI providers. Specify which one you want to use in your `.env` file:

```env
# Option 1: OpenRouter (RECOMMENDED - Flexible and low-cost)
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-or-v1-your-key

# Option 2: DeepSeek (FASTEST - Direct and very low-cost)
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-key

# Option 3: OpenAI (HIGHEST QUALITY - Powerful but more expensive)
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
```

### 2. Enable AI

Enable the bot to use AI with the following command:

```bash
php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true); echo 'AI enabled';"
```

### 3. Start a Trading Cycle!

Initiate a trading cycle with the following command:

```bash
curl -X POST http://localhost:8000/api/multi-coin/execute
```

## 🛠️ Installation

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL or PostgreSQL database
- Binance API Key and Secret (for `testnet` or `live` mode)
- AI Provider API Key (OpenRouter, DeepSeek, or OpenAI)

### Installation Steps

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/your-repo-name.git
    cd your-repo-name
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Install Node.js dependencies:**
    ```bash
    npm install
    npm run dev
    ```

4.  **Set up your environment variables:**
    Copy the `.env.example` file to a new file named `.env` and update the values within it.
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    **Important `.env` variables to set:**
    - `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
    - `BINANCE_API_KEY`, `BINANCE_API_SECRET`
    - `TRADING_MODE` (e.g., `mock`, `testnet`, `live`)
    - `AI_PROVIDER` (e.g., `openrouter`, `deepseek`, `openai`)
    - `OPENROUTER_API_KEY`, `DEEPSEEK_API_KEY`, `OPENAI_API_KEY` (depending on your chosen AI provider)
    - `INITIAL_CAPITAL` (Your starting capital for return calculations, e.g., `1000`)

5.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

6.  **Start the development server:**
    ```bash
    php artisan serve
    ```

7.  **Access the interface:**
    - **Dashboard:** `http://127.0.0.1:8000/trade-dashboard`
    - **Filament Admin Panel:** `http://127.0.0.1:8000/admin` (To create a user: `php artisan make:filament-user`)

## ⚙️ Usage

### API Endpoints

- `POST /api/multi-coin/execute`: Initiates a trading cycle for all coins.
- `GET /api/multi-coin/status`: Returns the current status of all positions and market data.

### Running the Bot

To manually trigger a trading cycle:
```bash
curl -X POST http://localhost:8000/api/multi-coin/execute
```

You can add this command to a cron job or a scheduled task for automatic execution.

### Trading Modes

- **`mock` (Default):** Does not connect to a real exchange. It tests with virtual money and simulates price fluctuations.
- **`testnet`:** Uses the Binance Testnet API. It allows you to experience real exchange behavior with test money.
- **`live` (BE CAREFUL!):** Uses real money. Use only after conducting all tests and understanding the risks.

## 📊 Monitoring

- **Dashboard:** View real-time balance, recent trades, and P&L (profit/loss) at `/trade-dashboard`.
- **Filament Admin Panel:** Access detailed logs, manage bot settings, and review positions/trades via the `/admin` interface.
- **Log Files:** You can monitor all bot activities in real-time by tailing the log file:
  ```bash
  tail -f storage/logs/laravel.log | grep "🤖"
  ```

## 🤝 Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## 📄 License

This project is open-source and licensed under the MIT License.

## 🔒 Security

- **API Keys:** Never commit your API keys or secrets directly to the repository. Always use environment variables like the `.env` file.
- **Trading Mode:** Always start with `mock` or `testnet` mode to understand the bot's behavior before using `live` mode.

---

**Note:** This bot is for educational and experimental purposes. Cryptocurrency trading involves significant risks, and you could lose money. Use at your own risk. The system is currently under development and testing - proceed with caution.