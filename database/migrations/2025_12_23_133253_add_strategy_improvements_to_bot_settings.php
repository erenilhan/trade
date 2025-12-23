<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Strategy Improvements (December 2025)
     */
    public function up(): void
    {
        $settings = [
            // 1. RSI Ranges (Tightened)
            ['key' => 'rsi_long_min', 'value' => '50'],
            ['key' => 'rsi_long_max', 'value' => '70'],
            ['key' => 'rsi_short_min', 'value' => '30'],
            ['key' => 'rsi_short_max', 'value' => '55'],

            // 2. Regional Volume Thresholds
            ['key' => 'volume_threshold_us', 'value' => '0.9'],
            ['key' => 'volume_threshold_asia', 'value' => '0.8'],
            ['key' => 'volume_threshold_europe', 'value' => '0.95'],
            ['key' => 'volume_threshold_offpeak', 'value' => '1.0'],

            // 3. Dynamic TP/SL (ATR-based)
            ['key' => 'dynamic_tp_enabled', 'value' => 'true'],
            ['key' => 'dynamic_tp_min_percent', 'value' => '7.5'],
            ['key' => 'dynamic_tp_atr_multiplier', 'value' => '1.5'],
            ['key' => 'dynamic_sl_enabled', 'value' => 'true'],
            ['key' => 'dynamic_sl_atr_multiplier', 'value' => '0.75'],

            // 4. Trailing Stops (L3 Optimized)
            ['key' => 'trailing_stop_l2_trigger', 'value' => '8'],
            ['key' => 'trailing_stop_l2_target', 'value' => '2'],
            ['key' => 'trailing_stop_l3_trigger', 'value' => '10'],
            ['key' => 'trailing_stop_l3_target', 'value' => '5'],
            ['key' => 'trailing_stop_l4_trigger', 'value' => '12'],
            ['key' => 'trailing_stop_l4_target', 'value' => '8'],

            // 5. Pre-Sleep Position Closing
            ['key' => 'pre_sleep_close_enabled', 'value' => 'true'],
            ['key' => 'pre_sleep_close_hour_utc', 'value' => '21'],
            ['key' => 'sleep_mode_start_hour', 'value' => '23'],
            ['key' => 'sleep_mode_end_hour', 'value' => '4'],

            // 6. AI Scoring (Volume Separate)
            ['key' => 'ai_score_required', 'value' => '3'],
            ['key' => 'ai_score_max', 'value' => '4'],
            ['key' => 'ai_volume_separate_check', 'value' => 'true'],

            // 7. Strategy Version
            ['key' => 'strategy_version', 'value' => '2.0.0'],
            ['key' => 'strategy_updated_at', 'value' => now()->toDateTimeString()],
        ];

        foreach ($settings as $setting) {
            DB::table('bot_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        echo "✅ Strategy improvements applied to bot_settings\n";
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $keys = [
            'rsi_long_min', 'rsi_long_max', 'rsi_short_min', 'rsi_short_max',
            'volume_threshold_us', 'volume_threshold_asia', 'volume_threshold_europe', 'volume_threshold_offpeak',
            'dynamic_tp_enabled', 'dynamic_tp_min_percent', 'dynamic_tp_atr_multiplier',
            'dynamic_sl_enabled', 'dynamic_sl_atr_multiplier',
            'trailing_stop_l2_trigger', 'trailing_stop_l2_target',
            'trailing_stop_l3_trigger', 'trailing_stop_l3_target',
            'trailing_stop_l4_trigger', 'trailing_stop_l4_target',
            'pre_sleep_close_enabled', 'pre_sleep_close_hour_utc',
            'sleep_mode_start_hour', 'sleep_mode_end_hour',
            'ai_score_required', 'ai_score_max', 'ai_volume_separate_check',
            'strategy_version', 'strategy_updated_at',
        ];

        DB::table('bot_settings')->whereIn('key', $keys)->delete();

        echo "❌ Strategy improvements rolled back\n";
    }
};
