<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Models\CoinBlacklist;
use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OptimizeStrategy extends Command
{
    protected $signature = 'strategy:optimize {--dry-run : Show what would be changed without applying}';
    protected $description = 'Automatically optimize trading strategy based on performance analytics';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔧 STRATEGY OPTIMIZATION STARTED');
        $this->info($dryRun ? '   (DRY RUN - No changes will be applied)' : '   (LIVE MODE - Changes will be applied)');
        $this->newLine();

        $actions = [];

        // 1. Analyze and update coin blacklist
        $actions = array_merge($actions, $this->optimizeCoinBlacklist($dryRun));

        // 2. Optimize AI confidence threshold
        $actions = array_merge($actions, $this->optimizeConfidenceThreshold($dryRun));

        // 3. Optimize position sizing based on coin performance
        $actions = array_merge($actions, $this->optimizePositionSizing($dryRun));

        // 4. Optimize leverage settings
        $actions = array_merge($actions, $this->optimizeLeverageSettings($dryRun));

        // Summary
        $this->newLine();
        if (empty($actions)) {
            $this->info('✅ No optimizations needed - strategy performing optimally');
        } else {
            $this->warn('📊 OPTIMIZATION SUMMARY:');
            foreach ($actions as $action) {
                $this->line('  • ' . $action);
            }

            if (!$dryRun) {
                Log::info('Strategy optimized automatically', ['actions' => $actions]);
            }
        }

        return self::SUCCESS;
    }

    private function optimizeCoinBlacklist(bool $dryRun): array
    {
        $this->info('1️⃣  Analyzing coin performance...');
        $actions = [];

        $coins = Position::select('symbol')->distinct()->pluck('symbol');

        foreach ($coins as $symbol) {
            $trades = Position::where('symbol', $symbol)->where('is_open', false)->get();

            if ($trades->count() < 3) {
                continue; // Need at least 3 trades
            }

            $wins = $trades->where('realized_pnl', '>', 0);
            $winRate = ($wins->count() / $trades->count()) * 100;
            $totalPnl = $trades->sum('realized_pnl');

            $current = CoinBlacklist::where('symbol', $symbol)->first();
            $currentStatus = $current?->status ?? 'none';

            // BLACKLIST if: Win rate < 25% AND 5+ trades AND negative P&L
            if ($winRate < 25 && $trades->count() >= 5 && $totalPnl < 0) {
                if ($currentStatus !== 'blacklisted') {
                    $action = "🚫 BLACKLIST {$symbol} ({$winRate}% WR, {$trades->count()} trades, $" . round($totalPnl, 2) . ")";
                    $this->warn('   ' . $action);
                    $actions[] = $action;

                    if (!$dryRun) {
                        CoinBlacklist::updateOrCreate(
                            ['symbol' => $symbol],
                            [
                                'status' => 'blacklisted',
                                'reason' => "Auto-optimized: Very poor performance {$winRate}% WR",
                                'min_confidence' => 0.70,
                                'auto_added' => true,
                            ]
                        );
                    }
                }
            }
            // HIGH CONFIDENCE if: Win rate < 35% AND 3+ trades
            elseif ($winRate < 35 && $trades->count() >= 3) {
                if ($currentStatus !== 'high_confidence_only' && $currentStatus !== 'blacklisted') {
                    $action = "⚠️  HIGH CONFIDENCE REQUIRED for {$symbol} ({$winRate}% WR, {$trades->count()} trades)";
                    $this->warn('   ' . $action);
                    $actions[] = $action;

                    if (!$dryRun) {
                        CoinBlacklist::updateOrCreate(
                            ['symbol' => $symbol],
                            [
                                'status' => 'high_confidence_only',
                                'reason' => "Auto-optimized: Low win rate {$winRate}%",
                                'min_confidence' => 0.80,
                                'auto_added' => true,
                            ]
                        );
                    }
                }
            }
            // PROMOTE TO ACTIVE if: Win rate >= 50% AND was restricted
            elseif ($winRate >= 50 && $trades->count() >= 5 && in_array($currentStatus, ['high_confidence_only', 'blacklisted'])) {
                $action = "✅ PROMOTE {$symbol} to ACTIVE ({$winRate}% WR, {$trades->count()} trades)";
                $this->info('   ' . $action);
                $actions[] = $action;

                if (!$dryRun) {
                    CoinBlacklist::updateOrCreate(
                        ['symbol' => $symbol],
                        [
                            'status' => 'active',
                            'reason' => "Auto-optimized: Performance improved to {$winRate}% WR",
                            'min_confidence' => 0.70,
                            'auto_added' => true,
                        ]
                    );
                }
            }
        }

        return $actions;
    }

    private function optimizeConfidenceThreshold(bool $dryRun): array
    {
        $this->info('2️⃣  Analyzing AI confidence correlation...');
        $actions = [];

        $aiTrades = Position::where('is_open', false)->whereNotNull('confidence')->get();

        if ($aiTrades->count() < 10) {
            $this->line('   ⏭️  Insufficient data (need 10+ AI trades)');
            return [];
        }

        // Analyze different confidence ranges
        $highConf = $aiTrades->where('confidence', '>=', 0.80);
        $medConf = $aiTrades->where('confidence', '>=', 0.70)->where('confidence', '<', 0.80);
        $lowConf = $aiTrades->where('confidence', '<', 0.70);

        $highWR = $highConf->count() > 0 ? ($highConf->where('realized_pnl', '>', 0)->count() / $highConf->count()) * 100 : 0;
        $medWR = $medConf->count() > 0 ? ($medConf->where('realized_pnl', '>', 0)->count() / $medConf->count()) * 100 : 0;
        $lowWR = $lowConf->count() > 0 ? ($lowConf->where('realized_pnl', '>', 0)->count() / $lowConf->count()) * 100 : 0;

        $currentMinConf = BotSetting::get('min_confidence', 0.70);

        // If low confidence performs better, lower threshold
        if ($lowConf->count() >= 5 && $lowWR > $medWR + 10) {
            $newThreshold = 0.65;
            if ($newThreshold != $currentMinConf) {
                $action = "🎯 LOWER min confidence threshold: {$currentMinConf} → {$newThreshold} (Low conf WR: {$lowWR}% vs Med: {$medWR}%)";
                $this->warn('   ' . $action);
                $actions[] = $action;

                if (!$dryRun) {
                    BotSetting::set('min_confidence', $newThreshold);
                }
            }
        }
        // If high confidence performs significantly better, raise threshold
        elseif ($highConf->count() >= 5 && $highWR > $medWR + 15) {
            $newThreshold = 0.75;
            if ($newThreshold != $currentMinConf) {
                $action = "🎯 RAISE min confidence threshold: {$currentMinConf} → {$newThreshold} (High conf WR: {$highWR}% vs Med: {$medWR}%)";
                $this->info('   ' . $action);
                $actions[] = $action;

                if (!$dryRun) {
                    BotSetting::set('min_confidence', $newThreshold);
                }
            }
        } else {
            $this->line("   ✓ Confidence threshold optimal ({$currentMinConf})");
        }

        return $actions;
    }

    private function optimizePositionSizing(bool $dryRun): array
    {
        $this->info('3️⃣  Analyzing position sizing...');
        $actions = [];

        $closed = Position::where('is_open', false)->get();

        if ($closed->count() < 10) {
            $this->line('   ⏭️  Insufficient data');
            return [];
        }

        $wins = $closed->where('realized_pnl', '>', 0);
        $losses = $closed->where('realized_pnl', '<', 0);

        $avgWin = $wins->count() > 0 ? $wins->avg('realized_pnl') : 0;
        $avgLoss = $losses->count() > 0 ? abs($losses->avg('realized_pnl')) : 0;

        $currentSize = BotSetting::get('position_size_usdt', 100);

        // If average loss > average win * 1.5, reduce position size
        if ($avgLoss > $avgWin * 1.5 && $currentSize > 50) {
            $newSize = max(50, $currentSize * 0.8);
            $action = "💵 REDUCE position size: \${$currentSize} → \${$newSize} (Avg loss too high: \${$avgLoss} vs win \${$avgWin})";
            $this->warn('   ' . $action);
            $actions[] = $action;

            if (!$dryRun) {
                BotSetting::set('position_size_usdt', $newSize);
            }
        }
        // If win rate > 55% and profit factor > 2, can increase size
        elseif ($closed->count() >= 20) {
            $winRate = ($wins->count() / $closed->count()) * 100;
            $profitFactor = $avgLoss > 0 ? $avgWin / $avgLoss : 0;

            if ($winRate > 55 && $profitFactor > 2 && $currentSize < 200) {
                $newSize = min(200, $currentSize * 1.2);
                $action = "💵 INCREASE position size: \${$currentSize} → \${$newSize} (Strong performance: {$winRate}% WR, PF: " . round($profitFactor, 2) . ")";
                $this->info('   ' . $action);
                $actions[] = $action;

                if (!$dryRun) {
                    BotSetting::set('position_size_usdt', $newSize);
                }
            } else {
                $this->line("   ✓ Position size optimal (\${$currentSize})");
            }
        }

        return $actions;
    }

    private function optimizeLeverageSettings(bool $dryRun): array
    {
        $this->info('4️⃣  Analyzing leverage performance...');
        $actions = [];

        $trades = Position::where('is_open', false)->get();

        if ($trades->count() < 15) {
            $this->line('   ⏭️  Insufficient data');
            return [];
        }

        $leverages = $trades->pluck('leverage')->unique();

        foreach ($leverages as $lev) {
            $levTrades = $trades->where('leverage', $lev);
            if ($levTrades->count() < 5) continue;

            $wins = $levTrades->where('realized_pnl', '>', 0);
            $wr = ($wins->count() / $levTrades->count()) * 100;

            $this->line("   {$lev}x leverage: {$levTrades->count()} trades, {$wr}% WR");
        }

        $maxLeverage = BotSetting::get('max_leverage', 10);
        $this->line("   ✓ Max leverage: {$maxLeverage}x (AI will choose 2-{$maxLeverage}x dynamically)");

        return $actions;
    }
}
