<?php

namespace App\Services;

use App\Models\MarketData;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private BinanceService $binance;

    public function __construct(BinanceService $binance)
    {
        $this->binance = $binance;
    }

    /**
     * Get supported coins from BotSetting or config default
     */
    public static function getSupportedCoins(): array
    {
        $defaultCoins = config('trading.default_active_pairs', array_keys(config('trading.supported_pairs', [])));
        $coins = \App\Models\BotSetting::get('supported_coins', $defaultCoins);
        
        // Handle JSON string from database
        if (is_string($coins)) {
            $coins = json_decode($coins, true) ?? $defaultCoins;
        }
        
        return is_array($coins) ? $coins : $defaultCoins;
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
            'macd_signal' => $indicators['macd_signal'],
            'macd_histogram' => $indicators['macd_histogram'] ?? 0,
            'macd_histogram_rising' => $indicators['macd_histogram_rising'] ?? false,
            'rsi7' => $indicators['rsi7'],
            'rsi14' => $indicators['rsi14'],
            'atr3' => $indicators['atr3'] ?? 0,
            'atr14' => $indicators['atr14'] ?? 0,
            'adx' => $indicators['adx'],
            'plus_di' => $indicators['plus_di'],
            'minus_di' => $indicators['minus_di'],
            'volume' => $indicators['volume'],
            'funding_rate' => $fundingRate,
            'open_interest' => $openInterest,
            'price_series' => $priceSeries,
            // New indicators
            'bb_upper' => $indicators['bb_upper'],
            'bb_middle' => $indicators['bb_middle'],
            'bb_lower' => $indicators['bb_lower'],
            'bb_width' => $indicators['bb_width'],
            'bb_percent_b' => $indicators['bb_percent_b'],
            'volume_ma' => $indicators['volume_ma'],
            'volume_ratio' => $indicators['volume_ratio'],
            'stoch_rsi_k' => $indicators['stoch_rsi_k'],
            'stoch_rsi_d' => $indicators['stoch_rsi_d'],
            'indicators' => [
                'ema_series' => array_slice($indicators['ema20_series'], -10),
                'macd_series' => array_slice($indicators['macd_series'], -10),
                'signal_series' => array_slice($indicators['signal_series'], -10),
                'rsi7_series' => array_slice($indicators['rsi7_series'], -10),
                'rsi14_series' => array_slice($indicators['rsi14_series'], -10),
                'volume' => $indicators['volume'],
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
        $macdHistogram = $macdValue - $signalValue; // MACD Histogram = MACD - Signal
        
        // Calculate previous MACD histogram to determine if rising
        $prevMacdValue = count($macdData['macd']) > 1 ? $macdData['macd'][count($macdData['macd']) - 2] : $macdValue;
        $prevSignalValue = count($macdData['signal']) > 1 ? $macdData['signal'][count($macdData['signal']) - 2] : $signalValue;
        $prevMacdHistogram = $prevMacdValue - $prevSignalValue;
        $macdHistogramRising = $macdHistogram > $prevMacdHistogram;

        // Calculate ADX
        $adxData = $this->calculateADX($highs, $lows, $closes, 14);

        // Calculate new indicators
        $bollingerBands = $this->calculateBollingerBands($closes, 20, 2.0);
        $volumeMA = $this->calculateVolumeMA($volumes, 20);
        $stochRsi = $this->calculateStochasticRSI($closes, 14, 14);
        $supertrend = $this->calculateSupertrend($highs, $lows, $closes, 10, 3.0);

        // Volume ratio (current volume vs 20-period average)
        $currentVolume = end($volumes);
        $volumeRatio = $volumeMA > 0 ? $currentVolume / $volumeMA : 1.0;

        return [
            'ema20' => $this->calculateEMA($closes, 20),
            'ema50' => $this->calculateEMA($closes, 50),
            'ema20_series' => $this->calculateEMASeries($closes, 20),
            'macd' => $macdValue,
            'macd_signal' => $signalValue,
            'macd_histogram' => round($macdHistogram, 8),
            'macd_histogram_rising' => $macdHistogramRising,
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
            'volume' => $currentVolume,
            // New indicators
            'bb_upper' => $bollingerBands['upper'],
            'bb_middle' => $bollingerBands['middle'],
            'bb_lower' => $bollingerBands['lower'],
            'bb_width' => $bollingerBands['bandwidth'],
            'bb_percent_b' => $bollingerBands['percent_b'],
            'volume_ma' => $volumeMA,
            'volume_ratio' => round($volumeRatio, 4),
            'stoch_rsi_k' => $stochRsi['k'],
            'stoch_rsi_d' => $stochRsi['d'],
            'supertrend_trend' => $supertrend['trend'],
            'supertrend_value' => $supertrend['value'],
            'supertrend_is_bullish' => $supertrend['is_bullish'],
            'supertrend_is_bearish' => $supertrend['is_bearish'],
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
     * Calculate RSI (Relative Strength Index) with Wilder's Smoothing
     * Standard RSI uses Wilder's smoothing, not simple moving average
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

        // Initial average (first period) - simple average
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        // Wilder's smoothing for remaining periods
        // Formula: AvgGain = (PrevAvgGain * (n-1) + CurrentGain) / n
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
        }

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
     * Calculate ATR (Average True Range) with Wilder's Smoothing
     * Standard ATR uses Wilder's smoothing, not simple moving average
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

        // Initial ATR (first period) - simple average
        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;

        // Wilder's smoothing for remaining periods
        // Formula: ATR = (PrevATR * (n-1) + CurrentTR) / n
        for ($i = $period; $i < count($trueRanges); $i++) {
            $atr = (($atr * ($period - 1)) + $trueRanges[$i]) / $period;
        }

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

    /**
     * Calculate Bollinger Bands
     * Returns middle band (SMA), upper band, lower band, bandwidth, and %B
     */
    private function calculateBollingerBands(array $prices, int $period = 20, float $stdDevMultiplier = 2.0): array
    {
        if (count($prices) < $period) {
            $currentPrice = end($prices);
            return [
                'middle' => $currentPrice,
                'upper' => $currentPrice,
                'lower' => $currentPrice,
                'bandwidth' => 0,
                'percent_b' => 0.5,
            ];
        }

        // Calculate SMA (middle band)
        $subset = array_slice($prices, -$period);
        $sma = array_sum($subset) / $period;

        // Calculate standard deviation
        $variance = 0;
        foreach ($subset as $price) {
            $variance += pow($price - $sma, 2);
        }
        $stdDev = sqrt($variance / $period);

        $upper = $sma + ($stdDevMultiplier * $stdDev);
        $lower = $sma - ($stdDevMultiplier * $stdDev);

        // Bollinger Band Width (measures volatility)
        $bandwidth = $upper > 0 ? (($upper - $lower) / $sma) * 100 : 0;

        // %B (where is price relative to bands? 0 = lower, 0.5 = middle, 1 = upper)
        $currentPrice = end($prices);
        $percentB = ($upper - $lower) > 0 ? (($currentPrice - $lower) / ($upper - $lower)) : 0.5;

        return [
            'middle' => round($sma, 8),
            'upper' => round($upper, 8),
            'lower' => round($lower, 8),
            'bandwidth' => round($bandwidth, 4),
            'percent_b' => round($percentB, 4),
        ];
    }

    /**
     * Calculate Volume Moving Average
     */
    private function calculateVolumeMA(array $volumes, int $period = 20): float
    {
        if (count($volumes) < $period) {
            return end($volumes) ?: 0;
        }

        $subset = array_slice($volumes, -$period);
        return array_sum($subset) / $period;
    }

    /**
     * Calculate Stochastic RSI
     * More sensitive than regular RSI - better for detecting momentum shifts
     * %D is 3-period SMA of %K (proper calculation)
     */
    private function calculateStochasticRSI(array $prices, int $rsiPeriod = 14, int $stochPeriod = 14): array
    {
        if (count($prices) < $rsiPeriod + $stochPeriod) {
            return [
                'k' => 50,
                'd' => 50,
            ];
        }

        // Calculate RSI series
        $rsiValues = [];
        for ($i = $rsiPeriod + 1; $i <= count($prices); $i++) {
            $subset = array_slice($prices, 0, $i);
            $rsiValues[] = $this->calculateRSI($subset, $rsiPeriod);
        }

        if (count($rsiValues) < $stochPeriod) {
            return [
                'k' => 50,
                'd' => 50,
            ];
        }

        // Calculate %K for each period in the stochastic window
        $kValues = [];
        for ($i = $stochPeriod - 1; $i < count($rsiValues); $i++) {
            $window = array_slice($rsiValues, $i - $stochPeriod + 1, $stochPeriod);
            $currentRSI = end($window);
            $lowestRSI = min($window);
            $highestRSI = max($window);

            $k = ($highestRSI - $lowestRSI) > 0
                ? (($currentRSI - $lowestRSI) / ($highestRSI - $lowestRSI)) * 100
                : 50;
            $kValues[] = $k;
        }

        // Get current %K (last value)
        $currentK = end($kValues);

        // Calculate %D as 3-period SMA of %K
        $d = 50; // Default
        if (count($kValues) >= 3) {
            $last3K = array_slice($kValues, -3);
            $d = array_sum($last3K) / 3;
        } elseif (count($kValues) > 0) {
            $d = array_sum($kValues) / count($kValues);
        }

        return [
            'k' => round($currentK, 4),
            'd' => round($d, 4),
        ];
    }

    /**
     * Calculate Supertrend Indicator
     * Returns trend direction and value
     *
     * @param array $highs High prices
     * @param array $lows Low prices
     * @param array $closes Close prices
     * @param int $period ATR period (default 10)
     * @param float $multiplier ATR multiplier (default 3.0)
     * @return array ['trend' => 1/-1, 'value' => float, 'is_bullish' => bool]
     */
    private function calculateSupertrend(array $highs, array $lows, array $closes, int $period = 10, float $multiplier = 3.0): array
    {
        $length = count($closes);
        if ($length < $period) {
            return [
                'trend' => 0,
                'value' => end($closes),
                'is_bullish' => false,
            ];
        }

        // Calculate ATR
        $atr = $this->calculateATR($highs, $lows, $closes, $period);

        // Calculate HL2 (average of high and low)
        $hl2 = [];
        for ($i = 0; $i < $length; $i++) {
            $hl2[] = ($highs[$i] + $lows[$i]) / 2;
        }

        // Calculate basic upper and lower bands
        $basicUpperband = [];
        $basicLowerband = [];
        $finalUpperband = [];
        $finalLowerband = [];
        $supertrend = [];
        $trend = [];

        for ($i = 0; $i < $length; $i++) {
            $currentAtr = $this->calculateATR(
                array_slice($highs, max(0, $i - $period + 1), $period),
                array_slice($lows, max(0, $i - $period + 1), $period),
                array_slice($closes, max(0, $i - $period + 1), $period),
                min($i + 1, $period)
            );

            $basicUpperband[$i] = $hl2[$i] + ($multiplier * $currentAtr);
            $basicLowerband[$i] = $hl2[$i] - ($multiplier * $currentAtr);

            // Calculate final bands
            if ($i == 0) {
                $finalUpperband[$i] = $basicUpperband[$i];
                $finalLowerband[$i] = $basicLowerband[$i];
            } else {
                $finalUpperband[$i] = ($basicUpperband[$i] < $finalUpperband[$i - 1] || $closes[$i - 1] > $finalUpperband[$i - 1])
                    ? $basicUpperband[$i]
                    : $finalUpperband[$i - 1];

                $finalLowerband[$i] = ($basicLowerband[$i] > $finalLowerband[$i - 1] || $closes[$i - 1] < $finalLowerband[$i - 1])
                    ? $basicLowerband[$i]
                    : $finalLowerband[$i - 1];
            }

            // Determine trend
            if ($i == 0) {
                $trend[$i] = 1; // Start bullish
                $supertrend[$i] = $finalLowerband[$i];
            } else {
                $prevTrend = $trend[$i - 1];

                if ($prevTrend == 1) {
                    // Was bullish
                    if ($closes[$i] <= $finalLowerband[$i]) {
                        $trend[$i] = -1; // Bearish
                        $supertrend[$i] = $finalUpperband[$i];
                    } else {
                        $trend[$i] = 1; // Still bullish
                        $supertrend[$i] = $finalLowerband[$i];
                    }
                } else {
                    // Was bearish
                    if ($closes[$i] >= $finalUpperband[$i]) {
                        $trend[$i] = 1; // Bullish
                        $supertrend[$i] = $finalLowerband[$i];
                    } else {
                        $trend[$i] = -1; // Still bearish
                        $supertrend[$i] = $finalUpperband[$i];
                    }
                }
            }
        }

        $currentTrend = end($trend);
        $currentSupertrendValue = end($supertrend);

        return [
            'trend' => $currentTrend,
            'value' => round($currentSupertrendValue, 8),
            'is_bullish' => $currentTrend == 1,
            'is_bearish' => $currentTrend == -1,
        ];
    }
}
