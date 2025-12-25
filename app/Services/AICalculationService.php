<?php

namespace App\Services;

use App\Models\MarketData;
use Illuminate\Support\Facades\Log;

class AICalculationService
{
    private $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get raw OHLCV data and let AI calculate indicators
     */
    public function getAICalculatedData(string $symbol, string $timeframe = '3m'): ?array
    {
        // Get raw OHLCV data from database
        $rawData = MarketData::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderBy('data_timestamp', 'desc')
            ->limit(50) // Last 50 candles for calculations
            ->get(['price', 'ema20', 'ema50', 'volume', 'price_series', 'data_timestamp'])
            ->reverse()
            ->values();

        if ($rawData->isEmpty()) {
            return null;
        }

        // Prepare data for AI
        $ohlcvData = [];
        foreach ($rawData as $candle) {
            $priceArray = json_decode($candle->price_series, true);
            if (is_array($priceArray) && count($priceArray) >= 4) {
                $ohlcvData[] = [
                    'timestamp' => $candle->data_timestamp,
                    'open' => $priceArray[0] ?? $candle->price,
                    'high' => $priceArray[1] ?? $candle->price,
                    'low' => $priceArray[2] ?? $candle->price,
                    'close' => $priceArray[3] ?? $candle->price,
                    'volume' => $candle->volume,
                ];
            }
        }

        if (empty($ohlcvData)) {
            return null;
        }

        // Ask AI to calculate indicators
        $prompt = $this->buildCalculationPrompt($symbol, $ohlcvData);
        
        try {
            $response = $this->aiService->makeRequest($prompt);
            $calculations = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($calculations['indicators'])) {
                Log::info("✅ AI calculated indicators for {$symbol}: " . json_encode($calculations['indicators']));
                return $calculations;
            }
            
        } catch (\Exception $e) {
            Log::error("❌ AI calculation failed for {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    private function buildCalculationPrompt(string $symbol, array $ohlcvData): string
    {
        $dataJson = json_encode(array_slice($ohlcvData, -20)); // Last 20 candles
        
        return "You are a technical analysis expert. Calculate the following indicators from this OHLCV data for {$symbol}:

OHLCV Data (last 20 candles):
{$dataJson}

Calculate these indicators for the LATEST candle:
1. RSI(7) - 7-period Relative Strength Index
2. RSI(14) - 14-period Relative Strength Index  
3. MACD(12,26,9) - MACD line, Signal line, Histogram
4. EMA(20) - 20-period Exponential Moving Average
5. EMA(50) - 50-period Exponential Moving Average
6. ADX(14) - Average Directional Index with +DI and -DI
7. ATR(14) - Average True Range
8. Volume Ratio - Current volume vs 20-period average

IMPORTANT: Use proper technical analysis formulas. Return ONLY valid JSON:

{
  \"symbol\": \"{$symbol}\",
  \"indicators\": {
    \"rsi7\": 45.67,
    \"rsi14\": 52.34,
    \"macd\": 0.0123,
    \"macd_signal\": 0.0098,
    \"macd_histogram\": 0.0025,
    \"ema20\": 98500.45,
    \"ema50\": 97800.23,
    \"adx\": 25.67,
    \"plus_di\": 18.45,
    \"minus_di\": 12.34,
    \"atr14\": 1250.67,
    \"volume_ratio\": 1.23,
    \"current_price\": 98750.00
  },
  \"trend_analysis\": {
    \"4h_trend\": \"bullish|bearish|sideways\",
    \"strength\": \"strong|moderate|weak\",
    \"recommendation\": \"LONG|SHORT|HOLD\"
  }
}";
    }
}
