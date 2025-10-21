<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Trade;

class RecentTradesChart extends ChartWidget
{
    protected  ?string $heading = 'Recent Trades';

    protected  ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // Get trades for the last 7 days
        $trades = Trade::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = $trades->pluck('date')->toArray();
        $counts = $trades->pluck('count')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Number of Trades',
                    'data' => $counts,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
