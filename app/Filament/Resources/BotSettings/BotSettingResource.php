<?php

namespace App\Filament\Resources\BotSettings;

use App\Filament\Resources\BotSettings\Pages\CreateBotSetting;
use App\Filament\Resources\BotSettings\Pages\EditBotSetting;
use App\Filament\Resources\BotSettings\Pages\ListBotSettings;
use App\Filament\Resources\BotSettings\Schemas\BotSettingForm;
use App\Filament\Resources\BotSettings\Tables\BotSettingsTable;
use App\Models\BotSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BotSettingResource extends Resource
{
    protected static ?string $model = BotSetting::class;

    // Hide from navigation - use ManageBotSettings page instead
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ComputerDesktop;

    public static function form(Schema $schema): Schema
    {
        return BotSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotSettingsTable::configure($table);
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
            'index' => ListBotSettings::route('/'),
            'create' => CreateBotSetting::route('/create'),
            'edit' => EditBotSetting::route('/{record}/edit'),
        ];
    }
}
