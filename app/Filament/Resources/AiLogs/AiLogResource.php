<?php

namespace App\Filament\Resources\AiLogs;

use App\Filament\Resources\AiLogs\Pages\ManageAiLogs;
use App\Models\AiLog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

use Filament\Forms;
use Filament\Tables;

class AiLogResource extends Resource
{
    protected static ?string $model = AiLog::class;
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'AI Monitoring';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('provider')
                    ->required()
                    ->maxLength(255)
                    ->readOnly(),
                Forms\Components\Textarea::make('prompt')
                    ->required()
                    ->rows(15)
                    ->columnSpanFull()
                    ->readOnly(),
                Forms\Components\Textarea::make('response')
                    ->required()
                    ->rows(15)
                    ->columnSpanFull()
                    ->readOnly(),
                Forms\Components\Textarea::make('decision')
                    ->required()
                    ->rows(10)
                    ->json()
                    ->columnSpanFull()
                    ->readOnly(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('provider')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiLogs::route('/'),
            'view' => \App\Filament\Resources\AiLogs\Pages\ViewAiLog::route('/{record}'),
        ];
    }
}
