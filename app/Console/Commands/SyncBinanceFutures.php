<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncBinanceFutures extends Command
{
    protected $signature = 'binance:sync-futures {--top=50 : Number of top pairs to show} {--update : Update bot settings with top coins}';
    protected $description = 'Get available Binance Futures USDT pairs and optionally update bot settings';

    public function handle()
    {
        $this->info('🔍 Fetching Binance Futures markets with 24h volume...');
        
        try {
            // Get 24h ticker statistics
            $tickerResponse = Http::get('https://fapi.binance.com/fapi/v1/ticker/24hr');
            
            if (!$tickerResponse->successful()) {
                $this->error('Failed to fetch ticker data');
                return 1;
            }
            
            $tickers = collect($tickerResponse->json())
                ->filter(fn($ticker) => str_ends_with($ticker['symbol'], 'USDT'))
                ->map(fn($ticker) => [
                    'symbol' => str_replace('USDT', '/USDT', $ticker['symbol']),
                    'volume' => (float) $ticker['quoteVolume'],
                    'change' => (float) $ticker['priceChangePercent']
                ])
                ->sortByDesc('volume')
                ->take($this->option('top'));
            
            $this->info("Top {$tickers->count()} Binance Futures USDT pairs by 24h volume:");
            $this->newLine();
            
            foreach ($tickers as $ticker) {
                $volume = '$' . number_format($ticker['volume'] / 1000000, 1) . 'M';
                $change = ($ticker['change'] >= 0 ? '+' : '') . number_format($ticker['change'], 2) . '%';
                $changeColor = $ticker['change'] >= 0 ? 'green' : 'red';
                
                $this->line("• <options=bold>{$ticker['symbol']}</> - Volume: {$volume} - <fg={$changeColor}>{$change}</>");
            }
            
            $this->newLine();
            $this->info('✅ Sync completed!');
            
            // Update bot settings if requested
            if ($this->option('update')) {
                $topCoins = $tickers->take(30)->pluck('symbol')->toArray();
                \App\Models\BotSetting::set('supported_coins', $topCoins);
                $this->info("🔄 Updated bot settings with top 30 coins");
                $this->line("Active coins: " . implode(', ', $topCoins));
            } else {
                $this->comment("💡 Run with --update to automatically update bot settings");
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
