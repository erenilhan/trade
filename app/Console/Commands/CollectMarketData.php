<?php

namespace App\Console\Commands;

use App\Services\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectMarketData extends Command
{
    protected $signature = 'market:collect 
                           {--continuous : Run continuously every 3 minutes}
                           {--coins= : Specific coins to collect (comma-separated)}
                           {--main-only : Only collect main 6 coins (BTC,ETH,SOL,BNB,XRP,DOGE)}';

    protected $description = 'Collect market data for supported coins';

    public function handle()
    {
        $marketDataService = app(MarketDataService::class);

        if ($this->option('continuous')) {
            $this->info('🔄 Starting continuous market data collection (every 3 minutes)...');
            $this->info('Press Ctrl+C to stop');

            while (true) {
                $this->collectData($marketDataService);
                sleep(180); // 3 minutes
            }
        } else {
            $this->collectData($marketDataService);
        }

        //Deleta old market data
        $this->info('🧹 Cleaning up old market data...');
        $deletedCount = $marketDataService->deleteOldMarketData(7); // Delete data older than 7 days
        $this->info("🗑️ Deleted {$deletedCount} old market data records.");

    }

    private function collectData(MarketDataService $marketDataService)
    {
        $this->info('📊 Collecting market data...');

        try {
            // Get supported coins
            if ($this->option('coins')) {
                $coins = explode(',', $this->option('coins'));
            } elseif ($this->option('main-only')) {
                $coins = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT'];
            } else {
                $coins = MarketDataService::getSupportedCoins();
            }

            $this->info("📈 Processing " . count($coins) . " coins in parallel...");

            $successCount = 0;
            $errorCount = 0;

            // Process coins in chunks of 10 for better performance
            $chunks = array_chunk($coins, 10);

            foreach ($chunks as $chunkIndex => $chunk) {
                $this->info("📦 Processing chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " (" . count($chunk) . " coins)");

                foreach ($chunk as $symbol) {
                    $symbol = trim($symbol);

                    try {
                        // Use the individual collectMarketData method
                        $marketDataService->collectMarketData($symbol, '3m');
                        $marketDataService->collectMarketData($symbol, '4h');

                        $this->line("  ✅ {$symbol}");
                        $successCount++;

                    } catch (\Exception $e) {
                        $this->error("  ❌ {$symbol} - " . substr($e->getMessage(), 0, 50));
                        $errorCount++;
                    }
                }

                // Small delay between chunks
                if ($chunkIndex < count($chunks) - 1) {
                    usleep(100000); // 0.1 seconds
                }
            }

            $this->info("✅ Complete: {$successCount} success, {$errorCount} errors");

        } catch (\Exception $e) {
            $this->error('❌ Collection failed: ' . $e->getMessage());
        }
    }
}
