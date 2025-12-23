<?php

namespace App\Console\Commands;

use App\Services\BinanceService;
use Illuminate\Console\Command;

class CheckBalance extends Command
{
    protected $signature = 'balance:check';
    protected $description = 'Check both spot and futures balances';

    public function handle()
    {
        $binance = app(BinanceService::class);
        
        $this->info('💰 Checking Binance Balances...');
        $this->newLine();
        
        // Check Spot Balance
        try {
            $this->info('📊 SPOT BALANCE:');
            $spotBalance = $binance->getExchange()->fetch_balance();
            $spotUsdt = $spotBalance['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];
            
            $this->line("  Free: $" . number_format($spotUsdt['free'], 2));
            $this->line("  Used: $" . number_format($spotUsdt['used'], 2));
            $this->line("  Total: $" . number_format($spotUsdt['total'], 2));
            
        } catch (\Exception $e) {
            $this->error('Spot balance error: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // Check Futures Balance
        try {
            $this->info('🚀 FUTURES BALANCE:');
            $futuresBalance = $binance->getExchange()->fetch_balance(['type' => 'future']);
            $futuresUsdt = $futuresBalance['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];
            
            $this->line("  Free: $" . number_format($futuresUsdt['free'], 2));
            $this->line("  Used: $" . number_format($futuresUsdt['used'], 2));
            $this->line("  Total: $" . number_format($futuresUsdt['total'], 2));
            
        } catch (\Exception $e) {
            $this->error('Futures balance error: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // Current bot balance method
        try {
            $this->info('🤖 BOT CURRENT METHOD:');
            $botBalance = $binance->fetchBalance();
            $botUsdt = $botBalance['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];
            
            $this->line("  Free: $" . number_format($botUsdt['free'], 2));
            $this->line("  Used: $" . number_format($botUsdt['used'], 2));
            $this->line("  Total: $" . number_format($botUsdt['total'], 2));
            
        } catch (\Exception $e) {
            $this->error('Bot balance error: ' . $e->getMessage());
        }
    }
}
