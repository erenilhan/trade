<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Position;
use App\Models\Trade;
use App\Models\TradeLog;
use App\Models\AiLog;
use App\Models\BotSetting;

class TradingOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Get account balance information
        $positions = Position::open()->get();
        $activePositions = $positions->count();
        
        // Calculate total position value
        $totalPositionValue = $positions->sum(function ($position) {
            return abs($position->quantity * $position->current_price);
        });
        
        // Get recent trades
        $recentTrades = Trade::recent(10)->get();
        $tradeCount = $recentTrades->count();
        
        // Get AI logs count
        $aiLogsCount = AiLog::count();
        
        // Get bot status
        $botStatus = BotSetting::get('bot_enabled', true) ? 'Active' : 'Inactive';
        
        // Get win rate
        $totalTrades = Trade::count();
        $successfulTrades = Trade::where('status', 'filled')->count();
        $winRate = $totalTrades > 0 ? round(($successfulTrades / $totalTrades) * 100, 2) : 0;

        return [
            Stat::make('Active Positions', $activePositions)
                ->description('Currently open positions')
                ->color('primary'),
            Stat::make('Position Value', '$' . number_format($totalPositionValue, 2))
                ->description('Total value of open positions')
                ->color('success'),
            Stat::make('Recent Trades', $tradeCount)
                ->description('Trades in the last 10 entries')
                ->color('warning'),
            Stat::make('AI Logs', $aiLogsCount)
                ->description('Total AI decision logs')
                ->color('info'),
        ];
    }
}