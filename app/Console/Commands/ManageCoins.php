<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use Illuminate\Console\Command;

class ManageCoins extends Command
{
    protected $signature = 'coins:manage {action} {coins?*}';
    protected $description = 'Manage supported coins (list, set, add, remove)';

    public function handle(): int
    {
        $action = $this->argument('action');
        $coins = $this->argument('coins');

        return match($action) {
            'list' => $this->listCoins(),
            'set' => $this->setCoins($coins),
            'add' => $this->addCoins($coins),
            'remove' => $this->removeCoins($coins),
            'reset' => $this->resetToDefault(),
            default => $this->showHelp()
        };
    }

    private function listCoins(): int
    {
        $coins = $this->getCurrentCoins();
        $this->info('Current supported coins (' . count($coins) . '):');
        foreach ($coins as $coin) {
            $this->line("  • {$coin}");
        }
        return self::SUCCESS;
    }

    private function setCoins(array $coins): int
    {
        if (empty($coins)) {
            $this->error('Please provide coins to set');
            return self::FAILURE;
        }

        $validCoins = $this->validateCoins($coins);
        BotSetting::set('supported_coins', $validCoins);
        
        $this->info('Updated supported coins to:');
        foreach ($validCoins as $coin) {
            $this->line("  • {$coin}");
        }
        
        return self::SUCCESS;
    }

    private function addCoins(array $coins): int
    {
        if (empty($coins)) {
            $this->error('Please provide coins to add');
            return self::FAILURE;
        }

        $current = $this->getCurrentCoins();
        $validCoins = $this->validateCoins($coins);
        $newCoins = array_unique(array_merge($current, $validCoins));
        
        BotSetting::set('supported_coins', $newCoins);
        
        $this->info('Added coins. New list:');
        foreach ($newCoins as $coin) {
            $this->line("  • {$coin}");
        }
        
        return self::SUCCESS;
    }

    private function removeCoins(array $coins): int
    {
        if (empty($coins)) {
            $this->error('Please provide coins to remove');
            return self::FAILURE;
        }

        $current = $this->getCurrentCoins();
        $newCoins = array_diff($current, $coins);
        
        if (empty($newCoins)) {
            $this->error('Cannot remove all coins');
            return self::FAILURE;
        }
        
        BotSetting::set('supported_coins', array_values($newCoins));
        
        $this->info('Removed coins. New list:');
        foreach ($newCoins as $coin) {
            $this->line("  • {$coin}");
        }
        
        return self::SUCCESS;
    }

    private function resetToDefault(): int
    {
        $defaultCoins = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT'];
        BotSetting::set('supported_coins', $defaultCoins);
        
        $this->info('Reset to default 6 coins:');
        foreach ($defaultCoins as $coin) {
            $this->line("  • {$coin}");
        }
        
        return self::SUCCESS;
    }

    private function getCurrentCoins(): array
    {
        $coins = BotSetting::get('supported_coins', []);
        if (is_string($coins)) {
            $coins = json_decode($coins, true) ?? [];
        }
        return $coins;
    }

    private function validateCoins(array $coins): array
    {
        $valid = [];
        foreach ($coins as $coin) {
            if (!str_ends_with($coin, '/USDT')) {
                $coin .= '/USDT';
            }
            $valid[] = strtoupper($coin);
        }
        return $valid;
    }

    private function showHelp(): int
    {
        $this->info('Usage:');
        $this->line('  coins:manage list                    - Show current coins');
        $this->line('  coins:manage set BTC ETH SOL        - Set specific coins');
        $this->line('  coins:manage add ADA DOT            - Add coins to current list');
        $this->line('  coins:manage remove ADA DOT         - Remove coins from list');
        $this->line('  coins:manage reset                  - Reset to default 6 coins');
        $this->line('');
        $this->line('Examples:');
        $this->line('  coins:manage set BTC ETH SOL BNB XRP DOGE');
        $this->line('  coins:manage add ADA/USDT DOT/USDT');
        
        return self::SUCCESS;
    }
}
