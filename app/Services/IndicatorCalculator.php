<?php

namespace App\Services;

/**
 * Pure PHP indicator calculations (original implementation)
 * Used for comparison with TAAPI.IO results
 */
class IndicatorCalculator
{
    /**
     * Calculate all indicators from OHLCV data
     */
    public function calculateIndicators(array $ohlcv): array
    {
        $closes = array_column($ohlcv, 4); // Close prices
        $highs = array_column($ohlcv, 2);  // High prices
        $lows = array_column($ohlcv, 3);   // Low prices
        $volumes = array_column($ohlcv, 5); // Volumes

        // Calculate MACD with signal line
        $macdData = $this->calculateMACDSeries($closes);
        $macdValue = end($macdData['macd']);
        $signalValue = end($macdData['signal']);
        $macdHistogram = $macdValue - $signalValue;

        // Calculate previous MACD histogram to determine if rising
        $prevMacdValue = count($macdData['macd']) > 1 ? $macdData['macd'][count($macdData['macd']) - 2] : $macdValue;
        $prevSignalValue = count($macdData['signal']) > 1 ? $macdData['signal'][count($macdData['signal']) - 2] : $signalValue;
        $prevMacdHistogram = $prevMacdValue - $prevSignalValue;
        $macdHistogramRising = $macdHistogram > $prevMacdHistogram;

        // Calculate ADX
        $adxData = $this->calculateADX($highs, $lows, $closes, 14);

        // Calculate indicators
        $bollingerBands = $this->calculateBollingerBands($closes, 20, 2.0);
        $volumeMA = $this->calculateVolumeMA($volumes, 20);
        $stochRsi = $this->calculateStochasticRSI($closes, 14, 14);
        $supertrend = $this->calculateSupertrend($highs, $lows, $closes, 10, 3.0);

        // Volume ratio
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
    public function calculateEMA(array $prices, int $period): float
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
     * Calculate EMA series
     */
    public function calculateEMASeries(array $prices, int $period): array
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
     * Calculate MACD series with signal line
     */
    public function calculateMACDSeries(array $prices): array
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
     */
    public function calculateRSI(array $prices, int $period = 14): float
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
    public function calculateRSISeries(array $prices, int $period = 14): array
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
     */
    public function calculateATR(array $highs, array $lows, array $closes, int $period = 14): float
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

        // Initial ATR
        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;

        // Wilder's smoothing
        for ($i = $period; $i < count($trueRanges); $i++) {
            $atr = (($atr * ($period - 1)) + $trueRanges[$i]) / $period;
        }

        return round($atr, 8);
    }

    /**
     * Calculate ADX (Average Directional Index)
     */
    public function calculateADX(array $highs, array $lows, array $closes, int $period = 14): array
    {
        if (count($highs) < $period + 1) {
            return ['adx' => 0, 'plus_di' => 0, 'minus_di' => 0];
        }

        $plusDM = [];
        $minusDM = [];
        $tr = [];

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

        $smoothedPlusDM = 0;
        $smoothedMinusDM = 0;
        $smoothedTR = 0;
        $smoothedDX = 0;
        $plusDI = 0;
        $minusDI = 0;

        if (count($plusDM) >= $period) {
            $smoothedPlusDM = array_sum(array_slice($plusDM, 0, $period)) / $period;
            $smoothedMinusDM = array_sum(array_slice($minusDM, 0, $period)) / $period;
            $smoothedTR = array_sum(array_slice($tr, 0, $period)) / $period;

            $plusDI = $smoothedTR > 0 ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
            $minusDI = $smoothedTR > 0 ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;
            $diSum = $plusDI + $minusDI;
            $dx = $diSum > 0 ? (abs($plusDI - $minusDI) / $diSum) * 100 : 0;
            $smoothedDX = $dx;
        }

        $dmStart = min($period, count($plusDM));
        for ($i = $dmStart; $i < count($plusDM); $i++) {
            $smoothedPlusDM = ($smoothedPlusDM * ($period - 1) + $plusDM[$i]) / $period;
            $smoothedMinusDM = ($smoothedMinusDM * ($period - 1) + $minusDM[$i]) / $period;
            $smoothedTR = ($smoothedTR * ($period - 1) + $tr[$i]) / $period;

            $plusDI = $smoothedTR > 0 ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
            $minusDI = $smoothedTR > 0 ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;

            $diSum = $plusDI + $minusDI;
            $dx = $diSum > 0 ? (abs($plusDI - $minusDI) / $diSum) * 100 : 0;

            $smoothedDX = ($smoothedDX * ($period - 1) + $dx) / $period;
        }

        return [
            'adx' => round($smoothedDX, 4),
            'plus_di' => round($plusDI, 4),
            'minus_di' => round($minusDI, 4),
        ];
    }

    /**
     * Calculate Bollinger Bands
     */
    public function calculateBollingerBands(array $prices, int $period = 20, float $stdDevMultiplier = 2.0): array
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

        $subset = array_slice($prices, -$period);
        $sma = array_sum($subset) / $period;

        $variance = 0;
        foreach ($subset as $price) {
            $variance += pow($price - $sma, 2);
        }
        $stdDev = sqrt($variance / $period);

        $upper = $sma + ($stdDevMultiplier * $stdDev);
        $lower = $sma - ($stdDevMultiplier * $stdDev);

        $bandwidth = $upper > 0 ? (($upper - $lower) / $sma) * 100 : 0;

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
    public function calculateVolumeMA(array $volumes, int $period = 20): float
    {
        if (count($volumes) < $period) {
            return end($volumes) ?: 0;
        }

        $subset = array_slice($volumes, -$period);
        return array_sum($subset) / $period;
    }

    /**
     * Calculate Stochastic RSI
     */
    public function calculateStochasticRSI(array $prices, int $rsiPeriod = 14, int $stochPeriod = 14): array
    {
        if (count($prices) < $rsiPeriod + $stochPeriod) {
            return ['k' => 50, 'd' => 50];
        }

        $rsiValues = [];
        for ($i = $rsiPeriod + 1; $i <= count($prices); $i++) {
            $subset = array_slice($prices, 0, $i);
            $rsiValues[] = $this->calculateRSI($subset, $rsiPeriod);
        }

        if (count($rsiValues) < $stochPeriod) {
            return ['k' => 50, 'd' => 50];
        }

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

        $currentK = end($kValues);

        $d = 50;
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
     */
    public function calculateSupertrend(array $highs, array $lows, array $closes, int $period = 10, float $multiplier = 3.0): array
    {
        $length = count($closes);
        if ($length < $period) {
            return [
                'trend' => 0,
                'value' => end($closes),
                'is_bullish' => false,
                'is_bearish' => false,
            ];
        }

        $atr = $this->calculateATR($highs, $lows, $closes, $period);

        $hl2 = [];
        for ($i = 0; $i < $length; $i++) {
            $hl2[] = ($highs[$i] + $lows[$i]) / 2;
        }

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

            if ($i == 0) {
                $trend[$i] = 1;
                $supertrend[$i] = $finalLowerband[$i];
            } else {
                $prevTrend = $trend[$i - 1];

                if ($prevTrend == 1) {
                    if ($closes[$i] <= $finalLowerband[$i]) {
                        $trend[$i] = -1;
                        $supertrend[$i] = $finalUpperband[$i];
                    } else {
                        $trend[$i] = 1;
                        $supertrend[$i] = $finalLowerband[$i];
                    }
                } else {
                    if ($closes[$i] >= $finalUpperband[$i]) {
                        $trend[$i] = 1;
                        $supertrend[$i] = $finalLowerband[$i];
                    } else {
                        $trend[$i] = -1;
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
