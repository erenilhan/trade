<?php

namespace App\Filament\Resources\TradeLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TradeLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('action')
                    ->options([
            'buy' => 'Buy',
            'sell' => 'Sell',
            'close_profitable' => 'Close profitable',
            'stop_loss' => 'Stop loss',
            'hold' => 'Hold',
            'error' => 'Error',
        ])
                    ->required(),
                Toggle::make('success')
                    ->required(),
                Textarea::make('message')
                    ->columnSpanFull(),
                TextInput::make('account_state'),
                TextInput::make('decision_data'),
                TextInput::make('result_data'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                DateTimePicker::make('executed_at')
                    ->required(),
            ]);
    }
}
