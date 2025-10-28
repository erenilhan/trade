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
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->comment('Trading date (UTC)');
            $table->decimal('starting_balance', 20, 8)->default(0)->comment('Balance at start of day');
            $table->decimal('current_balance', 20, 8)->default(0)->comment('Current balance');
            $table->decimal('daily_pnl', 20, 8)->default(0)->comment('Daily profit/loss (USDT)');
            $table->decimal('daily_pnl_percent', 10, 4)->default(0)->comment('Daily P&L percentage');
            $table->boolean('max_drawdown_hit')->default(false)->comment('Whether max drawdown limit was hit');
            $table->timestamp('cooldown_until')->nullable()->comment('Cooldown end time if max drawdown hit');
            $table->integer('trades_count')->default(0)->comment('Number of trades today');
            $table->integer('wins_count')->default(0)->comment('Number of winning trades');
            $table->integer('losses_count')->default(0)->comment('Number of losing trades');
            $table->json('metadata')->nullable()->comment('Additional daily stats');
            $table->timestamps();

            $table->index('date');
            $table->index('max_drawdown_hit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
