<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoinBlacklistResource\Pages;
use App\Models\CoinBlacklist;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoinBlacklistResource extends Resource
{
    protected static ?string $model = CoinBlacklist::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Coin Blacklist';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema):Schema
    {
        return $schema
            ->schema([
               Section::make('Coin Information')
                    ->schema([
                        Forms\Components\TextInput::make('symbol')
                            ->required()
                            ->placeholder('e.g., AVAX/USDT')
                            ->helperText('Trading pair symbol'),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active (No restrictions)',
                                'high_confidence_only' => 'High Confidence Only (80%+)',
                                'blacklisted' => 'Blacklisted (Do not trade)',
                            ])
                            ->default('active')
                            ->live(),

                        Forms\Components\TextInput::make('min_confidence')
                            ->numeric()
                            ->default(0.70)
                            ->step(0.01)
                            ->minValue(0.50)
                            ->maxValue(1.00)
                            ->helperText('Minimum confidence required (0.50-1.00)'),
                    ]),

                Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->placeholder('Why is this coin restricted?')
                            ->rows(3),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At (Optional)')
                            ->helperText('Leave empty for permanent restriction'),

                        Forms\Components\Toggle::make('auto_added')
                            ->label('Auto-added by System')
                            ->disabled()
                            ->helperText('Was this entry added automatically?'),
                    ]),

                Section::make('Performance Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('stats_display')
                            ->label('Statistics')
                            ->content(function ($record) {
                                if (!$record || !$record->performance_stats) {
                                    return 'No statistics available';
                                }

                                $stats = $record->performance_stats;
                                return sprintf(
                                    "Win Rate: %.1f%% | Trades: %d | P&L: $%.2f\nWins: %d | Losses: %d | Avg Loss: $%.2f",
                                    $stats['win_rate'] ?? 0,
                                    $stats['total_trades'] ?? 0,
                                    $stats['total_pnl'] ?? 0,
                                    $stats['wins'] ?? 0,
                                    $stats['losses'] ?? 0,
                                    $stats['avg_loss'] ?? 0
                                );
                            }),
                    ])
                    ->hidden(fn ($record) => !$record || !$record->performance_stats),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('symbol')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'high_confidence_only',
                        'danger' => 'blacklisted',
                    ])
                    ->icons([
                        'success' => 'heroicon-o-check-circle',
                        'warning' => 'heroicon-o-exclamation-triangle',
                        'danger' => 'heroicon-o-shield-exclamation',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'high_confidence_only' => 'High Conf Required',
                        'blacklisted' => 'Blacklisted',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('min_confidence')
                    ->label('Min Conf')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => round($state * 100) . '%'),

                Tables\Columns\TextColumn::make('performance_stats')
                    ->label('Win Rate')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';
                        $stats = is_array($state) ? $state : json_decode($state, true);
                        $wr = $stats['win_rate'] ?? 0;
                        return number_format($wr, 1) . '%';
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('performance_stats')
                    ->label('Trades')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';
                        $stats = is_array($state) ? $state : json_decode($state, true);
                        return $stats['total_trades'] ?? 0;
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('performance_stats')
                    ->label('P&L')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';
                        $stats = is_array($state) ? $state : json_decode($state, true);
                        $pnl = $stats['total_pnl'] ?? 0;
                        return '$' . number_format($pnl, 2);
                    })
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        $stats = is_array($state) ? $state : json_decode($state, true);
                        $pnl = $stats['total_pnl'] ?? 0;
                        return $pnl >= 0 ? 'success' : 'danger';
                    })
                    ->sortable(false),

                Tables\Columns\IconColumn::make('auto_added')
                    ->label('Auto')
                    ->boolean()
                    ->trueIcon('heroicon-o-cpu-chip')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'high_confidence_only' => 'High Confidence Only',
                        'blacklisted' => 'Blacklisted',
                    ])
                    ->label('Filter by Status'),

                Tables\Filters\Filter::make('restricted_only')
                    ->label('Show Restricted Only')
                    ->query(fn ($query) => $query->whereIn('status', ['high_confidence_only', 'blacklisted']))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(function ($query) {
                // Custom sorting: restricted first, then by updated_at
                return $query->orderByRaw("
                    CASE status
                        WHEN 'blacklisted' THEN 1
                        WHEN 'high_confidence_only' THEN 2
                        WHEN 'active' THEN 3
                        ELSE 4
                    END
                ")->orderBy('updated_at', 'desc');
            });
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
            'index' => Pages\ListCoinBlacklists::route('/'),
            'create' => Pages\CreateCoinBlacklist::route('/create'),
            'edit' => Pages\EditCoinBlacklist::route('/{record}/edit'),
        ];
    }
}
