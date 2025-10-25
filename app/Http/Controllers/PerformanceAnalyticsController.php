<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PerformanceAnalyticsController extends Controller
{
    /**
     * Show analytics dashboard
     */
    public function index()
    {
        return view('performance_analytics');
    }

    /**
     * Get comprehensive analytics data
     */
    public function getData()
    {
        $closedPositions = Position::where('is_open', false)->get();

        if ($closedPositions->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'overall' => [],
                    'message' => 'No closed positions yet'
                ]
            ]);
        }

        $data = [
            'overall' => $this->getOverallStats($closedPositions),
            'close_reasons' => $this->getCloseReasonAnalysis($closedPositions),
            'ai_confidence' => $this->getAIConfidenceAnalysis($closedPositions),
            'coin_performance' => $this->getCoinPerformance(),
            'leverage_analysis' => $this->getLeverageAnalysis($closedPositions),
            'best_worst_trades' => $this->getBestWorstTrades(),
            'timeline' => $this->getTimelineAnalysis($closedPositions),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Overall statistics
     */
    private function getOverallStats($positions)
    {
        $wins = $positions->where('realized_pnl', '>', 0);
        $losses = $positions->where('realized_pnl', '<', 0);

        $totalWinAmount = $wins->sum('realized_pnl');
        $totalLossAmount = abs($losses->sum('realized_pnl'));
        $profitFactor = $totalLossAmount > 0 ? $totalWinAmount / $totalLossAmount : 0;

        return [
            'total_trades' => $positions->count(),
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'win_rate' => round(($wins->count() / $positions->count()) * 100, 2),
            'total_pnl' => round($positions->sum('realized_pnl'), 2),
            'avg_win' => $wins->count() > 0 ? round($wins->avg('realized_pnl'), 2) : 0,
            'avg_loss' => $losses->count() > 0 ? round($losses->avg('realized_pnl'), 2) : 0,
            'largest_win' => round($wins->max('realized_pnl') ?? 0, 2),
            'largest_loss' => round($losses->min('realized_pnl') ?? 0, 2),
            'profit_factor' => round($profitFactor, 2),
            'total_win_amount' => round($totalWinAmount, 2),
            'total_loss_amount' => round($totalLossAmount, 2),
        ];
    }

    /**
     * Close reason breakdown
     */
    private function getCloseReasonAnalysis($positions)
    {
        $withReasons = $positions->whereNotNull('close_reason');
        $withoutReasons = $positions->whereNull('close_reason');

        $breakdown = [];

        // Group by close_reason
        $grouped = $withReasons->groupBy('close_reason');

        foreach ($grouped as $reason => $trades) {
            $tradeWins = $trades->where('realized_pnl', '>', 0);
            $breakdown[] = [
                'reason' => $reason,
                'count' => $trades->count(),
                'wins' => $tradeWins->count(),
                'losses' => $trades->count() - $tradeWins->count(),
                'win_rate' => round(($tradeWins->count() / $trades->count()) * 100, 1),
                'total_pnl' => round($trades->sum('realized_pnl'), 2),
                'avg_pnl' => round($trades->avg('realized_pnl'), 2),
            ];
        }

        // Add "no reason" category for old trades
        if ($withoutReasons->count() > 0) {
            $noReasonWins = $withoutReasons->where('realized_pnl', '>', 0);
            $breakdown[] = [
                'reason' => 'unknown',
                'count' => $withoutReasons->count(),
                'wins' => $noReasonWins->count(),
                'losses' => $withoutReasons->count() - $noReasonWins->count(),
                'win_rate' => round(($noReasonWins->count() / $withoutReasons->count()) * 100, 1),
                'total_pnl' => round($withoutReasons->sum('realized_pnl'), 2),
                'avg_pnl' => round($withoutReasons->avg('realized_pnl'), 2),
            ];
        }

        // Sort by count descending
        usort($breakdown, fn($a, $b) => $b['count'] - $a['count']);

        return $breakdown;
    }

    /**
     * AI Confidence analysis
     */
    private function getAIConfidenceAnalysis($positions)
    {
        $aiTrades = $positions->whereNotNull('confidence');

        $ranges = [
            ['min' => 0.85, 'max' => 1.00, 'label' => 'Very High (85%+)'],
            ['min' => 0.80, 'max' => 0.85, 'label' => 'High (80-84%)'],
            ['min' => 0.75, 'max' => 0.80, 'label' => 'Med-High (75-79%)'],
            ['min' => 0.70, 'max' => 0.75, 'label' => 'Medium (70-74%)'],
            ['min' => 0.60, 'max' => 0.70, 'label' => 'Low (60-69%)'],
            ['min' => 0.00, 'max' => 0.60, 'label' => 'Very Low (<60%)'],
        ];

        $analysis = [];

        foreach ($ranges as $range) {
            $trades = $aiTrades->where('confidence', '>=', $range['min'])
                              ->where('confidence', '<', $range['max']);

            if ($trades->count() > 0) {
                $wins = $trades->where('realized_pnl', '>', 0);
                $analysis[] = [
                    'range' => $range['label'],
                    'min' => $range['min'],
                    'max' => $range['max'],
                    'count' => $trades->count(),
                    'wins' => $wins->count(),
                    'losses' => $trades->count() - $wins->count(),
                    'win_rate' => round(($wins->count() / $trades->count()) * 100, 1),
                    'avg_confidence' => round($trades->avg('confidence') * 100, 1),
                    'total_pnl' => round($trades->sum('realized_pnl'), 2),
                    'avg_pnl' => round($trades->avg('realized_pnl'), 2),
                ];
            }
        }

        // Overall AI stats
        $aiWins = $aiTrades->where('realized_pnl', '>', 0);
        $aiLosses = $aiTrades->where('realized_pnl', '<', 0);

        return [
            'by_range' => $analysis,
            'overall' => [
                'total_ai_trades' => $aiTrades->count(),
                'win_rate' => $aiTrades->count() > 0 ? round(($aiWins->count() / $aiTrades->count()) * 100, 2) : 0,
                'avg_confidence_wins' => $aiWins->count() > 0 ? round($aiWins->avg('confidence') * 100, 1) : 0,
                'avg_confidence_losses' => $aiLosses->count() > 0 ? round($aiLosses->avg('confidence') * 100, 1) : 0,
                'correlation' => $aiWins->count() > 0 && $aiLosses->count() > 0
                    ? round(($aiWins->avg('confidence') - $aiLosses->avg('confidence')) * 100, 1)
                    : 0,
            ]
        ];
    }

    /**
     * Coin performance comparison
     */
    private function getCoinPerformance()
    {
        $coins = Position::where('is_open', false)
            ->select('symbol')
            ->distinct()
            ->pluck('symbol');

        $performance = [];

        foreach ($coins as $symbol) {
            $trades = Position::where('symbol', $symbol)->where('is_open', false)->get();
            $wins = $trades->where('realized_pnl', '>', 0);
            $losses = $trades->where('realized_pnl', '<', 0);

            $totalWin = $wins->sum('realized_pnl');
            $totalLoss = abs($losses->sum('realized_pnl'));

            $performance[] = [
                'symbol' => $symbol,
                'trades' => $trades->count(),
                'wins' => $wins->count(),
                'losses' => $losses->count(),
                'win_rate' => $trades->count() > 0 ? round(($wins->count() / $trades->count()) * 100, 1) : 0,
                'total_pnl' => round($trades->sum('realized_pnl'), 2),
                'avg_pnl' => round($trades->avg('realized_pnl'), 2),
                'profit_factor' => $totalLoss > 0 ? round($totalWin / $totalLoss, 2) : 0,
                'avg_confidence' => $trades->whereNotNull('confidence')->count() > 0
                    ? round($trades->avg('confidence') * 100, 1)
                    : null,
            ];
        }

        // Sort by total PnL descending
        usort($performance, fn($a, $b) => $b['total_pnl'] <=> $a['total_pnl']);

        return $performance;
    }

    /**
     * Leverage analysis
     */
    private function getLeverageAnalysis($positions)
    {
        $byLeverage = [];

        $leverages = $positions->pluck('leverage')->unique()->sort();

        foreach ($leverages as $lev) {
            $trades = $positions->where('leverage', $lev);
            $wins = $trades->where('realized_pnl', '>', 0);

            $byLeverage[] = [
                'leverage' => $lev . 'x',
                'count' => $trades->count(),
                'wins' => $wins->count(),
                'losses' => $trades->count() - $wins->count(),
                'win_rate' => round(($wins->count() / $trades->count()) * 100, 1),
                'total_pnl' => round($trades->sum('realized_pnl'), 2),
                'avg_pnl' => round($trades->avg('realized_pnl'), 2),
            ];
        }

        return $byLeverage;
    }

    /**
     * Best and worst trades
     */
    private function getBestWorstTrades()
    {
        $best = Position::where('is_open', false)
            ->orderBy('realized_pnl', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($p) => $this->formatTrade($p));

        $worst = Position::where('is_open', false)
            ->orderBy('realized_pnl', 'asc')
            ->limit(5)
            ->get()
            ->map(fn($p) => $this->formatTrade($p));

        return [
            'best' => $best,
            'worst' => $worst,
        ];
    }

    /**
     * Timeline analysis (by week/month)
     */
    private function getTimelineAnalysis($positions)
    {
        $byWeek = $positions->groupBy(function($trade) {
            return $trade->closed_at?->format('Y-W') ?? 'unknown';
        })->map(function($trades, $week) {
            $wins = $trades->where('realized_pnl', '>', 0);
            return [
                'week' => $week,
                'trades' => $trades->count(),
                'wins' => $wins->count(),
                'win_rate' => round(($wins->count() / $trades->count()) * 100, 1),
                'pnl' => round($trades->sum('realized_pnl'), 2),
            ];
        })->values();

        return $byWeek;
    }

    /**
     * Format trade for display
     */
    private function formatTrade($position)
    {
        $pnlPercent = 0;
        if ($position->entry_price > 0) {
            $exitPrice = $position->current_price;
            $priceDiff = $exitPrice - $position->entry_price;
            if ($position->side === 'short') {
                $priceDiff = -$priceDiff;
            }
            $pnlPercent = ($priceDiff / $position->entry_price) * 100 * $position->leverage;
        }

        return [
            'id' => $position->id,
            'symbol' => $position->symbol,
            'side' => $position->side,
            'leverage' => $position->leverage,
            'entry_price' => round($position->entry_price, 2),
            'exit_price' => round($position->current_price, 2),
            'pnl' => round($position->realized_pnl, 2),
            'pnl_percent' => round($pnlPercent, 2),
            'confidence' => $position->confidence ? round($position->confidence * 100, 1) : null,
            'close_reason' => $position->close_reason,
            'closed_at' => $position->closed_at?->format('Y-m-d H:i'),
        ];
    }
}
