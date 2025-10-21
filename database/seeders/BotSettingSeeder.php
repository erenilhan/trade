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
            [
                'key' => 'bot_enabled',
                'value' => true,
                'type' => 'bool',
                'description' => 'Enable/disable auto trading',
            ],
            [
                'key' => 'initial_capital',
                'value' => 9.0,
                'type' => 'float',
                'description' => 'Starting capital for ROI calculation (USDT)',
            ],
            [
                'key' => 'max_leverage',
                'value' => 2,
                'type' => 'int',
                'description' => 'Maximum leverage (1–20)',
            ],
            [
                'key' => 'take_profit_percent',
                'value' => 5,
                'type' => 'int',
                'description' => 'Take profit threshold %',
            ],
            [
                'key' => 'stop_loss_percent',
                'value' => 3,
                'type' => 'int',
                'description' => 'Stop loss threshold %',
            ],
            [
                'key' => 'position_size_usdt',
                'value' => 100,
                'type' => 'int',
                'description' => 'Position size in USDT',
            ],
            [
                'key' => 'use_ai',
                'value' => true,
                'type' => 'bool',
                'description' => 'Use AI for decisions',
            ],
            [
                'key' => 'symbols',
                'value' => json_encode(['BTC/USDT', 'ETH/USDT', 'SOL/USDT']),
                'type' => 'json',
                'description' => 'Trading symbols',
            ],
            [
                'key' => 'ai_provider',
                'value' => 'openrouter',
                'type' => 'string',
                'description' => 'AI provider',
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
    }
}
