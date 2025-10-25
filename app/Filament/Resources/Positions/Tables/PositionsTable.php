<?php

namespace App\Filament\Resources\Positions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
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
                BadgeColumn::make('close_reason')
                    ->label('Exit Reason')
                    ->colors([
                        'success' => 'take_profit',
                        'danger' => 'stop_loss',
                        'warning' => ['trailing_stop_l1', 'trailing_stop_l2'],
                        'info' => ['trailing_stop_l3', 'trailing_stop_l4'],
                        'gray' => ['manual', 'other'],
                        'danger' => 'liquidated',
                    ])
                    ->icons([
                        'take_profit' => 'heroicon-o-check-circle',
                        'stop_loss' => 'heroicon-o-x-circle',
                        'trailing_stop_l1' => 'heroicon-o-arrow-trending-down',
                        'trailing_stop_l2' => 'heroicon-o-arrow-trending-down',
                        'trailing_stop_l3' => 'heroicon-o-arrow-trending-up',
                        'trailing_stop_l4' => 'heroicon-o-arrow-trending-up',
                        'manual' => 'heroicon-o-hand-raised',
                        'liquidated' => 'heroicon-o-exclamation-triangle',
                        'other' => 'heroicon-o-question-mark-circle',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'take_profit' => 'Take Profit',
                        'stop_loss' => 'Stop Loss',
                        'trailing_stop_l1' => 'Trailing Stop L1',
                        'trailing_stop_l2' => 'Trailing Stop L2',
                        'trailing_stop_l3' => 'Trailing Stop L3',
                        'trailing_stop_l4' => 'Trailing Stop L4',
                        'manual' => 'Manual',
                        'liquidated' => 'Liquidated',
                        'other' => 'Other',
                        default => '-',
                    })
                    ->placeholder('-')
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
