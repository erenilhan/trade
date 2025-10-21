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
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
            'stop_loss_percent' => BotSetting::get('stop_loss_percent', 3),
            'supported_coins' => BotSetting::get('supported_coins', ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT']),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
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

                        TextInput::make('take_profit_percent')
                            ->label('Take Profit %')
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->suffix('%')
                            ->helperText('Target profit percentage')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state) => $this->saveSetting('take_profit_percent', $state)),

                        TextInput::make('stop_loss_percent')
                            ->label('Stop Loss %')
                            ->numeric()
                            ->required()
                            ->default(3)
                            ->suffix('%')
                            ->helperText('Maximum loss percentage')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state) => $this->saveSetting('stop_loss_percent', $state)),
                    ])
                    ->columns(2),

                Section::make('Multi-Coin Settings')
                    ->description('Configure multi-coin trading (for new system)')
                    ->schema([
                        Select::make('supported_coins')
                            ->label('Active Trading Pairs')
                            ->multiple()
                            ->options([
                                'BTC/USDT' => 'Bitcoin (BTC/USDT)',
                                'ETH/USDT' => 'Ethereum (ETH/USDT)',
                                'SOL/USDT' => 'Solana (SOL/USDT)',
                                'BNB/USDT' => 'BNB (BNB/USDT)',
                                'XRP/USDT' => 'Ripple (XRP/USDT)',
                                'BTC/USDT',
                                'DOGE/USDT' => 'Dogecoin (DOGE/USDT)',
                                'ADA/USDT' => 'Cardano (ADA/USDT)',
                                'AVAX/USDT' => 'Avalanche (AVAX/USDT)',
                                'LINK/USDT' => 'Chainlink (LINK/USDT)',
                                'DOT/USDT' => 'Polkadot (DOT/USDT)',
                            ])
                            ->default(['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT'])
                            ->helperText('Select which coins to trade (multi-coin system only)')
                            ->live()
                            ->afterStateUpdated(fn($state) => $this->saveSetting('supported_coins', $state)),
                    ])
                    ->columns(1),
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
            'stop_loss_percent' => 'Maximum loss percentage',
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
            'stop_loss_percent' => 'Stop Loss',
            'supported_coins' => 'Trading Pairs',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }
}
