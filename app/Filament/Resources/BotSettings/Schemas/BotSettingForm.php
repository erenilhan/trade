<?php

namespace App\Filament\Resources\BotSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class BotSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->label('Setting Key')
                    ->helperText('Common keys: initial_capital, max_leverage, position_size_usdt, bot_enabled'),

                TextInput::make('value')
                    ->required()
                    ->label('Value')
                    ->helperText('Enter value based on type'),

                Select::make('type')
                    ->required()
                    ->label('Data Type')
                    ->default('string')
                    ->options([
                        'string' => 'String',
                        'int' => 'Integer',
                        'float' => 'Float/Decimal',
                        'bool' => 'Boolean (true/false)',
                        'json' => 'JSON Array',
                    ])
                    ->helperText('Select appropriate data type for casting'),

                Textarea::make('description')
                    ->label('Description')
                    ->placeholder('What does this setting control?')
                    ->columnSpanFull(),
            ]);
    }
}
