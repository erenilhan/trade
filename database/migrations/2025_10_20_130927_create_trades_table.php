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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('symbol'); // BTC/USDT
            $table->enum('side', ['buy', 'sell']);
            $table->enum('type', ['market', 'limit']);
            $table->decimal('amount', 20, 8); // Coin amount
            $table->decimal('price', 20, 2); // Entry price
            $table->decimal('cost', 20, 2); // USDT cost
            $table->integer('leverage')->default(1);
            $table->decimal('stop_loss', 20, 2)->nullable();
            $table->decimal('take_profit', 20, 2)->nullable();
            $table->enum('status', ['pending', 'filled', 'cancelled', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('response_data')->nullable(); // Binance response
            $table->timestamps();

            $table->index('symbol');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
