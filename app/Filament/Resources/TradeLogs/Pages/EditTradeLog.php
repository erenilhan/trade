<?php

namespace App\Filament\Resources\TradeLogs\Pages;

use App\Filament\Resources\TradeLogs\TradeLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTradeLog extends EditRecord
{
    protected static string $resource = TradeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
