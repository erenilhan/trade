<?php

namespace App\Filament\Pages;

use App\Models\BotSetting;
use BackedEnum;
use Exception;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use UnitEnum;

class ManageBotSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Bot Settings';

    protected static string|null|UnitEnum $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    protected string $view = 'filament.pages.manage-bot-settings';

    public function mount(): void
    {
        $this->form->fill($this->getSettingsArray());
    }

    protected function getSettingsArray(): array
    {
        return [
            'bot_enabled' => BotSetting::get('bot_enabled', true),
            'use_ai' => BotSetting::get('use_ai', true),
            'ai_provider' => env('AI_PROVIDER', 'openrouter'),
            'ai_model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat'),
            'ai_system_prompt' => BotSetting::get('ai_system_prompt', ''),
            'initial_capital' => BotSetting::get('initial_capital', 9),
            'position_size_usdt' => BotSetting::get('position_size_usdt', 100),
            'max_leverage' => BotSetting::get('max_leverage', 2),
            'take_profit_percent' => BotSetting::get('take_profit_percent', 5),
            'stop_loss_percent_long' => BotSetting::get('stop_loss_percent_long', 3),
            'stop_loss_percent_short' => BotSetting::get('stop_loss_percent_short', 3),
            'supported_coins' => BotSetting::get('supported_coins', config('trading.default_active_pairs', [])),
            // Trailing stops
            'trailing_stop_l1_trigger' => BotSetting::get('trailing_stop_l1_trigger', 3),
            'trailing_stop_l1_target' => BotSetting::get('trailing_stop_l1_target', -1),
            'trailing_stop_l2_trigger' => BotSetting::get('trailing_stop_l2_trigger', 5),
            'trailing_stop_l2_target' => BotSetting::get('trailing_stop_l2_target', 0),
            'trailing_stop_l3_trigger' => BotSetting::get('trailing_stop_l3_trigger', 8),
            'trailing_stop_l3_target' => BotSetting::get('trailing_stop_l3_target', 3),
            'trailing_stop_l4_trigger' => BotSetting::get('trailing_stop_l4_trigger', 12),
            'trailing_stop_l4_target' => BotSetting::get('trailing_stop_l4_target', 6),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                       Tab::make('General')
                            ->schema([
                                Section::make('Bot Control')
                                    ->description('Enable or disable the trading bot')
                                    ->schema([
                                        Toggle::make('bot_enabled')
                                            ->label('Bot Enabled')
                                            ->helperText('Master switch for the entire trading bot')
                                            ->default(true)
                                            ->live()
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('bot_enabled', $state)),
                                    ])
                                    ->columns(1),

                                Section::make('AI Configuration')
                                    ->description('Configure AI provider and model')
                                    ->schema([
                                        Toggle::make('use_ai')
                                            ->label('Use AI Trading')
                                            ->helperText('Enable AI-powered trading decisions')
                                            ->default(true)
                                            ->live()
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('use_ai', $state)),

                                        Select::make('ai_provider')
                                            ->label('AI Provider')
                                            ->options([
                                                'openrouter' => 'OpenRouter',
                                                'deepseek' => 'DeepSeek Direct',
                                                'openai' => 'OpenAI',
                                            ])
                                            ->default(env('AI_PROVIDER', 'openrouter'))
                                            ->helperText('Select which AI service to use')
                                            ->live()
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('ai_provider', $state)),

                                        TextInput::make('ai_model')
                                            ->label('AI Model')
                                            ->default(env('OPENROUTER_MODEL', 'deepseek/deepseek-chat'))
                                            ->helperText('Model name (e.g., deepseek/deepseek-chat, x-ai/grok-2-vision-1212)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('ai_model', $state)),

                                        Textarea::make('ai_system_prompt')
                                            ->label('System Prompt Override')
                                            ->rows(4)
                                            ->placeholder('Leave empty to use default prompt...')
                                            ->helperText('Custom system prompt for AI (optional, leave empty for default)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('ai_system_prompt', $state)),
                                    ])
                                    ->columns(2),
                            ]),
                       Tab::make('Trading')
                            ->schema([
                                Section::make('Trading Parameters')
                                    ->description('Configure position sizing and risk management')
                                    ->schema([
                                        TextInput::make('initial_capital')
                                            ->label('Initial Capital (USDT)')
                                            ->numeric()
                                            ->required()
                                            ->default(9)
                                            ->helperText('Starting balance for ROI calculation')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('initial_capital', $state)),

                                        TextInput::make('position_size_usdt')
                                            ->label('Position Size (USDT)')
                                            ->numeric()
                                            ->required()
                                            ->default(100)
                                            ->helperText('Size of each position in USDT')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('position_size_usdt', $state)),

                                        TextInput::make('max_leverage')
                                            ->label('Max Leverage')
                                            ->numeric()
                                            ->required()
                                            ->default(2)
                                            ->minValue(1)
                                            ->maxValue(125)
                                            ->helperText('Maximum leverage to use (1-125x)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('max_leverage', $state)),
                                    ])
                                    ->columns(3),

                                Section::make('Profit & Loss Management')
                                    ->description('Configure profit targets and loss limits for LONG and SHORT positions')
                                    ->schema([
                                        TextInput::make('take_profit_percent')
                                            ->label('Take Profit %')
                                            ->numeric()
                                            ->required()
                                            ->default(5)
                                            ->suffix('%')
                                            ->helperText('Target profit percentage (applies to both LONG and SHORT)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('take_profit_percent', $state)),

                                        TextInput::make('stop_loss_percent_long')
                                            ->label('Stop Loss % (LONG)')
                                            ->numeric()
                                            ->required()
                                            ->default(3)
                                            ->suffix('%')
                                            ->helperText('Maximum loss % for LONG positions (price goes DOWN)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('stop_loss_percent_long', $state)),

                                        TextInput::make('stop_loss_percent_short')
                                            ->label('Stop Loss % (SHORT)')
                                            ->numeric()
                                            ->required()
                                            ->default(3)
                                            ->suffix('%')
                                            ->helperText('Maximum loss % for SHORT positions (price goes UP)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('stop_loss_percent_short', $state)),
                                    ])
                                    ->columns(3),

                                Section::make('Multi-Coin Settings')
                                    ->description('Configure multi-coin trading (for new system)')
                                    ->schema([
                                        Select::make('supported_coins')
                                            ->label('Active Trading Pairs')
                                            ->multiple()
                                            ->options(function () {
                                                $pairs = config('trading.supported_pairs', []);
                                                return collect($pairs)->mapWithKeys(fn($name, $pair) => [$pair => "$name ($pair)"])->toArray();
                                            })
                                            ->default(config('trading.default_active_pairs', []))
                                            ->helperText('Select which coins to trade (' . count(config('trading.supported_pairs', [])) . ' coins available, pre-filtering enabled to save AI tokens)')
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn($state) => $this->saveSetting('supported_coins', $state)),
                                    ])
                                    ->columns(1),
                            ]),
                        Tab::make('Trailing Stops')
                            ->schema([
                                Section::make('Trailing Stop Levels')
                                    ->description('Multi-level trailing stop configuration - automatically protect profits as positions grow')
                                    ->schema([
                                        // Level 1
                                        Section::make('Level 1 Configuration')
                                            ->schema([
                                                TextInput::make('trailing_stop_l1_trigger')
                                                    ->label('Trigger (% profit)')
                                                    ->numeric()
                                                    ->default(3)
                                                    ->suffix('%')
                                                    ->helperText('Activate Level 1 when profit reaches this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l1_trigger', $state)),

                                                TextInput::make('trailing_stop_l1_target')
                                                    ->label('Target (% from entry)')
                                                    ->numeric()
                                                    ->default(-1)
                                                    ->suffix('%')
                                                    ->helperText('Move stop loss to this % from entry (negative = loss, 0 = breakeven, positive = profit)')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l1_target', $state)),
                                            ])
                                            ->columns(2),

                                        // Level 2
                                        Section::make('Level 2 Configuration')
                                            ->schema([
                                                TextInput::make('trailing_stop_l2_trigger')
                                                    ->label('Trigger (% profit)')
                                                    ->numeric()
                                                    ->default(5)
                                                    ->suffix('%')
                                                    ->helperText('Activate Level 2 when profit reaches this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l2_trigger', $state)),

                                                TextInput::make('trailing_stop_l2_target')
                                                    ->label('Target (% from entry)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('%')
                                                    ->helperText('Typically breakeven (0%)')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l2_target', $state)),
                                            ])
                                            ->columns(2),

                                        // Level 3
                                        Section::make('Level 3 Configuration')
                                            ->schema([
                                                TextInput::make('trailing_stop_l3_trigger')
                                                    ->label('Trigger (% profit)')
                                                    ->numeric()
                                                    ->default(8)
                                                    ->suffix('%')
                                                    ->helperText('Activate Level 3 when profit reaches this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l3_trigger', $state)),

                                                TextInput::make('trailing_stop_l3_target')
                                                    ->label('Target (% from entry)')
                                                    ->numeric()
                                                    ->default(3)
                                                    ->suffix('%')
                                                    ->helperText('Lock in profit at this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l3_target', $state)),
                                            ])
                                            ->columns(2),

                                        // Level 4
                                        Section::make('Level 4 Configuration')
                                            ->schema([
                                                TextInput::make('trailing_stop_l4_trigger')
                                                    ->label('Trigger (% profit)')
                                                    ->numeric()
                                                    ->default(12)
                                                    ->suffix('%')
                                                    ->helperText('Activate Level 4 when profit reaches this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l4_trigger', $state)),

                                                TextInput::make('trailing_stop_l4_target')
                                                    ->label('Target (% from entry)')
                                                    ->numeric()
                                                    ->default(6)
                                                    ->suffix('%')
                                                    ->helperText('Lock in big profit at this %')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn($state) => $this->saveSetting('trailing_stop_l4_target', $state)),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columns(1)
                                    ->collapsible(),
                            ]),
                    ])
            ])
            ->statePath('data');
    }

    public function saveSetting(string $key, mixed $value): void
    {
        try {
            if ($key === 'supported_coins') {
                BotSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => json_encode($value),
                        'type' => 'json',
                        'description' => 'Active trading pairs for multi-coin system',
                    ]
                );
            } elseif ($key === 'ai_provider' || $key === 'ai_model') {
                // Save to both database and .env
                BotSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => 'string',
                        'description' => $this->getSettingDescription($key),
                    ]
                );
                $this->updateEnvFile($key, $value);
            } elseif ($key === 'ai_system_prompt') {
                if (!empty($value)) {
                    BotSetting::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'type' => 'string',
                            'description' => 'Custom AI system prompt',
                        ]
                    );
                }
            } else {
                // Determine type
                $type = is_bool($value) ? 'bool' : (is_numeric($value) ? (str_contains((string)$value, '.') ? 'float' : 'int') : 'string');

                BotSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => (string)$value,
                        'type' => $type,
                        'description' => $this->getSettingDescription($key),
                    ]
                );
            }

            Notification::make()
                ->title('Saved')
                ->body("{$this->getSettingLabel($key)} updated")
                ->success()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getSettingDescription(string $key): string
    {
        return match ($key) {
            'bot_enabled' => 'Master switch for trading bot',
            'use_ai' => 'Enable AI-powered trading',
            'initial_capital' => 'Starting capital for ROI calculation',
            'position_size_usdt' => 'Size of each position in USDT',
            'max_leverage' => 'Maximum leverage multiplier',
            'take_profit_percent' => 'Target profit percentage',
            'stop_loss_percent_long' => 'Stop loss threshold % for LONG positions',
            'stop_loss_percent_short' => 'Stop loss threshold % for SHORT positions',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    protected function updateEnvFile(string $key, mixed $value): void
    {
        $envKey = match ($key) {
            'ai_provider' => 'AI_PROVIDER',
            'ai_model' => 'OPENROUTER_MODEL',
            default => strtoupper($key),
        };

        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if (str_contains($content, "{$envKey}=")) {
                // Update existing
                $content = preg_replace(
                    "/^{$envKey}=.*/m",
                    "{$envKey}={$value}",
                    $content
                );
            } else {
                // Add new
                $content .= "\n{$envKey}={$value}\n";
            }

            file_put_contents($path, $content);
        }
    }

    protected function getSettingLabel(string $key): string
    {
        return match ($key) {
            'bot_enabled' => 'Bot Status',
            'use_ai' => 'AI Trading',
            'ai_provider' => 'AI Provider',
            'ai_model' => 'AI Model',
            'ai_system_prompt' => 'System Prompt',
            'initial_capital' => 'Initial Capital',
            'position_size_usdt' => 'Position Size',
            'max_leverage' => 'Leverage',
            'take_profit_percent' => 'Take Profit',
            'stop_loss_percent_long' => 'Stop Loss (LONG)',
            'stop_loss_percent_short' => 'Stop Loss (SHORT)',
            'supported_coins' => 'Trading Pairs',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }
}
