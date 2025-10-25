<?php

namespace App\Filament\Resources\CoinBlacklistResource\Pages;

use App\Filament\Resources\CoinBlacklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoinBlacklist extends EditRecord
{
    protected static string $resource = CoinBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
