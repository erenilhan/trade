<?php

namespace App\Filament\Resources\AiLogs\Pages;

use App\Filament\Resources\AiLogs\AiLogResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAiLog extends ViewRecord
{
    protected static string $resource = AiLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('AI Log Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('provider')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextEntry::make('created_at'),
                        TextEntry::make('updated_at'),
                    ]),
                Section::make('Prompt')
                    ->schema([
                        Forms\Components\Textarea::make('prompt')
                            ->required()
                            ->rows(15)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('Response')
                    ->schema([
                        Forms\Components\Textarea::make('response')
                            ->required()
                            ->rows(15)
                            ->formatStateUsing(fn ($record) => json_encode(json_decode($record->response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('Decision')
                    ->schema([
                        Forms\Components\Textarea::make('decision_display')
//                            ->content(fn ($record) => json_encode($record->decision, JSON_PRETTY_PRINT))
                            ->rows(15)
                            ->columnSpanFull()
                            ->disabled()
                            ->formatStateUsing(function ($record) {
                                return json_encode($record->decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            }),
                    ])
                    ->collapsible(),
            ])
            ->disabled();
    }
}
