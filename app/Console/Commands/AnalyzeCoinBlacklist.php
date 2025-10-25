<?php

namespace App\Console\Commands;

use App\Models\CoinBlacklist;
use Illuminate\Console\Command;

class AnalyzeCoinBlacklist extends Command
{
    protected $signature = 'coins:analyze-blacklist';
    protected $description = 'Analyze coin performance and update blacklist automatically';

    public function handle(): int
    {
        $this->info('🔍 Analyzing coin performance...');

        $results = CoinBlacklist::analyzeAllCoins();

        if (empty($results)) {
            $this->info('✅ All coins performing well - no restrictions needed');
            return self::SUCCESS;
        }

        $this->warn('⚠️  Found ' . count($results) . ' coins with restrictions:');
        $this->newLine();

        foreach ($results as $entry) {
            $stats = $entry->performance_stats;
            $emoji = $entry->status === 'blacklisted' ? '🚫' : '⚠️';

            $this->line(sprintf(
                '%s %s [%s]',
                $emoji,
                $entry->symbol,
                $entry->status === 'blacklisted' ? 'BLACKLISTED' : 'HIGH CONFIDENCE REQUIRED'
            ));

            $this->line("   Reason: {$entry->reason}");
            $this->line(sprintf(
                "   Stats: %d trades, %.1f%% WR, $%.2f P&L",
                $stats['total_trades'] ?? 0,
                $stats['win_rate'] ?? 0,
                $stats['total_pnl'] ?? 0
            ));
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
