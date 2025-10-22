<?php

namespace App\Services;

use App\Models\MarketData;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private BinanceService $binance;

    // Default supported coins (fallback) - Top 10
    private const array DEFAULT_SUPPORTED_COINS = [
        'BTC/USDT',
        'ETH/USDT',
        'SOL/USDT',
        'BNB/USDT',
        'XRP/USDT',
        'DOGE/USDT',
        'ADA/USDT',   // Cardano
        'AVAX/USDT',  // Avalanche
        'LINK/USDT',  // Chainlink
        'DOT/USDT',   // Polkadot
    ];

    public function __construct(BinanceService $binance)
    {
        $this->binance = $binance;
    }

    /**
     * Get supported coins from BotSetting or default
     */
    public static function getSupportedCoins(): array
    {
        return \App\Models\BotSetting::get('supported_coins', self::DEFAULT_SUPPORTED_COINS);
    }

    /**
     * Collect market data for all supported coins
     */
    public function collectAllMarketData(): array
    {
        $allData = [];

        foreach (self::getSupportedCoins() as $symbol) {
            try {
                // Get 3m data
                $data3m = $this->collectMarketData($symbol, '3m');

                // Get 4h data
                $data4h = $this->collectMarketData($symbol, '4h');

                $allData[$symbol] = [
                    '3m' => $data3m,
                    '4h' => $data4h,
                ];

                Log::info("📊 Market data collected for {$symbol}");
            } catch (\Exception $e) {
                Log::error("Failed to collect market data for {$symbol}: " . $e->getMessage());
                $allData[$symbol] = null;
            }
        }

        return $allData;
    }

    /**
     * Collect market data for a single symbol and timeframe
     */
    public function collectMarketData(string $symbol, string $timeframe = '3m'): array
    {
        // Fetch OHLCV data
        $ohlcv = $this->fetchOHLCV($symbol, $timeframe, 50); // Last 50 candles for indicators

        if (empty($ohlcv)) {
            throw new \Exception("No OHLCV data for {$symbol}");
        }

        // Calculate indicators
        $indicators = $this->calculateIndicators($ohlcv);

        // Get funding rate and open interest (for futures)
        try {
            $fundingRate = $this->getFundingRate($symbol);
            $openInterest = $this->getOpenInterest($symbol);
        } catch (\Exception $e) {
            Log::warning("Could not fetch funding/OI for {$symbol}: " . $e->getMessage());
            $fundingRate = 0;
            $openInterest = 0;
        }

        // Get last 10 prices for series
        $priceSeries = array_slice(array_column($ohlcv, 4), -10); // Close prices

        // Current values
        $currentPrice = end($priceSeries);
        $marketData = [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'price' => $currentPrice,
            'ema20' => $indicators['ema20'],
            'ema50' => $indicators['ema50'],
            'macd' => $indicators['macd'],
            'rsi7' => $indicators['rsi7'],
            'rsi14' => $indicators['rsi14'],
            'atr3' => $indicators['atr3'] ?? 0,
            'atr14' => $indicators['atr14'] ?? 0,
            'volume' => $indicators['volume'],
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'price_series' => $priceSeries,
            'indicators' => [
                'ema_series' => array_slice($indicators['ema20_series'], -10),
                'macd_series' => array_slice($indicators['macd_series'], -10),
                'rsi7_series' => array_slice($indicators['rsi7_series'], -10),
                'rsi14_series' => array_slice($indicators['rsi14_series'], -10),
            ],
            'data_timestamp' => now(),
        ];

        // Store in database
        MarketData::store($symbol, $timeframe, $marketData);

        return $marketData;
    }

    /**
     * Fetch OHLCV data from Binance
     */
    private function fetchOHLCV(string $symbol, string $timeframe, int $limit = 50): array
    {
        $exchange = $this->binance->getExchange();

        return $exchange->fetchOHLCV($symbol, $timeframe, null, $limit);
    }

    /**
     * Calculate technical indicators
     */
    private function calculateIndicators(array $ohlcv): array
    {
        $closes = array_column($ohlcv, 4); // Close prices
        $highs = array_column($ohlcv, 2);  // High prices
        $lows = array_column($ohlcv, 3);   // Low prices
        $volumes = array_column($ohlcv, 5); // Volumes

        // Calculate MACD with signal line
        $macdData = $this->calculateMACDSeries($closes);
        $macdValue = end($macdData['macd']);
        $signalValue = end($macdData['signal']);

        // Calculate ADX
        $adxData = $this->calculateADX($highs, $lows, $closes, 14);

        return [
            'ema20' => $this->calculateEMA($closes, 20),
            'ema50' => $this->calculateEMA($closes, 50),
            'ema20_series' => $this->calculateEMASeries($closes, 20),
            'macd' => $macdValue,
            'macd_signal' => $signalValue,
            'macd_series' => $macdData['macd'],
            'signal_series' => $macdData['signal'],
            'rsi7' => $this->calculateRSI($closes, 7),
            'rsi14' => $this->calculateRSI($closes, 14),
            'rsi7_series' => $this->calculateRSISeries($closes, 7),
            'rsi14_series' => $this->calculateRSISeries($closes, 14),
            'atr3' => $this->calculateATR($highs, $lows, $closes, 3),
            'atr14' => $this->calculateATR($highs, $lows, $closes, 14),
            'adx' => $adxData['adx'],
            'plus_di' => $adxData['plus_di'],
            'minus_di' => $adxData['minus_di'],
            'volume' => end($volumes),
        ];
    }

    /**
     * Calculate EMA (Exponential Moving Average)
     */
    private function calculateEMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices);
        }

        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($prices, 0, $period)) / $period;

        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
        }

        return round($ema, 8);
    }

    /**
     * Calculate EMA series (last 50 values)
     */
    private function calculateEMASeries(array $prices, int $period): array
    {
        $series = [];
        $multiplier = 2 / ($period + 1);

        if (count($prices) < $period) {
            return array_fill(0, count($prices), end($prices));
        }

        $ema = array_sum(array_slice($prices, 0, $period)) / $period;
        $series[] = $ema;

        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
            $series[] = round($ema, 8);
        }

        return $series;
    }

    /**
     * Calculate MACD (Moving Average Convergence Divergence)
     */
    private function calculateMACD(array $prices): array
    {
        $ema12 = $this->calculateEMA($prices, 12);
        $ema26 = $this->calculateEMA($prices, 26);
        $macd = $ema12 - $ema26;

        return [
            'macd' => round($macd, 8),
            'ema12' => $ema12,
            'ema26' => $ema26,
        ];
    }

    /**
     * Calculate MACD series with signal line
     */
    private function calculateMACDSeries(array $prices): array
    {
        $ema12Series = $this->calculateEMASeries($prices, 12);
        $ema26Series = $this->calculateEMASeries($prices, 26);

        $macdSeries = [];
        $minLength = min(count($ema12Series), count($ema26Series));

        for ($i = 0; $i < $minLength; $i++) {
            $macdSeries[] = round($ema12Series[$i] - $ema26Series[$i], 8);
        }

        // Calculate signal line (9-period EMA of MACD)
        $signalSeries = $this->calculateEMASeries($macdSeries, 9);

        return [
            'macd' => $macdSeries,
            'signal' => $signalSeries,
        ];
    }

    /**
     * Calculate RSI (Relative Strength Index)
     */
    private function calculateRSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period + 1) {
            return 50; // Neutral
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;

        if ($avgLoss == 0) {
            return 100;
        }

        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return round($rsi, 4);
    }

    /**
     * Calculate RSI series
     */
    private function calculateRSISeries(array $prices, int $period = 14): array
    {
        $rsiSeries = [];

        for ($i = $period + 1; $i <= count($prices); $i++) {
            $subset = array_slice($prices, 0, $i);
            $rsiSeries[] = $this->calculateRSI($subset, $period);
        }

        return $rsiSeries;
    }

    /**
     * Calculate ATR (Average True Range)
     */
    private function calculateATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        if (count($highs) < $period + 1) {
            return 0;
        }

        $trueRanges = [];

        for ($i = 1; $i < count($highs); $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trueRanges[] = $tr;
        }

        $atr = array_sum(array_slice($trueRanges, -$period)) / $period;

        return round($atr, 8);
    }

    /**
     * Calculate ADX (Average Directional Index) with proper Wilder's smoothing
     * Returns ADX value and +DI, -DI
     */
    private function calculateADX(array $highs, array $lows, array $closes, int $period = 14): array
    {
        if (count($highs) < $period + 1) {
            return ['adx' => 0, 'plus_di' => 0, 'minus_di' => 0];
        }

        $plusDM = [];
        $minusDM = [];
        $tr = [];

        // Calculate raw +DM, -DM, and TR
        for ($i = 1; $i < count($highs); $i++) {
            $highDiff = $highs[$i] - $highs[$i - 1];
            $lowDiff = $lows[$i - 1] - $lows[$i];

            $plusDM[] = ($highDiff > $lowDiff && $highDiff > 0) ? $highDiff : 0;
            $minusDM[] = ($lowDiff > $highDiff && $lowDiff > 0) ? $lowDiff : 0;

            $tr[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }

        // Iterative Wilder's smoothing
        $smoothedPlusDM = 0;
        $smoothedMinusDM = 0;
        $smoothedTR = 0;
        $smoothedDX = 0;
        $plusDI = 0;
        $minusDI = 0;

        // Initial SMA for first 14 periods
        if (count($plusDM) >= $period) {
            $smoothedPlusDM = array_sum(array_slice($plusDM, 0, $period)) / $period;
            $smoothedMinusDM = array_sum(array_slice($minusDM, 0, $period)) / $period;
            $smoothedTR = array_sum(array_slice($tr, 0, $period)) / $period;

            // Calculate initial DI and DX
            $plusDI = $smoothedTR > 0 ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
            $minusDI = $smoothedTR > 0 ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;
            $diSum = $plusDI + $minusDI;
            $dx = $diSum > 0 ? (abs($plusDI - $minusDI) / $diSum) * 100 : 0;
            $smoothedDX = $dx; // Initial ADX = first DX
        }

        // Continue with Wilder's smoothing for remaining bars
        $dmStart = min($period, count($plusDM));
        for ($i = $dmStart; $i < count($plusDM); $i++) {
            // Wilder's smoothing: (prev * (n-1) + current) / n
            $smoothedPlusDM = ($smoothedPlusDM * ($period - 1) + $plusDM[$i]) / $period;
            $smoothedMinusDM = ($smoothedMinusDM * ($period - 1) + $minusDM[$i]) / $period;
            $smoothedTR = ($smoothedTR * ($period - 1) + $tr[$i]) / $period;

            // Calculate DI
            $plusDI = $smoothedTR > 0 ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
            $minusDI = $smoothedTR > 0 ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;

            // Calculate DX
            $diSum = $plusDI + $minusDI;
            $dx = $diSum > 0 ? (abs($plusDI - $minusDI) / $diSum) * 100 : 0;

            // Smooth ADX with same Wilder's method
            $smoothedDX = ($smoothedDX * ($period - 1) + $dx) / $period;
        }

        return [
            'adx' => round($smoothedDX, 4),
            'plus_di' => round($plusDI, 4),
            'minus_di' => round($minusDI, 4),
        ];
    }

    /**
     * Get funding rate from Binance (futures only)
     */
    private function getFundingRate(string $symbol): float
    {
        try {
            $exchange = $this->binance->getExchange();

            // Convert symbol format: BTC/USDT -> BTCUSDT
            $marketSymbol = str_replace('/', '', $symbol);

            $fundingRate = $exchange->fapiPublicGetPremiumIndex(['symbol' => $marketSymbol]);

            return (float) ($fundingRate['lastFundingRate'] ?? 0);
        } catch (\Exception $e) {
            Log::warning("Could not fetch funding rate for {$symbol}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get open interest from Binance (futures only)
     */
    private function getOpenInterest(string $symbol): float
    {
        try {
            $exchange = $this->binance->getExchange();

            $marketSymbol = str_replace('/', '', $symbol);

            $oi = $exchange->fapiPublicGetOpenInterest(['symbol' => $marketSymbol]);

            return (float) ($oi['openInterest'] ?? 0);
        } catch (\Exception $e) {
            Log::warning("Could not fetch open interest for {$symbol}: " . $e->getMessage());
            return 0;
        }
    }


    /**
     * Check if overall market volatility is too low (skip AI call if true)
     * Returns true if market is too quiet to trade
     */
    public function isMarketTooQuiet(array $allMarketData): bool
    {
        $quietCoins = 0;
        $totalCoins = 0;

        foreach ($allMarketData as $symbol => $data) {
            if (!$data || !isset($data['4h']['atr14'])) {
                continue;
            }

            $totalCoins++;
            $atr = $data['4h']['atr14'];
            $currentPrice = $data['3m']['price'];

            // ATR as percentage of price
            $atrPercent = ($atr / $currentPrice) * 100;

            // If ATR is less than 1% of price, consider it "quiet"
            if ($atrPercent < 1.0) {
                $quietCoins++;
            }
        }

        // If 70% or more coins are quiet, skip AI
        if ($totalCoins > 0 && ($quietCoins / $totalCoins) >= 0.7) {
            Log::info("🔇 Market too quiet: {$quietCoins}/{$totalCoins} coins have low volatility");
            return true;
        }

        return false;
    }

    /**
     * Get latest market data for all coins from database
     */
    public function getLatestDataAllCoins(string $timeframe = '3m'): array
    {
        $data = [];

        foreach (self::getSupportedCoins() as $symbol) {
            $latest = MarketData::getLatest($symbol, $timeframe);
            if ($latest) {
                $data[$symbol] = $latest->toArray();
            }
        }

        return $data;
    }
}
