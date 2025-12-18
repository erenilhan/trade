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
        Schema::create('taapi_usage', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('request_count')->default(0);
            $table->integer('daily_limit')->default(5000);
            $table->timestamps();

            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taapi_usage');
    }
};
