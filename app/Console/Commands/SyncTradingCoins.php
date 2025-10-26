<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use Illuminate\Console\Command;

class SyncTradingCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:sync {--reset : Reset to default config pairs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all coins from config/trading.php to active trading list';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Syncing trading coins...');
        $this->newLine();

        // Get all coins from config
        $allCoins = array_keys(config('trading.supported_pairs', []));

        if (empty($allCoins)) {
            $this->error('❌ No coins found in config/trading.php!');
            return 1;
        }

        // Get current active coins
        $currentCoins = BotSetting::get('supported_coins', []);

        $this->info('📊 Config has ' . count($allCoins) . ' coins');
        $this->info('📊 Currently trading ' . count($currentCoins) . ' coins');
        $this->newLine();

        // Show what will be added/removed
        $newCoins = array_diff($allCoins, $currentCoins);
        $removedCoins = array_diff($currentCoins, $allCoins);

        if (!empty($newCoins)) {
            $this->info('➕ Will ADD these coins:');
            foreach ($newCoins as $coin) {
                $this->line('   • ' . $coin);
            }
            $this->newLine();
        }

        if (!empty($removedCoins)) {
            $this->warn('➖ Will REMOVE these coins:');
            foreach ($removedCoins as $coin) {
                $this->line('   • ' . $coin);
            }
            $this->newLine();
        }

        if (empty($newCoins) && empty($removedCoins)) {
            $this->info('✅ Already in sync! No changes needed.');
            return 0;
        }

        // Confirm
        if (!$this->option('reset') && !$this->confirm('Do you want to proceed?', true)) {
            $this->info('❌ Cancelled.');
            return 0;
        }

        // Update BotSetting
        BotSetting::set('supported_coins', $allCoins);

        $this->newLine();
        $this->info('✅ Successfully synced!');
        $this->info('📊 Now trading ' . count($allCoins) . ' coins:');

        // Show all active coins
        foreach ($allCoins as $coin) {
            $description = config('trading.supported_pairs.' . $coin, '');
            $this->line('   ✓ ' . $coin . ' ' . $description);
        }

        return 0;
    }
}
