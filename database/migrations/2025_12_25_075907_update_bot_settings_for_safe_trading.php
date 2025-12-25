<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\BotSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 🎯 SAFE TRADING SETTINGS FOR SMALL ACCOUNTS ($18-25 balance)
        
        // Core Trading Parameters
        BotSetting::set('position_size_usdt', 4);           // $50 → $4 (92% less risk)
        BotSetting::set('initial_capital', 25);             // Reference capital
        BotSetting::set('max_leverage', 2);                 // Keep 2x (proven best)
        
        // Profit & Loss Management
        BotSetting::set('take_profit_percent', 6);          // %5 → %6 (covers commission)
        BotSetting::set('stop_loss_percent', 5);            // %3 → %5 (less noise)
        
        // Risk Management
        BotSetting::set('max_trades_per_day', 2);           // Max 2 trades daily
        BotSetting::set('max_daily_drawdown', 1.0);         // 1% max daily loss
        BotSetting::set('max_positions', 2);                // Max 2 positions (2x$4 = $8 total)
        
        // Trailing Stops (Simplified 3-Level System)
        BotSetting::set('trailing_stop_l1_trigger', 8);     // %8 profit → activate L1
        BotSetting::set('trailing_stop_l1_target', 2);      // Move stop to %2 (breakeven + commission)
        
        BotSetting::set('trailing_stop_l2_trigger', 12);    // %12 profit → activate L2
        BotSetting::set('trailing_stop_l2_target', 6);      // Move stop to %6 (lock profit)
        
        BotSetting::set('trailing_stop_l3_trigger', 16);    // %16 profit → activate L3
        BotSetting::set('trailing_stop_l3_target', 10);     // Move stop to %10 (big profit protection)
        
        // Disable Level 4 (unnecessary complexity)
        BotSetting::set('trailing_stop_l4_trigger', 999);   // Effectively disabled
        BotSetting::set('trailing_stop_l4_target', 0);
        
        // Safety Features
        BotSetting::set('min_holding_period_minutes', 120); // 2 hours minimum hold
        BotSetting::set('stop_loss_cooldown_minutes', 120); // 2 hours cooldown after stop loss
        BotSetting::set('dynamic_sl_atr_multiplier', 2.0);  // Wider stops for less false signals
        
        // AI Confidence Filtering
        BotSetting::set('min_ai_confidence', 0.60);         // 60% minimum confidence
        BotSetting::set('max_ai_confidence', 0.82);         // 82% maximum (avoid overconfidence trap)
        
        echo "✅ SAFE TRADING SETTINGS APPLIED:\n";
        echo "Position Size: $4 (was $50)\n";
        echo "Stop Loss: 5% (was 3%)\n";
        echo "Take Profit: 6% (was 5%)\n";
        echo "Max Positions: 2 (total $8 exposure)\n";
        echo "Trailing Stops: 3-level simplified system\n";
        echo "Daily Risk: 1% max drawdown\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original risky settings (not recommended)
        BotSetting::set('position_size_usdt', 50);
        BotSetting::set('take_profit_percent', 5);
        BotSetting::set('stop_loss_percent', 3);
        BotSetting::set('max_trades_per_day', 10);
        BotSetting::set('max_daily_drawdown', 8.0);
        BotSetting::set('max_positions', 3);
        
        // Restore complex trailing stops
        BotSetting::set('trailing_stop_l1_trigger', 999);
        BotSetting::set('trailing_stop_l1_target', -0.5);
        BotSetting::set('trailing_stop_l2_trigger', 10);
        BotSetting::set('trailing_stop_l2_target', 2);
        BotSetting::set('trailing_stop_l3_trigger', 15);
        BotSetting::set('trailing_stop_l3_target', 8);
        BotSetting::set('trailing_stop_l4_trigger', 20);
        BotSetting::set('trailing_stop_l4_target', 12);
    }
};
