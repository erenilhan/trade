<?php

namespace App\Filament\Resources\TradeLogs\Pages;

use App\Filament\Resources\TradeLogs\TradeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTradeLogs extends ListRecords
{
    protected static string $resource = TradeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action allowed
        ];
    }
}
