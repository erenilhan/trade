<?php

/**
 * TRADING STRATEGY DOCUMENTATION
 * 
 * Complete LONG and SHORT entry/exit logic for crypto trading bot
 */

class TradingStrategy
{
    /**
     * LONG POSITION ENTRY CRITERIA
     * All 5 criteria must be TRUE
     */
    public function shouldEnterLong($marketData, $data4h): bool
    {
        // 1. MACD Bullish: MACD > Signal AND MACD > 0
        $macdBullish = ($marketData['macd'] > $marketData['macd_signal']) && 
                       ($marketData['macd'] > 0);
        
        // 2. RSI Healthy: RSI(7) between 45-72 (not overbought)
        $rsiHealthy = ($marketData['rsi7'] >= 45) && ($marketData['rsi7'] <= 72);
        
        // 3. Price Above EMA: 0-2% above EMA20 (riding uptrend)
        $priceAboveEma = ($marketData['close'] >= $marketData['ema20']) && 
                         ($marketData['close'] <= $marketData['ema20'] * 1.02);
        
        // 4. 4H Strong Uptrend: EMA20 > EMA50 AND ADX > 20
        $strongUptrend = ($data4h['ema20'] > $data4h['ema50']) && 
                         ($data4h['adx'] > 20);
        
        // 5. Volume Confirmation: Volume Ratio ≥ 1.0x
        $volumeConfirm = $marketData['volume_ratio'] >= 1.0;
        
        return $macdBullish && $rsiHealthy && $priceAboveEma && $strongUptrend && $volumeConfirm;
    }

    /**
     * SHORT POSITION ENTRY CRITERIA
     * All 5 criteria must be TRUE
     */
    public function shouldEnterShort($marketData, $data4h): bool
    {
        // 1. MACD Bearish: MACD < Signal AND MACD < 0
        $macdBearish = ($marketData['macd'] < $marketData['macd_signal']) && 
                       ($marketData['macd'] < 0);
        
        // 2. RSI Healthy: RSI(7) between 25-60 (not oversold)
        $rsiHealthy = ($marketData['rsi7'] >= 25) && ($marketData['rsi7'] <= 60);
        
        // 3. Price Below EMA: 0-2% below EMA20 (riding downtrend)
        $priceBelowEma = ($marketData['close'] <= $marketData['ema20']) && 
                         ($marketData['close'] >= $marketData['ema20'] * 0.98);
        
        // 4. 4H Strong Downtrend: EMA20 < EMA50 AND ADX > 20
        $strongDowntrend = ($data4h['ema20'] < $data4h['ema50']) && 
                           ($data4h['adx'] > 20);
        
        // 5. Volume Confirmation: Volume Ratio ≥ 1.0x
        $volumeConfirm = $marketData['volume_ratio'] >= 1.0;
        
        return $macdBearish && $rsiHealthy && $priceBelowEma && $strongDowntrend && $volumeConfirm;
    }

    /**
     * LONG POSITION EXIT CRITERIA (Trend Invalidation)
     * 2+ signals + PNL < 2% = Close early
     * 3+ signals = Close regardless of PNL
     */
    public function shouldExitLong($marketData, $data4h, $currentPrice, $pnlPercent): array
    {
        $invalidationReasons = [];
        
        // Price broke below EMA20
        if ($currentPrice < $marketData['ema20']) {
            $invalidationReasons[] = "Price < EMA20 ({$marketData['ema20']})";
        }
        
        // MACD turned negative
        if ($marketData['macd'] < 0) {
            $invalidationReasons[] = "MACD turned negative ({$marketData['macd']})";
        }
        
        // 4H trend weakened
        if ($data4h['adx'] < 20) {
            $invalidationReasons[] = "4H ADX weak ({$data4h['adx']} < 20)";
        }
        
        // 4H trend reversed
        if ($data4h['ema20'] < $data4h['ema50']) {
            $invalidationReasons[] = "4H trend reversed (EMA20 < EMA50)";
        }
        
        $shouldClose = false;
        $reason = '';
        
        if (count($invalidationReasons) >= 3) {
            $shouldClose = true;
            $reason = 'Strong trend invalidation (3+ signals)';
        } elseif (count($invalidationReasons) >= 2 && $pnlPercent < 2) {
            $shouldClose = true;
            $reason = 'Trend invalidation (2+ signals, low profit)';
        }
        
        return [
            'should_close' => $shouldClose,
            'reason' => $reason,
            'signals' => $invalidationReasons
        ];
    }

