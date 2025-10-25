<?php

namespace App\Filament\Resources\CoinBlacklistResource\Pages;

use App\Filament\Resources\CoinBlacklistResource;
use App\Models\CoinBlacklist;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListCoinBlacklists extends ListRecords
{
    protected static string $resource = CoinBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analyze_all')
                ->label('Analyze All Coins')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->action(function () {
                    CoinBlacklist::analyzeAllCoins();

                    $all = CoinBlacklist::count();
                    $restricted = CoinBlacklist::whereIn('status', ['high_confidence_only', 'blacklisted'])->count();
                    $active = CoinBlacklist::where('status', 'active')->count();

                    Notification::make()
                        ->title('Analysis Complete')
                        ->body("Analyzed {$all} coins: {$active} active, {$restricted} restricted")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Analyze Coin Performance')
                ->modalDescription('This will analyze all coins with closed positions and update their status automatically.'),

            Actions\CreateAction::make()
                ->label('Add Coin Manually'),
        ];
    }
}
