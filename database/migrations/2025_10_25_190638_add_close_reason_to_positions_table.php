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
        Schema::table('positions', function (Blueprint $table) {
            $table->enum('close_reason', [
                'take_profit',      // Hit profit target
                'stop_loss',        // Hit stop loss
                'trailing_stop_l1', // Trailing stop level 1
                'trailing_stop_l2', // Trailing stop level 2
                'trailing_stop_l3', // Trailing stop level 3
                'trailing_stop_l4', // Trailing stop level 4
                'manual',           // Manually closed
                'liquidated',       // Liquidated
                'other'             // Other reason
            ])->nullable()->after('closed_at');

            $table->json('close_metadata')->nullable()->after('close_reason')
                ->comment('Additional info: {profit_pct, locked_profit, trailing_level, etc}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['close_reason', 'close_metadata']);
        });
    }
};
