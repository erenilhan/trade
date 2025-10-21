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
            if (!Schema::hasColumn('positions', 'liquidation_price')) {
                $table->decimal('liquidation_price', 20, 8)->nullable()->after('current_price');
            }
            if (!Schema::hasColumn('positions', 'leverage')) {
                $table->integer('leverage')->default(1)->after('current_price');
            }
            if (!Schema::hasColumn('positions', 'notional_value')) {
                $table->decimal('notional_value', 20, 2)->nullable()->after('current_price');
            }
            if (!Schema::hasColumn('positions', 'exit_plan')) {
                $table->json('exit_plan')->nullable();
            }
            if (!Schema::hasColumn('positions', 'confidence')) {
                $table->decimal('confidence', 3, 2)->nullable();
            }
            if (!Schema::hasColumn('positions', 'risk_usd')) {
                $table->decimal('risk_usd', 20, 2)->nullable();
            }
            if (!Schema::hasColumn('positions', 'sl_order_id')) {
                $table->string('sl_order_id')->nullable();
            }
            if (!Schema::hasColumn('positions', 'tp_order_id')) {
                $table->string('tp_order_id')->nullable();
            }
            if (!Schema::hasColumn('positions', 'entry_order_id')) {
                $table->string('entry_order_id')->nullable();
            }
            if (!Schema::hasColumn('positions', 'wait_for_fill')) {
                $table->boolean('wait_for_fill')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'liquidation_price',
                'leverage',
                'notional_value',
                'exit_plan',
                'confidence',
                'risk_usd',
                'sl_order_id',
                'tp_order_id',
                'entry_order_id',
                'wait_for_fill',
            ]);
        });
    }
};
