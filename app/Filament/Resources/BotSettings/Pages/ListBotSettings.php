<?php

namespace App\Filament\Resources\BotSettings\Pages;

use App\Filament\Resources\BotSettings\BotSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotSettings extends ListRecords
{
    protected static string $resource = BotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
