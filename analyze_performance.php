<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Position;
use App\Models\BotSetting;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              📊 TRADING BOT PERFORMANCE ANALYSIS              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Get all positions
$closed = Position::where('is_open', false)->get();
$open = Position::where('is_open', true)->get();

// Overall stats
echo "═══ GENEL DURUM ═══\n";
echo "Closed Positions: " . $closed->count() . "\n";
echo "Open Positions: " . $open->count() . "\n";
echo "\n";

// P&L Analysis
$totalPnl = $closed->sum('realized_pnl');
$wins = $closed->where('realized_pnl', '>', 0);
$losses = $closed->where('realized_pnl', '<', 0);
$winRate = $closed->count() > 0 ? ($wins->count() / $closed->count()) * 100 : 0;

echo "═══ KAR/ZARAR ANALİZİ ═══\n";
echo "Total Realized P&L: $" . number_format($totalPnl, 2) . "\n";
echo "Wins: " . $wins->count() . " | Losses: " . $losses->count() . "\n";
echo "Win Rate: " . number_format($winRate, 2) . "%\n";

$avgWin = $wins->count() > 0 ? $wins->avg('realized_pnl') : 0;
$avgLoss = $losses->count() > 0 ? abs($losses->avg('realized_pnl')) : 0;
$totalWinAmount = $wins->sum('realized_pnl');
$totalLossAmount = abs($losses->sum('realized_pnl'));

echo "Avg Win: $" . number_format($avgWin, 2) . " | Avg Loss: $" . number_format($avgLoss, 2) . "\n";
echo "Total Win Amount: $" . number_format($totalWinAmount, 2) . "\n";
echo "Total Loss Amount: $" . number_format($totalLossAmount, 2) . "\n";

$profitFactor = $totalLossAmount > 0 ? $totalWinAmount / $totalLossAmount : 0;
echo "Profit Factor: " . number_format($profitFactor, 2) . "\n";
echo "\n";

// Coin performance
echo "═══ COIN PERFORMANSI ═══\n";
$coinStats = [];
foreach ($closed as $pos) {
    $symbol = $pos->symbol;
    if (!isset($coinStats[$symbol])) {
        $coinStats[$symbol] = [
            'trades' => 0,
            'wins' => 0,
            'losses' => 0,
            'pnl' => 0,
        ];
    }
    $coinStats[$symbol]['trades']++;
    $coinStats[$symbol]['pnl'] += $pos->realized_pnl;
    if ($pos->realized_pnl > 0) {
        $coinStats[$symbol]['wins']++;
    } else {
        $coinStats[$symbol]['losses']++;
    }
}

uasort($coinStats, function($a, $b) {
    return $b['pnl'] <=> $a['pnl'];
});

foreach ($coinStats as $symbol => $stats) {
    $winRate = $stats['trades'] > 0 ? ($stats['wins'] / $stats['trades']) * 100 : 0;
    echo sprintf(
        "%s: %d trades, %.0f%% WR, $%s P&L\n",
        str_pad($symbol, 10),
        $stats['trades'],
        $winRate,
        number_format($stats['pnl'], 2)
    );
}
echo "\n";

// Leverage analysis
echo "═══ LEVERAGE ANALİZİ ═══\n";
$leverageStats = [];
foreach ($closed as $pos) {
    $lev = $pos->leverage ?? 2;
    if (!isset($leverageStats[$lev])) {
        $leverageStats[$lev] = [
            'trades' => 0,
            'wins' => 0,
            'pnl' => 0,
        ];
    }
    $leverageStats[$lev]['trades']++;
    $leverageStats[$lev]['pnl'] += $pos->realized_pnl;
    if ($pos->realized_pnl > 0) {
        $leverageStats[$lev]['wins']++;
    }
}

ksort($leverageStats);

foreach ($leverageStats as $lev => $stats) {
    $winRate = $stats['trades'] > 0 ? ($stats['wins'] / $stats['trades']) * 100 : 0;
    echo sprintf(
        "%dx: %d trades, %.0f%% WR, $%s P&L\n",
        $lev,
        $stats['trades'],
        $winRate,
        number_format($stats['pnl'], 2)
    );
}
echo "\n";

// AI Confidence vs Performance
echo "═══ AI CONFIDENCE ANALİZİ ═══\n";
$withConfidence = $closed->whereNotNull('confidence');
if ($withConfidence->count() > 0) {
    $highConf = $withConfidence->where('confidence', '>=', 0.80);
    $medConf = $withConfidence->where('confidence', '>=', 0.70)->where('confidence', '<', 0.80);
    $lowConf = $withConfidence->where('confidence', '<', 0.70);

    echo "High Confidence (≥80%): " . $highConf->count() . " trades\n";
    if ($highConf->count() > 0) {
        $winRate = ($highConf->where('realized_pnl', '>', 0)->count() / $highConf->count()) * 100;
        echo "  Win Rate: " . number_format($winRate, 2) . "%\n";
        echo "  Avg P&L: $" . number_format($highConf->avg('realized_pnl'), 2) . "\n";
    }

    echo "Medium Confidence (70-79%): " . $medConf->count() . " trades\n";
    if ($medConf->count() > 0) {
        $winRate = ($medConf->where('realized_pnl', '>', 0)->count() / $medConf->count()) * 100;
        echo "  Win Rate: " . number_format($winRate, 2) . "%\n";
        echo "  Avg P&L: $" . number_format($medConf->avg('realized_pnl'), 2) . "\n";
    }

    echo "Low Confidence (<70%): " . $lowConf->count() . " trades\n";
    if ($lowConf->count() > 0) {
        $winRate = ($lowConf->where('realized_pnl', '>', 0)->count() / $lowConf->count()) * 100;
        echo "  Win Rate: " . number_format($winRate, 2) . "%\n";
        echo "  Avg P&L: $" . number_format($lowConf->avg('realized_pnl'), 2) . "\n";
    }
}
echo "\n";

// Open positions analysis
if ($open->count() > 0) {
    echo "═══ AÇIK POZİSYONLAR ═══\n";
    $totalUnrealized = 0;
    foreach ($open as $pos) {
        $pnl = $pos->unrealized_pnl ?? 0;
        $totalUnrealized += $pnl;
        $emoji = $pnl >= 0 ? '🟢' : '🔴';
        echo sprintf(
            "%s %s: $%s (%s%%)\n",
            $emoji,
            $pos->symbol,
            number_format($pnl, 2),
            number_format(($pnl / ($pos->quantity * $pos->entry_price)) * 100, 2)
        );
    }
    echo "Total Unrealized P&L: $" . number_format($totalUnrealized, 2) . "\n";
    echo "\n";
}

// Recent performance (last 10 trades)
echo "═══ SON 10 TRADE ═══\n";
$recent = Position::where('is_open', false)
    ->orderBy('closed_at', 'desc')
    ->limit(10)
    ->get();

foreach ($recent as $pos) {
    $emoji = $pos->realized_pnl >= 0 ? '✅' : '❌';
    echo sprintf(
        "%s %s: $%s (%s)\n",
        $emoji,
        str_pad($pos->symbol, 10),
        str_pad(number_format($pos->realized_pnl, 2), 8),
        $pos->closed_at->format('Y-m-d H:i')
    );
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";
