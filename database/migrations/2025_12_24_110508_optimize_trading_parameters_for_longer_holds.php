<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PROBLEM: Pozisyonlar çok hızlı kapanıyor (1-2 saat içinde, küçük kar/zararlarla)
        // ROOT CAUSES:
        // 1. Position size çok küçük ($10) → $0.10-0.30 kar/zararlar
        // 2. Trailing stops çok agresif → %6 karda hemen breakeven'a çekiliyor
        // 3. Trend invalidation çok erken → 2 sinyal varsa hemen kapanıyor

        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'position_size_usdt'],
            ['value' => '50', 'updated_at' => now()]
        );

        // TRAILING STOP OPTIMIZATIONS:
        // L2: %6 → %10 trigger (2x leverage = %5 price move)
        // L2 target: %2 → %3 (daha fazla kar kilitle)
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l2_trigger'],
            ['value' => '10', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l2_target'],
            ['value' => '3', 'updated_at' => now()]
        );

        // L3: %9 → %15 trigger (2x leverage = %7.5 price move)
        // L3 target: %5 → %8 (daha fazla kar kilitle)
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l3_trigger'],
            ['value' => '15', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l3_target'],
            ['value' => '8', 'updated_at' => now()]
        );

        // L4: %13 → %20 trigger (2x leverage = %10 price move)
        // L4 target: %8 → %12
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l4_trigger'],
            ['value' => '20', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l4_target'],
            ['value' => '12', 'updated_at' => now()]
        );

        // TREND INVALIDATION: Sadece zarardayken kapat, kârdayken bekle
        // Öncesi: pnlPercent < 2 ise kapat
        // Sonrası: pnlPercent < -3 ise kapat (3% zarar)
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trend_invalidation_min_loss'],
            ['value' => '-3', 'updated_at' => now()]
        );

        // DISABLE TRAILING STOPS DURING MIN HOLDING PERIOD
        // İlk 30 dakika hem stop loss hem de trailing stop devre dışı
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'disable_trailing_in_holding_period'],
            ['value' => 'true', 'updated_at' => now()]
        );

        echo "✅ Trading parameters optimized for longer holds:\n";
        echo "   - Position size: $10 → $50\n";
        echo "   - L2 trailing: %6 → %10 trigger, %2 → %3 target\n";
        echo "   - L3 trailing: %9 → %15 trigger, %5 → %8 target\n";
        echo "   - L4 trailing: %13 → %20 trigger, %8 → %12 target\n";
        echo "   - Trend invalidation: Only close if losing >3%\n";
        echo "   - Trailing stops disabled during 30min holding period\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old aggressive settings
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'position_size_usdt'],
            ['value' => '10', 'updated_at' => now()]
        );

        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l2_trigger'],
            ['value' => '6', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l2_target'],
            ['value' => '2', 'updated_at' => now()]
        );

        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l3_trigger'],
            ['value' => '9', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l3_target'],
            ['value' => '5', 'updated_at' => now()]
        );

        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l4_trigger'],
            ['value' => '13', 'updated_at' => now()]
        );
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'trailing_stop_l4_target'],
            ['value' => '8', 'updated_at' => now()]
        );

        DB::table('bot_settings')->where('key', 'trend_invalidation_min_loss')->delete();
        DB::table('bot_settings')->where('key', 'disable_trailing_in_holding_period')->delete();
    }
};
