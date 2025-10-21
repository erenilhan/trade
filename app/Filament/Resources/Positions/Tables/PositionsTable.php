<?php

namespace App\Filament\Resources\Positions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')
                    ->searchable(),
                TextColumn::make('side')
                    ->badge(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entry_price')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('current_price')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('liquidation_price')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unrealized_pnl')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('realized_pnl')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('leverage')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('notional_usd')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sl_order_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tp_order_id')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_open')
                    ->boolean(),
                TextColumn::make('opened_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
