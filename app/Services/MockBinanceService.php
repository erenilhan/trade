<?php

namespace App\Services;

use App\Models\BotSetting;

/**
 * Mock Binance Service for testing without real API calls
 * Simulates trading with virtual money
 */
class MockBinanceService
{
    private float $virtualBalance = 10000; // Starting with 10k USDT
    private array $virtualPositions = [];
    private array $priceHistory = [
        'BTC/USDT' => 45000,
        'ETH/USDT' => 2500,
        'SOL/USDT' => 100,
    ];

    public function __construct()
    {
        $this->virtualBalance = BotSetting::get('initial_capital', 10000);
    }

    public function fetchBalance()
    {
        return [
            'USDT' => [
                'free' => $this->virtualBalance,
                'total' => $this->virtualBalance + $this->calculateTotalPositionValue(),
            ],
        ];
    }

    public function fetchPositions(array $symbols = null)
    {
        return $this->virtualPositions;
    }

    public function fetchTicker(string $symbol)
    {
        // Simulate price fluctuation ±1%
        $basePrice = $this->priceHistory[$symbol] ?? 45000;
        $fluctuation = $basePrice * (mt_rand(-100, 100) / 10000); // ±1%
        $currentPrice = $basePrice + $fluctuation;

        $this->priceHistory[$symbol] = $currentPrice;

        return [
            'symbol' => $symbol,
            'last' => $currentPrice,
            'bid' => $currentPrice * 0.9999,
            'ask' => $currentPrice * 1.0001,
            'high' => $currentPrice * 1.02,
            'low' => $currentPrice * 0.98,
        ];
    }

    public function createMarketBuy(string $symbol, float $amount, array $params = [])
    {
        $ticker = $this->fetchTicker($symbol);
        $price = $ticker['last'];
        $cost = $amount * $price;

        // Check if enough balance
        if ($cost > $this->virtualBalance) {
            throw new \Exception("Insufficient balance. Required: {$cost}, Available: {$this->virtualBalance}");
        }

        // Deduct balance
        $this->virtualBalance -= $cost;

        $orderId = 'MOCK_' . time() . '_' . mt_rand(1000, 9999);

        return [
            'id' => $orderId,
            'symbol' => $symbol,
            'type' => 'market',
            'side' => 'buy',
            'price' => $price,
            'amount' => $amount,
            'cost' => $cost,
            'filled' => $amount,
            'status' => 'closed',
            'timestamp' => now()->timestamp * 1000,
        ];
    }

    public function createMarketSell(string $symbol, float $amount, array $params = [])
    {
        $ticker = $this->fetchTicker($symbol);
        $price = $ticker['last'];
        $cost = $amount * $price;

        // Add balance back
        $this->virtualBalance += $cost;

        $orderId = 'MOCK_' . time() . '_' . mt_rand(1000, 9999);

        return [
            'id' => $orderId,
            'symbol' => $symbol,
            'type' => 'market',
            'side' => 'sell',
            'price' => $price,
            'amount' => $amount,
            'cost' => $cost,
            'filled' => $amount,
            'status' => 'closed',
            'timestamp' => now()->timestamp * 1000,
        ];
    }

    public function setLeverage(int $leverage, string $symbol)
    {
        // Mock: Just log it
        \Log::info("Mock: Set leverage {$leverage}x for {$symbol}");
        return ['leverage' => $leverage];
    }

    public function createStopLoss(string $symbol, float $amount, float $stopPrice, string $side = 'sell')
    {
        return [
            'id' => 'MOCK_SL_' . time(),
            'symbol' => $symbol,
            'type' => 'STOP_MARKET',
            'side' => $side,
            'amount' => $amount,
            'stopPrice' => $stopPrice,
            'status' => 'open',
        ];
    }

    public function createTakeProfit(string $symbol, float $amount, float $profitPrice, string $side = 'sell')
    {
        return [
            'id' => 'MOCK_TP_' . time(),
            'symbol' => $symbol,
            'type' => 'TAKE_PROFIT_MARKET',
            'side' => $side,
            'amount' => $amount,
            'stopPrice' => $profitPrice,
            'status' => 'open',
        ];
    }

    private function calculateTotalPositionValue(): float
    {
        // Mock: Would calculate from virtual positions
        return 0;
    }

    public function getVirtualBalance(): float
    {
        return $this->virtualBalance;
    }
}
