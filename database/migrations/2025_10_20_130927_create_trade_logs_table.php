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
        Schema::create('trade_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('action', ['buy', 'sell', 'close_profitable', 'stop_loss', 'hold', 'error']);
            $table->boolean('success')->default(false);
            $table->text('message')->nullable();
            $table->json('account_state')->nullable(); // Account snapshot
            $table->json('decision_data')->nullable(); // AI reasoning
            $table->json('result_data')->nullable(); // Trade result
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index('action');
            $table->index('success');
            $table->index('executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_logs');
    }
};
