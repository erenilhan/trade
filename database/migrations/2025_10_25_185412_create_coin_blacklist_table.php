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
        Schema::create('coin_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // e.g., 'AVAX/USDT'
            $table->enum('status', ['blacklisted', 'high_confidence_only', 'active'])->default('active');
            $table->decimal('min_confidence', 3, 2)->default(0.70); // Minimum confidence required
            $table->string('reason')->nullable(); // Why it's blacklisted
            $table->json('performance_stats')->nullable(); // {win_rate, trades, pnl, avg_loss}
            $table->boolean('auto_added')->default(false); // Was it added automatically?
            $table->timestamp('expires_at')->nullable(); // Temporary blacklist expiry
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_blacklist');
    }
};
