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
                    $results = CoinBlacklist::analyzeAllCoins();

                    if (empty($results)) {
                        Notification::make()
                            ->title('Analysis Complete')
                            ->body('All coins performing well - no restrictions needed')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Analysis Complete')
                            ->body(count($results) . ' coins with restrictions found')
                            ->warning()
                            ->send();
                    }

                })
                ->requiresConfirmation()
                ->modalHeading('Analyze Coin Performance')
                ->modalDescription('This will analyze all coins and automatically update blacklist based on performance.'),

            Actions\CreateAction::make()
                ->label('Add Coin Manually'),
        ];
    }
}
