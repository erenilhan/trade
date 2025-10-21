<?php

namespace App\Filament\Resources\Trades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_id')
                    ->required(),
                TextInput::make('symbol')
                    ->required(),
                Select::make('side')
                    ->options(['buy' => 'Buy', 'sell' => 'Sell'])
                    ->required(),
                Select::make('type')
                    ->options(['market' => 'Market', 'limit' => 'Limit'])
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('cost')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('leverage')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('stop_loss')
                    ->numeric(),
                TextInput::make('take_profit')
                    ->numeric(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'filled' => 'Filled', 'cancelled' => 'Cancelled', 'failed' => 'Failed'])
                    ->default('pending')
                    ->required(),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                TextInput::make('response_data'),
            ]);
    }
}
