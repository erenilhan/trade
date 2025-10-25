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

        CoinBlacklist::analyzeAllCoins();

        // Get all entries
        $allEntries = CoinBlacklist::orderBy('status', 'desc')->orderBy('symbol')->get();

        if ($allEntries->isEmpty()) {
            $this->info('✅ No coins found');
            return self::SUCCESS;
        }

        // Group by status
        $restricted = $allEntries->whereIn('status', ['blacklisted', 'high_confidence_only']);
        $active = $allEntries->where('status', 'active');

        // Show summary
        $this->info(sprintf('📊 Analyzed %d coins:', $allEntries->count()));
        $this->line(sprintf('   ✅ Active: %d', $active->count()));
        $this->line(sprintf('   ⚠️  Restricted: %d', $restricted->count()));
        $this->newLine();

        // Show restricted coins first
        if ($restricted->isNotEmpty()) {
            $this->warn('⚠️  Restricted Coins:');
            foreach ($restricted as $entry) {
                $stats = $entry->performance_stats;
                $emoji = $entry->status === 'blacklisted' ? '🚫' : '⚠️';

                $this->line(sprintf(
                    '%s %s [%s]',
                    $emoji,
                    $entry->symbol,
                    $entry->status === 'blacklisted' ? 'BLACKLISTED' : 'HIGH CONFIDENCE ONLY'
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
        }

        // Show active coins
        if ($active->isNotEmpty()) {
            $this->info('✅ Active Coins:');
            foreach ($active as $entry) {
                $stats = $entry->performance_stats;

                $this->line(sprintf(
                    '✅ %s [ACTIVE]',
                    $entry->symbol
                ));

                $this->line("   {$entry->reason}");
                $this->line(sprintf(
                    "   Stats: %d trades, %.1f%% WR, $%.2f P&L",
                    $stats['total_trades'] ?? 0,
                    $stats['win_rate'] ?? 0,
                    $stats['total_pnl'] ?? 0
                ));
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }
}
