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
            
            // Get exchange info for minimum notional values
            $exchangeResponse = Http::get('https://fapi.binance.com/fapi/v1/exchangeInfo');
            
            if (!$exchangeResponse->successful()) {
                $this->error('Failed to fetch exchange info');
                return 1;
            }
            
            $exchangeInfo = collect($exchangeResponse->json()['symbols'])
                ->keyBy('symbol');
            
            $tickers = collect($tickerResponse->json())
                ->filter(fn($ticker) => str_ends_with($ticker['symbol'], 'USDT'))
                ->map(function($ticker) use ($exchangeInfo) {
                    $symbol = $ticker['symbol'];
                    $symbolInfo = $exchangeInfo->get($symbol);
                    
                    // Find minimum notional filter
                    $minNotional = 5; // Default fallback
                    if ($symbolInfo && isset($symbolInfo['filters'])) {
                        foreach ($symbolInfo['filters'] as $filter) {
                            if ($filter['filterType'] === 'MIN_NOTIONAL') {
                                $minNotional = (float) $filter['notional'];
                                break;
                            }
                        }
                    }
                    
                    return [
                        'symbol' => str_replace('USDT', '/USDT', $ticker['symbol']),
                        'volume' => (float) $ticker['quoteVolume'],
                        'change' => (float) $ticker['priceChangePercent'],
                        'minNotional' => $minNotional
                    ];
                })
                ->filter(fn($ticker) => $ticker['minNotional'] <= 10) // Filter for $10 or less
                ->sortByDesc('volume')
                ->take($this->option('top'));
            
            $this->info("Top {$tickers->count()} Binance Futures USDT pairs (≤$10 min trade) by 24h volume:");
            $this->newLine();
            
            foreach ($tickers as $ticker) {
                $volume = '$' . number_format($ticker['volume'] / 1000000, 1) . 'M';
                $change = ($ticker['change'] >= 0 ? '+' : '') . number_format($ticker['change'], 2) . '%';
                $changeColor = $ticker['change'] >= 0 ? 'green' : 'red';
                $minTrade = '$' . number_format($ticker['minNotional'], 0);
                
                $this->line("• <options=bold>{$ticker['symbol']}</> - Volume: {$volume} - <fg={$changeColor}>{$change}</> - Min: {$minTrade}");
            }
            
            $this->newLine();
            $this->info('✅ Sync completed!');
            
            // Update bot settings if requested
            if ($this->option('update')) {
                $topCoins = $tickers->take(30)->pluck('symbol')->toArray();
                \App\Models\BotSetting::set('supported_coins', $topCoins);
                $this->info("🔄 Updated bot settings with top 30 coins (≤$10 min trade)");
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
