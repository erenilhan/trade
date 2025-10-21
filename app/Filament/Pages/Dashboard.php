<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets;
use App\Filament\Widgets\TradingOverview;
use App\Filament\Widgets\BotStatusOverview;
use App\Filament\Widgets\RecentTradesChart;
use App\Filament\Widgets\AiLogSummary;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            TradingOverview::class,
            BotStatusOverview::class,
            RecentTradesChart::class,
            AiLogSummary::class,
            Widgets\AccountWidget::class,
            Widgets\FilamentInfoWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
