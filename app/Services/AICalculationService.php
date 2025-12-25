<?php

namespace App\Services;

use App\Models\MarketData;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class AICalculationService
{
    private $aiService;
    private $calculationModel;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
        
        // Use different model for calculations (more accurate)
        $this->calculationModel = BotSetting::get('calculation_ai_model', 'openai/gpt-4.1-nano');
    }

    /**
     * Get AI-calculated data with caching and pre-filtering
     */
    public function getOptimizedAIData(string $symbol, string $timeframe = '3m'): ?array
    {
        // Check cache first (1 hour TTL)
        $cacheKey = "ai_calc_{$symbol}_{$timeframe}";
        $cached = cache()->get($cacheKey);
        
        if ($cached) {
            Log::info("📦 Using cached AI calculation for {$symbol}");
            return $cached;
        }

        // Pre-filter: Only calculate for promising coins
        if (!$this->isPromising($symbol, $timeframe)) {
            Log::info("⏭️ Skipping AI calculation for {$symbol} - not promising");
            return null;
        }

        // Get minimal OHLCV data (last 14 candles only)
        $rawData = MarketData::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderBy('data_timestamp', 'desc')
            ->limit(14) // Reduced from 50 to 14
            ->get(['price', 'volume', 'price_series', 'data_timestamp'])
            ->reverse()
            ->values();

        if ($rawData->count() < 14) {
            return null;
        }

        // Prepare compact data
        $ohlcvData = [];
        foreach ($rawData as $candle) {
            $priceArray = json_decode($candle->price_series, true);
            if (is_array($priceArray) && count($priceArray) >= 4) {
                $ohlcvData[] = [
                    'o' => round($priceArray[0], 4),
                    'h' => round($priceArray[1], 4), 
                    'l' => round($priceArray[2], 4),
                    'c' => round($priceArray[3], 4),
                    'v' => round($candle->volume, 0),
                ];
            }
        }

        // Compact AI prompt (minimal tokens)
        $prompt = $this->buildCompactCalculationPrompt($symbol, $ohlcvData);
        
        try {
            $response = $this->aiService->makeRequest($prompt);
            $calculations = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($calculations['i'])) {
                // Cache for 1 hour
                cache()->put($cacheKey, $calculations, 3600);
                
                Log::info("✅ AI calculated {$symbol}: RSI=" . ($calculations['i']['r7'] ?? 'N/A'));
                return $calculations;
            }
            
        } catch (\Exception $e) {
            Log::error("❌ AI calc failed {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Pre-filter promising coins to reduce AI calls
     */
    private function isPromising(string $symbol, string $timeframe): bool
    {
        $latest = MarketData::getLatest($symbol, $timeframe);
        if (!$latest) return false;

        // Quick filters
        $volumeRatio = $latest->volume_ratio ?? 0;
        $rsi = $latest->rsi7 ?? 50;
        
        // Only process if:
        return $volumeRatio >= 0.8 && // Decent volume
               ($rsi <= 30 || $rsi >= 70 || // Extreme RSI
                ($rsi >= 45 && $rsi <= 65)); // Or healthy range
    }

    /**
     * Ultra-compact prompt to minimize tokens
     */
    private function buildCompactCalculationPrompt(string $symbol, array $ohlcvData): string
    {
        $data = json_encode($ohlcvData);
        
        return "Technical Analysis Expert: Calculate precise indicators for {$symbol}

OHLCV: {$data}

Calculate for LAST candle using standard formulas:
- RSI(7): Relative Strength Index 7-period
- RSI(14): Relative Strength Index 14-period  
- MACD(12,26,9): MACD line, Signal line
- EMA(20): 20-period Exponential Moving Average
- EMA(50): 50-period Exponential Moving Average
- ADX(14): Average Directional Index
- ATR(14): Average True Range
- Volume Ratio: Current vs 20-period average

Return ONLY this JSON:
{\"i\":{\"r7\":45.6,\"r14\":52.3,\"m\":0.012,\"ms\":0.009,\"e20\":98500,\"e50\":97800,\"adx\":25.6,\"atr\":1250,\"vr\":1.23}}";
    }

    /**
     * Batch process multiple coins efficiently
     */
    public function getBatchAICalculations(array $symbols, string $timeframe = '3m'): array
    {
        $results = [];
        $batchSize = 5; // Process 5 coins at once
        
        $batches = array_chunk($symbols, $batchSize);
        
        foreach ($batches as $batch) {
            $batchData = [];
            
            foreach ($batch as $symbol) {
                $data = $this->getOptimizedAIData($symbol, $timeframe);
                if ($data) {
                    $batchData[$symbol] = $data;
                }
            }
            
            $results = array_merge($results, $batchData);
            
            // Small delay between batches
            usleep(500000); // 0.5 seconds
        }
        
        return $results;
    }
}
