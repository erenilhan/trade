<?php

namespace App\Services;

use ccxt\binance;
use ccxt\ExchangeError;
use ccxt\NotSupported;

class BinanceService
{
    private binance $exchange;

    /**
     * @throws ExchangeError
     * @throws NotSupported
     */
    public function __construct()
    {
        $this->exchange = new binance([
            'apiKey' => config('services.binance.api_key'),
            'secret' => config('services.binance.secret'),
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => 'future',
            ],
        ]);

        if (config('services.binance.testnet')) {
            $this->exchange->set_sandbox_mode(true);
        }
    }

    public function fetchBalance()
    {
        return $this->exchange->fetch_balance();
    }

    public function fetchPositions(array $symbols = null)
    {
        return $this->exchange->fetch_positions($symbols);
    }

    public function fetchTicker(string $symbol)
    {
        return $this->exchange->fetch_ticker($symbol);
    }

    public function createMarketBuy(string $symbol, float $amount, array $params = [])
    {
        return $this->exchange->create_market_buy_order($symbol, $amount, $params);
    }

    public function createMarketSell(string $symbol, float $amount, array $params = [])
    {
        return $this->exchange->create_market_sell_order($symbol, $amount, $params);
    }

    public function setLeverage(int $leverage, string $symbol)
    {
        return $this->exchange->set_leverage($leverage, $symbol);
    }

    public function createStopLoss(string $symbol, float $amount, float $stopPrice, string $side = 'sell')
    {
        return $this->exchange->create_order(
            $symbol,
            'STOP_MARKET',
            $side,
            $amount,
            null,
            ['stopPrice' => $stopPrice, 'reduceOnly' => true]
        );
    }

    public function createTakeProfit(string $symbol, float $amount, float $profitPrice, string $side = 'sell')
    {
        return $this->exchange->create_order(
            $symbol,
            'TAKE_PROFIT_MARKET',
            $side,
            $amount,
            null,
            ['stopPrice' => $profitPrice, 'reduceOnly' => true]
        );
    }

    /**
     * Get exchange instance (for direct CCXT access)
     */
    public function getExchange(): binance
    {
        return $this->exchange;
    }

    /**
     * Calculate liquidation price for a position
     */
    public function calculateLiquidationPrice(float $entryPrice, int $leverage, string $side = 'long'): float
    {
        // Simplified liquidation calculation
        // Actual formula: liquidationPrice = entryPrice ± (entryPrice / leverage)

        if ($side === 'long') {
            // Long: liquidation when price drops
            return round($entryPrice * (1 - 0.9 / $leverage), 2);
        } else {
            // Short: liquidation when price rises
            return round($entryPrice * (1 + 0.9 / $leverage), 2);
        }
    }

    /**
     * Fetch multiple tickers at once
     */
    public function fetchTickers(array $symbols): array
    {
        $tickers = [];

        foreach ($symbols as $symbol) {
            try {
                $tickers[$symbol] = $this->fetchTicker($symbol);
            } catch (\Exception $e) {
                \Log::warning("Failed to fetch ticker for {$symbol}: " . $e->getMessage());
            }
        }

        return $tickers;
    }
}
