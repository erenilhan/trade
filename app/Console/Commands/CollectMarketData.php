<?php

namespace App\Console\Commands;

use App\Services\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectMarketData extends Command
{
    protected $signature = 'market:collect 
                           {--continuous : Run continuously every 3 minutes}
                           {--coins= : Specific coins to collect (comma-separated)}';

    protected $description = 'Collect market data for all supported coins';

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
    }

    private function collectData(MarketDataService $marketDataService)
    {
        $this->info('📊 Collecting market data...');
        
        try {
            // Get supported coins
            $coins = $this->option('coins') 
                ? explode(',', $this->option('coins'))
                : MarketDataService::getSupportedCoins();
            
            $this->info("📈 Processing " . count($coins) . " coins...");
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($coins as $symbol) {
                $symbol = trim($symbol);
                
                try {
                    // Collect for both 3m and 4h timeframes
                    $marketDataService->collectAllMarketData($symbol, '3m');
                    $marketDataService->collectAllMarketData($symbol, '4h');
                    
                    $this->line("  ✅ {$symbol} - OK");
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $this->error("  ❌ {$symbol} - ERROR: " . $e->getMessage());
                    Log::error("Market data collection failed for {$symbol}: " . $e->getMessage());
                    $errorCount++;
                }
                
                // Small delay to avoid rate limits
                usleep(100000); // 0.1 second
            }
            
            $this->info("✅ Collection complete: {$successCount} success, {$errorCount} errors");
            
            if ($successCount > 0) {
                $this->info("📊 Market data updated at " . now()->format('H:i:s'));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Market data collection failed: ' . $e->getMessage());
            Log::error('Market data collection failed: ' . $e->getMessage());
        }
    }
}
