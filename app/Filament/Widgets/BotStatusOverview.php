<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\Trade;
use App\Models\TradeLog;
use App\Models\AiLog;
use App\Models\BotSetting;

class BotStatusOverview extends Widget
{
    protected  string $view = 'filament.widgets.bot-status-overview';

    protected string|int|array $columnSpan = 2;

    public function getData(): array
    {
        // Get bot status
        $botStatus = BotSetting::get('bot_enabled', true) ? 'Active' : 'Inactive';

        // Get win rate
        $totalTrades = Trade::count();
        $successfulTrades = Trade::where('status', 'filled')->count();
        $winRate = $totalTrades > 0 ? round(($successfulTrades / $totalTrades) * 100, 2) : 0;

        // Get recent trade logs
        $recentLogs = TradeLog::recent(5)->get();

        return [
            'botStatus' => $botStatus,
            'winRate' => $winRate,
            'recentLogs' => $recentLogs,
        ];
    }
}
