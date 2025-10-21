<?php

namespace App\Filament\Resources\Positions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('symbol')
                    ->required(),
                Select::make('side')
                    ->options(['long' => 'Long', 'short' => 'Short'])
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('entry_price')
                    ->required()
                    ->numeric(),
                TextInput::make('current_price')
                    ->required()
                    ->numeric(),
                TextInput::make('liquidation_price')
                    ->numeric(),
                TextInput::make('unrealized_pnl')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('realized_pnl')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('leverage')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('notional_usd')
                    ->required()
                    ->numeric(),
                TextInput::make('sl_order_id')
                    ->numeric(),
                TextInput::make('tp_order_id')
                    ->numeric(),
                Toggle::make('is_open')
                    ->required(),
                DateTimePicker::make('opened_at')
                    ->required(),
                DateTimePicker::make('closed_at'),
            ]);
    }
}
