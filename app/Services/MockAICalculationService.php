<?php

namespace App\Services;

class MockAICalculationService
{
    /**
     * Mock AI calculation for testing (no real AI calls)
     */
    public function getMockAICalculation(array $ohlcvData): array
    {
        // Simulate AI calculating indicators from OHLCV data
        $closes = array_column($ohlcvData, 'c');
        $volumes = array_column($ohlcvData, 'v');
        
        // Mock calculations (simplified)
        $currentPrice = end($closes);
        $avgVolume = array_sum($volumes) / count($volumes);
        $lastVolume = end($volumes);
        
        // Simulate realistic indicator values
        $mockRSI7 = $this->mockRSI($closes, 7);
        $mockRSI14 = $this->mockRSI($closes, 14);
        $mockMACD = $this->mockMACD($closes);
        $mockEMA20 = $this->mockEMA($closes, 20);
        $mockEMA50 = $this->mockEMA($closes, 50);
        $mockADX = $this->mockADX($ohlcvData);
        $mockATR = $this->mockATR($ohlcvData);
        
        return [
            'i' => [
                'r7' => round($mockRSI7, 1),
                'r14' => round($mockRSI14, 1),
                'm' => round($mockMACD['macd'], 4),
                'ms' => round($mockMACD['signal'], 4),
                'e20' => round($mockEMA20, 2),
                'e50' => round($mockEMA50, 2),
                'adx' => round($mockADX, 1),
                'atr' => round($mockATR, 2),
                'vr' => round($lastVolume / $avgVolume, 2),
            ]
        ];
    }
    
    private function mockRSI(array $closes, int $period): float
    {
        if (count($closes) < $period) return 50.0;
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i-1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }
        
        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;
        
        if ($avgLoss == 0) return 100.0;
        
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }
    
    private function mockEMA(array $closes, int $period): float
    {
        if (count($closes) < $period) return end($closes);
        
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($closes, 0, $period)) / $period;
        
        for ($i = $period; $i < count($closes); $i++) {
            $ema = ($closes[$i] * $multiplier) + ($ema * (1 - $multiplier));
        }
        
        return $ema;
    }
    
    private function mockMACD(array $closes): array
    {
        $ema12 = $this->mockEMA($closes, 12);
        $ema26 = $this->mockEMA($closes, 26);
        $macd = $ema12 - $ema26;
        $signal = $macd * 0.8; // Simplified signal
        
        return ['macd' => $macd, 'signal' => $signal];
    }
    
    private function mockADX(array $ohlcvData): float
    {
        // Simplified ADX based on price volatility
        $ranges = [];
        foreach ($ohlcvData as $candle) {
            $ranges[] = $candle['h'] - $candle['l'];
        }
        
        $avgRange = array_sum($ranges) / count($ranges);
        $currentPrice = end($ohlcvData)['c'];
        
        // Mock ADX: higher volatility = higher ADX
        return min(100, ($avgRange / $currentPrice) * 10000);
    }
    
    private function mockATR(array $ohlcvData): float
    {
        $trueRanges = [];
        
        for ($i = 1; $i < count($ohlcvData); $i++) {
            $current = $ohlcvData[$i];
            $previous = $ohlcvData[$i-1];
            
            $tr1 = $current['h'] - $current['l'];
            $tr2 = abs($current['h'] - $previous['c']);
            $tr3 = abs($current['l'] - $previous['c']);
            
            $trueRanges[] = max($tr1, $tr2, $tr3);
        }
        
        return array_sum($trueRanges) / count($trueRanges);
    }
}
