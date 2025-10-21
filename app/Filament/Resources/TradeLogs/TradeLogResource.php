<?php

namespace App\Filament\Resources\TradeLogs;

use App\Filament\Resources\TradeLogs\Pages\CreateTradeLog;
use App\Filament\Resources\TradeLogs\Pages\EditTradeLog;
use App\Filament\Resources\TradeLogs\Pages\ListTradeLogs;
use App\Filament\Resources\TradeLogs\Schemas\TradeLogForm;
use App\Filament\Resources\TradeLogs\Tables\TradeLogsTable;
use App\Models\TradeLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TradeLogResource extends Resource
{
    protected static ?string $model = TradeLog::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Trading';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocument;

    public static function form(Schema $schema): Schema
    {
        return TradeLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TradeLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTradeLogs::route('/'),
        ];
    }
}