    /**
     * SHORT POSITION EXIT CRITERIA (Trend Invalidation)
     * 2+ signals + PNL < 2% = Close early
     * 3+ signals = Close regardless of PNL
     */
    public function shouldExitShort($marketData, $data4h, $currentPrice, $pnlPercent): array
    {
        $invalidationReasons = [];
        
        // Price broke above EMA20
        if ($currentPrice > $marketData['ema20']) {
            $invalidationReasons[] = "Price > EMA20 ({$marketData['ema20']})";
        }
        
        // MACD turned positive
        if ($marketData['macd'] > 0) {
            $invalidationReasons[] = "MACD turned positive ({$marketData['macd']})";
        }
        
        // 4H trend weakened
        if ($data4h['adx'] < 20) {
            $invalidationReasons[] = "4H ADX weak ({$data4h['adx']} < 20)";
        }
        
        // 4H trend reversed
        if ($data4h['ema20'] > $data4h['ema50']) {
            $invalidationReasons[] = "4H trend reversed (EMA20 > EMA50)";
        }
        
        $shouldClose = false;
        $reason = '';
        
        if (count($invalidationReasons) >= 3) {
            $shouldClose = true;
            $reason = 'Strong trend invalidation (3+ signals)';
        } elseif (count($invalidationReasons) >= 2 && $pnlPercent < 2) {
            $shouldClose = true;
            $reason = 'Trend invalidation (2+ signals, low profit)';
        }
        
        return [
            'should_close' => $shouldClose,
            'reason' => $reason,
            'signals' => $invalidationReasons
        ];
    }

    /**
     * TAKE PROFIT & STOP LOSS LEVELS
     */
    public function getExitLevels($entryPrice, $side, $atr14): array
    {
        if ($side === 'LONG') {
            return [
                'take_profit' => $entryPrice * 1.06,  // +6%
                'stop_loss' => $entryPrice * (1 - max(0.05, min(0.15, $atr14 * 2.5 / 100)))
            ];
        } else { // SHORT
            return [
                'take_profit' => $entryPrice * 0.94,  // -6%
                'stop_loss' => $entryPrice * (1 + max(0.05, min(0.15, $atr14 * 2.5 / 100)))
            ];
        }
    }

    /**
     * TRAILING STOP LEVELS
     */
    public function getTrailingStops(): array
    {
        return [
            'L1' => ['trigger' => 999, 'target' => -1],    // DISABLED (0% win rate)
            'L2' => ['trigger' => 8, 'target' => 2],       // 8% profit -> 2% stop
            'L3' => ['trigger' => 8, 'target' => 3],       // 8% profit -> 3% stop  
            'L4' => ['trigger' => 12, 'target' => 6],      // 12% profit -> 6% stop
        ];
    }

    /**
     * PRE-FILTERING CRITERIA
     * Reduces AI token usage by 70%+
     */
    public function shouldAnalyzeWithAI($marketData, $data4h): bool
    {
        // Skip if 4H ADX too weak
        if ($data4h['adx'] < 20) return false;
        
        // Skip if too volatile
        if ($marketData['atr14'] > 8) return false;
        
        // Time-aware volume thresholds
        $hour = (int)date('H');
        $isUSHours = ($hour >= 13 && $hour <= 22); // 13:00-22:00 UTC
        $minVolume = $isUSHours ? 0.9 : 1.0;
        
        if ($marketData['volume_ratio'] < $minVolume) return false;
        
        // Scoring system (need 3/5 to pass)
        $score = 0;
        
        // MACD alignment with 4H trend
        if (($marketData['macd'] > 0 && $data4h['ema20'] > $data4h['ema50']) ||
            ($marketData['macd'] < 0 && $data4h['ema20'] < $data4h['ema50'])) {
            $score++;
        }
        
        // RSI in healthy range
        if ($marketData['rsi7'] >= 25 && $marketData['rsi7'] <= 72) {
            $score++;
        }
        
        // Price near EMA20 (±2-5%)
        $priceEmaRatio = $marketData['close'] / $marketData['ema20'];
        if ($priceEmaRatio >= 0.95 && $priceEmaRatio <= 1.05) {
            $score++;
        }
        
        // Strong ADX (>25 preferred)
        if ($data4h['adx'] > 25) {
            $score++;
        }
        
        // Volume already checked above, so +1
        $score++;
        
        return $score >= 3;
    }

    /**
     * CONFIDENCE FILTERING
     * Block overconfident AI (inverse correlation trap)
     */
    public function shouldExecuteTrade($confidence): bool
    {
        // Block too low confidence
        if ($confidence < 60) return false;
        
        // Block overconfidence (inverse correlation)
        if ($confidence > 82) return false;
        
        return true;
    }

    /**
     * POSITION SIZING
     */
    public function getPositionSize(): array
    {
        return [
            'base_usdt' => 10,      // $10 USDT base
            'leverage' => 2,        // 2x leverage (proven best)
            'notional' => 20,       // $10 × 2x = $20
        ];
    }

    /**
     * RISK MANAGEMENT LIMITS
     */
    public function getRiskLimits(): array
    {
        return [
            'max_daily_drawdown' => 8,          // 8% per day
            'max_open_positions' => 3,          // Total positions
            'max_positions_per_symbol' => 1,    // Per symbol
            'cluster_loss_limit' => 3,          // Consecutive losses
            'sleep_mode_hours' => [23, 4],      // 23:00-04:00 UTC
            'min_cash_requirement' => 10,       // $10 minimum
        ];
    }
}
