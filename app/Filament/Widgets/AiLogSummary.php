<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\AiLog;
use Carbon\Carbon;

class AiLogSummary extends BaseWidget
{
    protected function getStats(): array
    {
        $todayLogs = AiLog::whereDate('created_at', Carbon::today())->count();
        $weekLogs = AiLog::whereDate('created_at', '>=', Carbon::now()->subWeek())->count();
        $totalLogs = AiLog::count();

        return [
            Stat::make('Today\'s AI Requests', $todayLogs)
                ->description('AI requests today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
            Stat::make('This Week', $weekLogs)
                ->description('AI requests this week')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
            Stat::make('Total AI Logs', $totalLogs)
                ->description('All time AI logs')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
        ];
    }
}