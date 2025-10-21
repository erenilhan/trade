<?php

namespace App\Filament\Resources\BotSettings\Pages;

use App\Filament\Resources\BotSettings\BotSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBotSetting extends EditRecord
{
    protected static string $resource = BotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
