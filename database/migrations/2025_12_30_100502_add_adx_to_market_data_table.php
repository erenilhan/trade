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
        Schema::table('market_data', function (Blueprint $table) {
            $table->decimal('adx', 8, 4)->nullable()->after('atr14');
            $table->decimal('plus_di', 8, 4)->nullable()->after('adx');
            $table->decimal('minus_di', 8, 4)->nullable()->after('plus_di');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_data', function (Blueprint $table) {
            $table->dropColumn(['adx', 'plus_di', 'minus_di']);
        });
    }
};
