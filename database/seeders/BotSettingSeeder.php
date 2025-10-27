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

            // Trailing Stop Level 1
            [
                'key' => 'trailing_stop_l1_trigger',
                'value' => 3,
                'type' => 'int',
                'description' => 'Level 1: Activate at +3% profit',
            ],
            [
                'key' => 'trailing_stop_l1_target',
                'value' => -1,
                'type' => 'int',
                'description' => 'Level 1: Move stop to -1%',
            ],

            // Trailing Stop Level 2
            [
                'key' => 'trailing_stop_l2_trigger',
                'value' => 5,
                'type' => 'int',
                'description' => 'Level 2: Activate at +5% profit',
            ],
            [
                'key' => 'trailing_stop_l2_target',
                'value' => 0,
                'type' => 'int',
                'description' => 'Level 2: Move stop to breakeven (0%)',
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
