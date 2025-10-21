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
        Schema::create('market_data', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('timeframe', 10)->default('3m'); // 3m, 4h, 1d
            $table->decimal('price', 20, 8);
            $table->decimal('ema20', 20, 8)->nullable();
            $table->decimal('ema50', 20, 8)->nullable();
            $table->decimal('macd', 20, 8)->nullable();
            $table->decimal('rsi7', 10, 4)->nullable();
            $table->decimal('rsi14', 10, 4)->nullable();
            $table->decimal('atr3', 20, 8)->nullable();
            $table->decimal('atr14', 20, 8)->nullable();
            $table->decimal('volume', 30, 8)->nullable();
            $table->decimal('funding_rate', 20, 10)->nullable();
            $table->decimal('open_interest', 30, 8)->nullable();
            $table->json('price_series')->nullable(); // Last 10 prices
            $table->json('indicators')->nullable(); // All other indicators
            $table->timestamp('data_timestamp');
            $table->timestamps();

            $table->index(['symbol', 'timeframe', 'data_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_data');
    }
};
