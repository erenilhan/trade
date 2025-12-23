<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Apply fixes for premature stop loss issue:
     * - Wider stop loss (ATR*1.5 instead of 0.75)
     * - Minimum holding period (30 minutes)
     * - Initial stop buffer (15 minutes @ ATR*2.0)
     * - Post-stop-loss cooldown (120 minutes)
     */
    public function up(): void
    {
        $settings = [
            // 1. Wider Stop Loss
            [
                'key' => 'dynamic_sl_atr_multiplier',
                'value' => '1.5',
                'description' => 'Stop loss ATR multiplier - wider stops to prevent premature exits (was 0.75)',
            ],

            // 2. Minimum Holding Period
            [
                'key' => 'min_holding_period_minutes',
                'value' => '30',
                'description' => 'Minimum holding period (minutes) - ignore stop loss during this time',
            ],

            // 3. Initial Stop Buffer
            [
                'key' => 'initial_stop_buffer_minutes',
                'value' => '15',
                'description' => 'Initial stop buffer period (minutes) - use wider stops during this time',
            ],
            [
                'key' => 'initial_stop_buffer_multiplier',
                'value' => '2.0',
                'description' => 'Initial stop buffer multiplier - how much wider to make stops (e.g., 2.0 = double)',
            ],

            // 4. Post-Stop-Loss Cooldown
            [
                'key' => 'stop_loss_cooldown_minutes',
                'value' => '120',
                'description' => 'Cooldown period after stop loss (minutes) - prevent re-entering failed setup',
            ],

            // 5. Version Tracking
            [
                'key' => 'stop_loss_fix_version',
                'value' => '1.0.0',
                'description' => 'Version of stop loss fixes applied (Dec 2025)',
            ],
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'dynamic_sl_atr_multiplier',
            'min_holding_period_minutes',
            'initial_stop_buffer_minutes',
            'initial_stop_buffer_multiplier',
            'stop_loss_cooldown_minutes',
            'stop_loss_fix_version',
        ];

        DB::table('bot_settings')->whereIn('key', $keys)->delete();
    }
};
