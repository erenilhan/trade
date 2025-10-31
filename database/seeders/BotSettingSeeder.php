<?php

namespace Database\Seeders;

use App\Models\BotSetting;
use Illuminate\Database\Seeder;

class BotSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Bot Control
            [
                'key' => 'bot_enabled',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable/disable auto trading',
            ],
            [
                'key' => 'use_ai',
                'value' => true,
                'type' => 'bool',
                'description' => 'Use AI for trading decisions',
            ],

            // AI Configuration
            [
                'key' => 'ai_provider',
                'value' => 'openrouter',
                'type' => 'string',
                'description' => 'AI provider (openrouter/deepseek/openai)',
            ],
            [
                'key' => 'ai_model',
                'value' => 'deepseek/deepseek-chat-v3.1',
                'type' => 'string',
                'description' => 'AI model name',
            ],

            // Trading Parameters
            [
                'key' => 'initial_capital',
                'value' => 9.0,
                'type' => 'float',
                'description' => 'Starting capital for ROI calculation (USDT)',
            ],
            [
                'key' => 'position_size_usdt',
                'value' => 100,
                'type' => 'int',
                'description' => 'Position size in USDT',
            ],
            [
                'key' => 'max_leverage',
                'value' => 2,
                'type' => 'int',
                'description' => 'Maximum leverage (1-125)',
            ],
            [
                'key' => 'take_profit_percent',
                'value' => 5,
                'type' => 'int',
                'description' => 'Take profit threshold %',
            ],
            [
                'key' => 'stop_loss_percent_long',
                'value' => 3,
                'type' => 'int',
                'description' => 'Stop loss threshold % for LONG positions',
            ],
            [
                'key' => 'stop_loss_percent_short',
                'value' => 3,
                'type' => 'int',
                'description' => 'Stop loss threshold % for SHORT positions',
            ],

            // Multi-Coin Settings (using config default)
            [
                'key' => 'supported_coins',
                'value' => json_encode(config('trading.default_active_pairs', [
                    'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT'
                ])),
                'type' => 'json',
                'description' => 'Active trading pairs for multi-coin system',
            ],

            // Pre-Filtering
            [
                'key' => 'enable_pre_filtering',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable pre-filtering to reduce AI token usage',
            ],

            // Trailing Stop Level 1 - DISABLED (historically 0% win rate, 7 trades lost)
            [
                'key' => 'trailing_stop_l1_trigger',
                'value' => 999,
                'type' => 'float',
                'description' => 'Level 1: DISABLED (was +4.5%, had 0% win rate)',
            ],
            [
                'key' => 'trailing_stop_l1_target',
                'value' => -0.5,
                'type' => 'float',
                'description' => 'Level 1: DISABLED (was -0.5%, had 0% win rate)',
            ],

            // Trailing Stop Level 2 - OPTIMIZED (increased trigger, preserve small profit)
            [
                'key' => 'trailing_stop_l2_trigger',
                'value' => 6,
                'type' => 'int',
                'description' => 'Level 2: Activate at +6% profit (was +5%, too early)',
            ],
            [
                'key' => 'trailing_stop_l2_target',
                'value' => 1,
                'type' => 'int',
                'description' => 'Level 2: Move stop to +1% (was 0% breakeven, now preserves small profit)',
            ],

            // Trailing Stop Level 3
            [
                'key' => 'trailing_stop_l3_trigger',
                'value' => 8,
                'type' => 'int',
                'description' => 'Level 3: Activate at +8% profit',
            ],
            [
                'key' => 'trailing_stop_l3_target',
                'value' => 3,
                'type' => 'int',
                'description' => 'Level 3: Move stop to +3% (lock profit)',
            ],

            // Trailing Stop Level 4
            [
                'key' => 'trailing_stop_l4_trigger',
                'value' => 12,
                'type' => 'int',
                'description' => 'Level 4: Activate at +12% profit',
            ],
            [
                'key' => 'trailing_stop_l4_target',
                'value' => 6,
                'type' => 'int',
                'description' => 'Level 4: Move stop to +6% (lock big profit)',
            ],

            // Sleep Mode (Low Liquidity Hours)
            [
                'key' => 'sleep_mode_enabled',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable sleep mode during low liquidity hours (23:00-04:00 UTC)',
            ],
            [
                'key' => 'sleep_mode_start_hour',
                'value' => 23,
                'type' => 'int',
                'description' => 'Sleep mode start hour (UTC, 0-23)',
            ],
            [
                'key' => 'sleep_mode_end_hour',
                'value' => 4,
                'type' => 'int',
                'description' => 'Sleep mode end hour (UTC, 0-23)',
            ],
            [
                'key' => 'sleep_mode_max_positions',
                'value' => 3,
                'type' => 'int',
                'description' => 'Maximum positions allowed during sleep mode',
            ],
            [
                'key' => 'sleep_mode_tighter_stops',
                'value' => true,
                'type' => 'bool',
                'description' => 'Tighten stop losses during sleep mode',
            ],
            [
                'key' => 'sleep_mode_stop_multiplier',
                'value' => 0.75,
                'type' => 'float',
                'description' => 'Stop loss multiplier during sleep mode (0.75 = 25% tighter)',
            ],

            // Daily Max Drawdown Protection
            [
                'key' => 'daily_max_drawdown_enabled',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable daily max drawdown protection',
            ],
            [
                'key' => 'daily_max_drawdown_percent',
                'value' => 8.0,
                'type' => 'float',
                'description' => 'Stop trading if daily loss exceeds this % (default: 8%)',
            ],
            [
                'key' => 'daily_max_drawdown_cooldown_hours',
                'value' => 24,
                'type' => 'int',
                'description' => 'Hours to pause trading after max drawdown hit',
            ],

            // Cluster Loss Cooldown
            [
                'key' => 'cluster_loss_cooldown_enabled',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable cooldown after consecutive losses',
            ],
            [
                'key' => 'cluster_loss_consecutive_trigger',
                'value' => 3,
                'type' => 'int',
                'description' => 'Number of consecutive losses to trigger cooldown',
            ],
            [
                'key' => 'cluster_loss_cooldown_hours',
                'value' => 24,
                'type' => 'int',
                'description' => 'Hours to pause trading after cluster losses',
            ],
        ];

        foreach ($settings as $setting) {
            BotSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                ]
            );
        }

        $this->command->info('✅ Bot settings seeded successfully!');
        $this->command->info('📊 Default coins: ' . implode(', ', config('trading.default_active_pairs', [])));
        $this->command->info('🔒 Trailing stops: 4 levels configured');
    }
}
