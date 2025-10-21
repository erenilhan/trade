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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // BTC/USDT
            $table->enum('side', ['long', 'short']);
            $table->decimal('quantity', 20, 8); // Current quantity
            $table->decimal('entry_price', 20, 2); // Average entry
            $table->decimal('current_price', 20, 2); // Mark price
            $table->decimal('liquidation_price', 20, 2)->nullable();
            $table->decimal('unrealized_pnl', 20, 2)->default(0);
            $table->decimal('realized_pnl', 20, 2)->default(0);
            $table->integer('leverage')->default(1);
            $table->decimal('notional_usd', 20, 2); // Position size
            $table->bigInteger('sl_order_id')->nullable(); // Stop Loss order
            $table->bigInteger('tp_order_id')->nullable(); // Take Profit order
            $table->boolean('is_open')->default(true);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('symbol');
            $table->index('is_open');
            $table->index('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
