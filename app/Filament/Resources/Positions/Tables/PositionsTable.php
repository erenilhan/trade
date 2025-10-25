<?php

namespace App\Filament\Resources\Positions\Tables;

use App\Models\Position;
use App\Services\TradingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')
                    ->label('Symbol')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('side')
                    ->badge(),

                TextColumn::make('unrealized_pnl')
                    ->label('PNL')
                    ->numeric()
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('notional_usd')
                    ->label('Size (USD)')
                    ->numeric()
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('entry_price')
                    ->label('Entry')
                    ->numeric()
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('current_price')
                    ->label('Current')
                    ->numeric()
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('leverage')
                    ->label('Lev')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state . 'x')
                    ->sortable(),

                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->since()
                    ->sortable(),

                // Hidden by default
                TextColumn::make('quantity')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('liquidation_price')->numeric()->money('USD')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('realized_pnl')->numeric()->money('USD')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_open')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Open')
                    ->falseLabel('Closed')
                    ->placeholder('All')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('close')
                    ->label('Close Position')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Position $record) => $record->is_open)
                    ->action(function (Position $record) {
                        try {
                            app(TradingService::class)->closePositionManually($record->symbol, 'Manual closure from admin panel');
                            Notification::make()
                                ->title('Position Closed')
                                ->body("The position for {$record->symbol} has been queued for closure.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Closure Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('opened_at', 'desc');
    }
}

