<?php

namespace App\Console\Commands;

use App\Services\TaapiService;
use App\Services\MarketDataService;
use Illuminate\Console\Command;

class TestTaapiIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taapi:test {symbol=BTC/USDT} {interval=1h}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test TAAPI.IO integration and fetch indicators';

    /**
     * Execute the console command.
     */
    public function handle(TaapiService $taapi, MarketDataService $marketData)
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');

        $this->info("🧪 Testing TAAPI integration for {$symbol} at {$interval} interval...");
        $this->newLine();

        // Show current usage
        $usage = $taapi->getUsageStats();
        $this->info("📊 Current API Usage: {$usage['request_count']}/{$usage['daily_limit']} ({$usage['remaining']} remaining)");
        $this->newLine();

        try {
            // Test individual indicators
            $this->info("📈 Fetching indicators...");

            $ema20 = $taapi->getEMA($symbol, $interval, 20);
            $emaValue = is_array($ema20['value']) ? json_encode($ema20['value']) : $ema20['value'];
            $this->line("✓ EMA(20): " . $emaValue);

            $macd = $taapi->getMACD($symbol, $interval);
            $macdValue = is_array($macd['macd']) ? end($macd['macd']) : $macd['macd'];
            $signalValue = is_array($macd['signal']) ? end($macd['signal']) : $macd['signal'];
            $histogramValue = is_array($macd['histogram']) ? end($macd['histogram']) : $macd['histogram'];
            $this->line("✓ MACD: {$macdValue} | Signal: {$signalValue} | Histogram: {$histogramValue}");

            $rsi = $taapi->getRSI($symbol, $interval, 14);
            $rsiValue = is_array($rsi['value']) ? json_encode($rsi['value']) : $rsi['value'];
            $this->line("✓ RSI(14): " . $rsiValue);

            $atr = $taapi->getATR($symbol, $interval, 14);
            $this->line("✓ ATR(14): {$atr}");

            $bbands = $taapi->getBollingerBands($symbol, $interval);
            $this->line("✓ Bollinger Bands: Upper={$bbands['upper']}, Middle={$bbands['middle']}, Lower={$bbands['lower']}");

            $supertrend = $taapi->getSupertrend($symbol, $interval);
            $trend = $supertrend['is_bullish'] ? '🟢 Bullish' : '🔴 Bearish';
            $this->line("✓ Supertrend: {$trend} | Value: {$supertrend['value']}");

            $this->newLine();
            $this->info("✅ All indicators fetched successfully!");

            // Show updated usage
            $usage = $taapi->getUsageStats();
            $this->info("📊 Updated API Usage: {$usage['request_count']}/{$usage['daily_limit']} ({$usage['remaining']} remaining)");

            $this->newLine();
            $this->info("🧪 Testing full market data collection...");

            // Test full market data collection
            $data = $marketData->collectMarketData($symbol, $interval);
            $this->info("✅ Market data collected successfully!");
            $this->line("   Price: {$data['price']}");
            $this->line("   EMA20: {$data['ema20']}");
            $this->line("   RSI7: {$data['rsi7']}");
            $this->line("   RSI14: {$data['rsi14']}");
            $this->line("   Volume: {$data['volume']}");

            // Final usage
            $usage = $taapi->getUsageStats();
            $this->newLine();
            $this->info("📊 Final API Usage: {$usage['request_count']}/{$usage['daily_limit']} ({$usage['remaining']} remaining)");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
