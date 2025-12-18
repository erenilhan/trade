<?php

namespace App\Console\Commands;

use App\Services\TaapiService;
use App\Services\BinanceService;
use App\Services\IndicatorCalculator;
use Illuminate\Console\Command;

class CompareIndicators extends Command
{
    protected $signature = 'indicators:compare {symbol=BTC/USDT} {interval=1h}';
    protected $description = 'Compare TAAPI.IO indicators with pure PHP calculations';

    public function handle(TaapiService $taapi, BinanceService $binance, IndicatorCalculator $calculator)
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');

        $this->info("🔬 Comparing indicators for {$symbol} at {$interval} interval...");
        $this->newLine();

        try {
            // Fetch OHLCV data from Binance (public API, no auth needed)
            $this->info("📊 Fetching OHLCV data from Binance public API...");

            // Use CCXT without authentication for public endpoints
            $publicExchange = new \ccxt\binance([
                'enableRateLimit' => true,
            ]);

            $ohlcv = $publicExchange->fetchOHLCV($symbol, $interval, null, 50);

            if (empty($ohlcv)) {
                $this->error("Failed to fetch OHLCV data");
                return Command::FAILURE;
            }

            $this->info("✓ Fetched " . count($ohlcv) . " candles");
            $this->newLine();

            // Calculate with pure PHP
            $this->info("🧮 Calculating indicators with Pure PHP...");
            $phpStart = microtime(true);
            $phpIndicators = $calculator->calculateIndicators($ohlcv);
            $phpTime = microtime(true) - $phpStart;
            $this->info("✓ PHP calculation completed in " . round($phpTime * 1000, 2) . "ms");
            $this->newLine();

            // Fetch from TAAPI
            $this->info("📡 Fetching indicators from TAAPI.IO...");
            $taapiStart = microtime(true);

            $ema20 = $taapi->getEMA($symbol, $interval, 20);
            $ema50 = $taapi->getEMA($symbol, $interval, 50);
            $macd = $taapi->getMACD($symbol, $interval);
            $rsi7 = $taapi->getRSI($symbol, $interval, 7);
            $rsi14 = $taapi->getRSI($symbol, $interval, 14);
            $atr3 = $taapi->getATR($symbol, $interval, 3);
            $atr14 = $taapi->getATR($symbol, $interval, 14);
            $adx = $taapi->getADX($symbol, $interval, 14);
            $bbands = $taapi->getBollingerBands($symbol, $interval, 20, 2);
            $stochRsi = $taapi->getStochasticRSI($symbol, $interval, 14);
            $supertrend = $taapi->getSupertrend($symbol, $interval, 10, 3);

            $taapiTime = microtime(true) - $taapiStart;
            $this->info("✓ TAAPI fetch completed in " . round($taapiTime * 1000, 2) . "ms");
            $this->newLine();

            // Extract scalar values from TAAPI responses
            $taapiIndicators = [
                'ema20' => is_array($ema20['value']) ? end($ema20['value']) : $ema20['value'],
                'ema50' => is_array($ema50['value']) ? end($ema50['value']) : $ema50['value'],
                'macd' => is_array($macd['macd']) ? end($macd['macd']) : $macd['macd'],
                'macd_signal' => is_array($macd['signal']) ? end($macd['signal']) : $macd['signal'],
                'macd_histogram' => is_array($macd['histogram']) ? end($macd['histogram']) : $macd['histogram'],
                'rsi7' => is_array($rsi7['value']) ? end($rsi7['value']) : $rsi7['value'],
                'rsi14' => is_array($rsi14['value']) ? end($rsi14['value']) : $rsi14['value'],
                'atr3' => $atr3,
                'atr14' => $atr14,
                'adx' => $adx['adx'],
                'plus_di' => $adx['plus_di'],
                'minus_di' => $adx['minus_di'],
                'bb_upper' => $bbands['upper'],
                'bb_middle' => $bbands['middle'],
                'bb_lower' => $bbands['lower'],
                'stoch_rsi_k' => $stochRsi['k'],
                'stoch_rsi_d' => $stochRsi['d'],
                'supertrend_value' => $supertrend['value'],
                'supertrend_trend' => $supertrend['trend'],
            ];

            // Display comparison
            $this->info("📊 COMPARISON RESULTS");
            $this->line(str_repeat('=', 100));
            $this->newLine();

            $this->displayComparison('EMA(20)', $phpIndicators['ema20'], $taapiIndicators['ema20']);
            $this->displayComparison('EMA(50)', $phpIndicators['ema50'], $taapiIndicators['ema50']);
            $this->newLine();

            $this->displayComparison('MACD', $phpIndicators['macd'], $taapiIndicators['macd']);
            $this->displayComparison('MACD Signal', $phpIndicators['macd_signal'], $taapiIndicators['macd_signal']);
            $this->displayComparison('MACD Histogram', $phpIndicators['macd_histogram'], $taapiIndicators['macd_histogram']);
            $this->newLine();

            $this->displayComparison('RSI(7)', $phpIndicators['rsi7'], $taapiIndicators['rsi7']);
            $this->displayComparison('RSI(14)', $phpIndicators['rsi14'], $taapiIndicators['rsi14']);
            $this->newLine();

            $this->displayComparison('ATR(3)', $phpIndicators['atr3'], $taapiIndicators['atr3']);
            $this->displayComparison('ATR(14)', $phpIndicators['atr14'], $taapiIndicators['atr14']);
            $this->newLine();

            $this->displayComparison('ADX', $phpIndicators['adx'], $taapiIndicators['adx']);
            $this->displayComparison('+DI', $phpIndicators['plus_di'], $taapiIndicators['plus_di']);
            $this->displayComparison('-DI', $phpIndicators['minus_di'], $taapiIndicators['minus_di']);
            $this->newLine();

            $this->displayComparison('BB Upper', $phpIndicators['bb_upper'], $taapiIndicators['bb_upper']);
            $this->displayComparison('BB Middle', $phpIndicators['bb_middle'], $taapiIndicators['bb_middle']);
            $this->displayComparison('BB Lower', $phpIndicators['bb_lower'], $taapiIndicators['bb_lower']);
            $this->newLine();

            $this->displayComparison('Stoch RSI %K', $phpIndicators['stoch_rsi_k'], $taapiIndicators['stoch_rsi_k']);
            $this->displayComparison('Stoch RSI %D', $phpIndicators['stoch_rsi_d'], $taapiIndicators['stoch_rsi_d']);
            $this->newLine();

            $this->displayComparison('Supertrend Value', $phpIndicators['supertrend_value'], $taapiIndicators['supertrend_value']);
            $this->displayComparison('Supertrend Trend', $phpIndicators['supertrend_trend'], $taapiIndicators['supertrend_trend']);

            $this->newLine();
            $this->line(str_repeat('=', 100));
            $this->info("⏱️  Performance: PHP={$phpTime}s vs TAAPI={$taapiTime}s");

            // Show usage stats
            $usage = $taapi->getUsageStats();
            $this->info("📊 TAAPI Usage: {$usage['request_count']}/{$usage['daily_limit']} ({$usage['remaining']} remaining)");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function displayComparison(string $indicator, $phpValue, $taapiValue)
    {
        // Format values
        $phpStr = is_numeric($phpValue) ? number_format($phpValue, 4) : (string)$phpValue;
        $taapiStr = is_numeric($taapiValue) ? number_format($taapiValue, 4) : (string)$taapiValue;

        // Calculate difference percentage
        $diff = '';
        $color = 'white';

        if (is_numeric($phpValue) && is_numeric($taapiValue) && $phpValue != 0) {
            $diffPercent = abs((($taapiValue - $phpValue) / $phpValue) * 100);

            if ($diffPercent < 0.01) {
                $diff = '✓ Perfect match';
                $color = 'green';
            } elseif ($diffPercent < 1) {
                $diff = sprintf('≈ %.3f%% diff', $diffPercent);
                $color = 'green';
            } elseif ($diffPercent < 5) {
                $diff = sprintf('⚠ %.2f%% diff', $diffPercent);
                $color = 'yellow';
            } else {
                $diff = sprintf('✗ %.2f%% diff', $diffPercent);
                $color = 'red';
            }
        } elseif ($phpValue == $taapiValue) {
            $diff = '✓ Match';
            $color = 'green';
        } else {
            $diff = '✗ Different';
            $color = 'red';
        }

        // Display
        $label = str_pad($indicator, 20);
        $php = str_pad("PHP: {$phpStr}", 25);
        $taapi = str_pad("TAAPI: {$taapiStr}", 25);

        $this->line("<fg={$color}>{$label} | {$php} | {$taapi} | {$diff}</>");
    }
}
