<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Setting name
            $table->text('value'); // Setting value
            $table->string('type')->default('string'); // string, int, bool, json
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('bot_settings')->insert([
            ['key' => 'bot_enabled', 'value' => 'true', 'type' => 'bool', 'description' => 'Enable/disable auto trading', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'initial_capital', 'value' => '10000', 'type' => 'int', 'description' => 'Starting capital in USDT', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_leverage', 'value' => '2', 'type' => 'int', 'description' => 'Maximum leverage (1-20)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'take_profit_percent', 'value' => '5', 'type' => 'int', 'description' => 'Take profit threshold %', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'stop_loss_percent', 'value' => '3', 'type' => 'int', 'description' => 'Stop loss threshold %', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'position_size_usdt', 'value' => '100', 'type' => 'int', 'description' => 'Position size in USDT', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'use_ai', 'value' => 'false', 'type' => 'bool', 'description' => 'Use AI for decisions', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'symbols', 'value' => '["BTC/USDT","ETH/USDT","SOL/USDT"]', 'type' => 'json', 'description' => 'Trading symbols', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
