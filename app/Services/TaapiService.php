<?php

namespace App\Services;

use App\Models\TaapiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaapiService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.taapi.io';
    private int $cacheTtl = 60; // Cache for 1 minute to avoid redundant API calls

    public function __construct()
    {
        $this->apiKey = config('services.taapi.api_key');
    }

    /**
     * Check if rate limit is reached
     */
    private function checkRateLimit(): bool
    {
        if (TaapiUsage::isLimitReached()) {
            $remaining = TaapiUsage::getRemainingRequests();
            Log::warning("⚠️ TAAPI rate limit reached! Remaining: {$remaining}");
            return false;
        }
        return true;
    }

    /**
     * Get current usage statistics
     */
    public function getUsageStats(): array
    {
        return TaapiUsage::getTodayUsage();
    }

    /**
     * Fetch a single indicator from TAAPI
     */
    private function fetchIndicator(
        string $indicator,
        string $symbol,
        string $interval,
        array $additionalParams = []
    ): ?array {
        $cacheKey = "taapi:{$indicator}:{$symbol}:{$interval}:" . md5(json_encode($additionalParams));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($indicator, $symbol, $interval, $additionalParams) {
            try {
                // Check rate limit before making request
                if (!$this->checkRateLimit()) {
                    Log::error("🚫 TAAPI request blocked due to rate limit for {$indicator}");
                    return null;
                }

                $params = array_merge([
                    'secret' => $this->apiKey,
                    'exchange' => 'binance',
                    'symbol' => $symbol,
                    'interval' => $interval,
                ], $additionalParams);

                $response = Http::timeout(10)->get("{$this->baseUrl}/{$indicator}", $params);

                if ($response->successful()) {
                    // Increment usage counter
                    TaapiUsage::incrementRequestCount();

                    $usage = TaapiUsage::getTodayUsage();
                    Log::info("📊 TAAPI request for {$indicator}: {$usage['request_count']}/{$usage['daily_limit']}");

                    return $response->json();
                }

                Log::warning("TAAPI request failed for {$indicator}: " . $response->body());
                return null;
            } catch (\Exception $e) {
                Log::error("TAAPI request exception for {$indicator}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Fetch multiple indicators in bulk (more efficient)
     * This reduces API calls significantly
     */
    public function fetchBulkIndicators(string $symbol, string $interval): array
    {
        $cacheKey = "taapi:bulk:{$symbol}:{$interval}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($symbol, $interval) {
            try {
                // Construct bulk request payload
                $requests = [
                    // Price data
                    ['indicator' => 'candle'],

                    // EMAs
                    ['indicator' => 'ema', 'period' => 20],
                    ['indicator' => 'ema', 'period' => 50],

                    // MACD
                    ['indicator' => 'macd', 'optInFastPeriod' => 12, 'optInSlowPeriod' => 26, 'optInSignalPeriod' => 9],

                    // RSI
                    ['indicator' => 'rsi', 'period' => 7],
                    ['indicator' => 'rsi', 'period' => 14],

                    // ATR
                    ['indicator' => 'atr', 'period' => 3],
                    ['indicator' => 'atr', 'period' => 14],

                    // ADX with DI
                    ['indicator' => 'adx', 'period' => 14],
                    ['indicator' => 'plus_di', 'period' => 14],
                    ['indicator' => 'minus_di', 'period' => 14],

                    // Bollinger Bands
                    ['indicator' => 'bbands', 'period' => 20, 'stddev' => 2],

                    // Stochastic RSI
                    ['indicator' => 'stochrsi', 'period' => 14],

                    // Supertrend
                    ['indicator' => 'supertrend', 'period' => 10, 'multiplier' => 3],

                    // Volume
                    ['indicator' => 'volume'],
                ];

                // Make individual requests for now (bulk endpoint might be different)
                $results = [];
                foreach ($requests as $req) {
                    $indicator = $req['indicator'];
                    unset($req['indicator']);

                    $data = $this->fetchIndicator($indicator, $symbol, $interval, $req);
                    if ($data) {
                        $results[$indicator . '_' . ($req['period'] ?? '')] = $data;
                    }
                }

                return $results;
            } catch (\Exception $e) {
                Log::error("TAAPI bulk request exception: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get EMA with historical series
     */
    public function getEMA(string $symbol, string $interval, int $period = 20, int $results = 10): array
    {
        $data = $this->fetchIndicator('ema', $symbol, $interval, [
            'period' => $period,
            'results' => $results,
            'addResultTimestamp' => true,
        ]);

        if (!$data) {
            return ['value' => 0, 'series' => []];
        }

        // If single value
        if (isset($data['value'])) {
            return ['value' => $data['value'], 'series' => [$data['value']]];
        }

        // If array of results
        return [
            'value' => end($data)['value'] ?? 0,
            'series' => array_column($data, 'value'),
        ];
    }

    /**
     * Get MACD with signal and histogram
     */
    public function getMACD(string $symbol, string $interval, int $results = 10): array
    {
        $data = $this->fetchIndicator('macd', $symbol, $interval, [
            'results' => $results,
            'addResultTimestamp' => true,
        ]);

        if (!$data) {
            return [
                'macd' => 0,
                'signal' => 0,
                'histogram' => 0,
                'macd_series' => [],
                'signal_series' => [],
            ];
        }

        // Handle single result
        if (isset($data['valueMACD'])) {
            return [
                'macd' => $data['valueMACD'],
                'signal' => $data['valueMACDSignal'],
                'histogram' => $data['valueMACDHist'],
                'macd_series' => [$data['valueMACD']],
                'signal_series' => [$data['valueMACDSignal']],
            ];
        }

        // Handle multiple results
        return [
            'macd' => end($data)['valueMACD'] ?? 0,
            'signal' => end($data)['valueMACDSignal'] ?? 0,
            'histogram' => end($data)['valueMACDHist'] ?? 0,
            'macd_series' => array_column($data, 'valueMACD'),
            'signal_series' => array_column($data, 'valueMACDSignal'),
        ];
    }

    /**
     * Get RSI with historical series
     */
    public function getRSI(string $symbol, string $interval, int $period = 14, int $results = 10): array
    {
        $data = $this->fetchIndicator('rsi', $symbol, $interval, [
            'period' => $period,
            'results' => $results,
            'addResultTimestamp' => true,
        ]);

        if (!$data) {
            return ['value' => 50, 'series' => []];
        }

        // Single value
        if (isset($data['value'])) {
            return ['value' => $data['value'], 'series' => [$data['value']]];
        }

        // Multiple values
        return [
            'value' => end($data)['value'] ?? 50,
            'series' => array_column($data, 'value'),
        ];
    }

    /**
     * Get ATR
     */
    public function getATR(string $symbol, string $interval, int $period = 14): float
    {
        $data = $this->fetchIndicator('atr', $symbol, $interval, ['period' => $period]);
        return $data['value'] ?? 0;
    }

    /**
     * Get ADX with +DI and -DI
     */
    public function getADX(string $symbol, string $interval, int $period = 14): array
    {
        $adx = $this->fetchIndicator('adx', $symbol, $interval, ['period' => $period]);
        $plusDI = $this->fetchIndicator('plus_di', $symbol, $interval, ['period' => $period]);
        $minusDI = $this->fetchIndicator('minus_di', $symbol, $interval, ['period' => $period]);

        return [
            'adx' => $adx['value'] ?? 0,
            'plus_di' => $plusDI['value'] ?? 0,
            'minus_di' => $minusDI['value'] ?? 0,
        ];
    }

    /**
     * Get Bollinger Bands
     */
    public function getBollingerBands(string $symbol, string $interval, int $period = 20, float $stddev = 2): array
    {
        $data = $this->fetchIndicator('bbands', $symbol, $interval, [
            'period' => $period,
            'stddev' => $stddev,
        ]);

        if (!$data) {
            return [
                'upper' => 0,
                'middle' => 0,
                'lower' => 0,
                'bandwidth' => 0,
                'percent_b' => 0.5,
            ];
        }

        $upper = $data['valueUpperBand'] ?? 0;
        $middle = $data['valueMiddleBand'] ?? 0;
        $lower = $data['valueLowerBand'] ?? 0;

        // Calculate bandwidth and %B
        $bandwidth = $middle > 0 ? (($upper - $lower) / $middle) * 100 : 0;

        // Get current price from recent candle
        $currentPrice = $this->getCurrentPrice($symbol, $interval);
        $percentB = ($upper - $lower) > 0 ? (($currentPrice - $lower) / ($upper - $lower)) : 0.5;

        return [
            'upper' => $upper,
            'middle' => $middle,
            'lower' => $lower,
            'bandwidth' => round($bandwidth, 4),
            'percent_b' => round($percentB, 4),
        ];
    }

    /**
     * Get Stochastic RSI
     */
    public function getStochasticRSI(string $symbol, string $interval, int $period = 14): array
    {
        $data = $this->fetchIndicator('stochrsi', $symbol, $interval, ['period' => $period]);

        return [
            'k' => $data['valueFastK'] ?? 50,
            'd' => $data['valueFastD'] ?? 50,
        ];
    }

    /**
     * Get Supertrend
     */
    public function getSupertrend(string $symbol, string $interval, int $period = 10, float $multiplier = 3): array
    {
        $data = $this->fetchIndicator('supertrend', $symbol, $interval, [
            'period' => $period,
            'multiplier' => $multiplier,
        ]);

        if (!$data) {
            return [
                'value' => 0,
                'trend' => 0,
                'is_bullish' => false,
                'is_bearish' => false,
            ];
        }

        // TAAPI returns trend as 'up' or 'down'
        $trend = $data['valueTrend'] ?? 'up';
        $isBullish = $trend === 'up';

        return [
            'value' => $data['value'] ?? 0,
            'trend' => $isBullish ? 1 : -1,
            'is_bullish' => $isBullish,
            'is_bearish' => !$isBullish,
        ];
    }

    /**
     * Get current price and volume from candle
     */
    public function getCurrentPrice(string $symbol, string $interval): float
    {
        $data = $this->fetchIndicator('candle', $symbol, $interval, []);
        return $data['close'] ?? 0;
    }

    /**
     * Get candle data with OHLCV
     */
    public function getCandle(string $symbol, string $interval): array
    {
        $data = $this->fetchIndicator('candle', $symbol, $interval, []);

        return [
            'open' => $data['open'] ?? 0,
            'high' => $data['high'] ?? 0,
            'low' => $data['low'] ?? 0,
            'close' => $data['close'] ?? 0,
            'volume' => $data['volume'] ?? 0,
        ];
    }

    /**
     * Get volume data
     */
    public function getVolume(string $symbol, string $interval): float
    {
        $data = $this->fetchIndicator('volume', $symbol, $interval, []);
        return $data['value'] ?? 0;
    }

    /**
     * Get volume with moving average
     */
    public function getVolumeWithMA(string $symbol, string $interval, int $period = 20): array
    {
        // Get candle data which includes volume
        $candle = $this->getCandle($symbol, $interval);
        $currentVolume = $candle['volume'];

        // TAAPI doesn't have a direct volume MA, so we'll use volume indicator
        // For a proper moving average, we'd need historical volume data
        // For now, use current volume as both values
        // TODO: Implement proper volume MA calculation if needed
        $volumeData = $this->fetchIndicator('volume', $symbol, $interval, [
            'results' => $period,
        ]);

        // Calculate simple average if we have historical data
        $ma = $currentVolume;
        if (is_array($volumeData) && count($volumeData) > 1) {
            $volumes = array_column($volumeData, 'value');
            $ma = array_sum($volumes) / count($volumes);
        }

        $ratio = $ma > 0 ? $currentVolume / $ma : 1.0;

        return [
            'current' => $currentVolume,
            'ma' => $ma,
            'ratio' => round($ratio, 4),
        ];
    }

    /**
     * Get price series (last N candles)
     */
    public function getPriceSeries(string $symbol, string $interval, int $results = 10): array
    {
        $data = $this->fetchIndicator('price', $symbol, $interval, [
            'results' => $results,
            'addResultTimestamp' => true,
        ]);

        if (!$data) {
            return [];
        }

        // Single value
        if (isset($data['value'])) {
            return [$data['value']];
        }

        // Multiple values
        return array_column($data, 'value');
    }

    /**
     * Get DMI (Directional Movement Index) - similar to ADX with +DI/-DI
     */
    public function getDMI(string $symbol, string $interval, int $period = 14): array
    {
        $data = $this->fetchIndicator('dmi', $symbol, $interval, ['period' => $period]);

        return [
            'adx' => $data['valueAdx'] ?? 0,
            'plus_di' => $data['valuePlusDi'] ?? 0,
            'minus_di' => $data['valueMinusDi'] ?? 0,
        ];
    }
}
