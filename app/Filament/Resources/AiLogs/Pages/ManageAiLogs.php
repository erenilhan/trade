<?php

namespace App\Filament\Resources\AiLogs\Pages;

use App\Filament\Resources\AiLogs\AiLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAiLogs extends ManageRecords
{
    protected static string $resource = AiLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action allowed
        ];
    }
}
