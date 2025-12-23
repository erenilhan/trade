<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Multi-coin trading scheduler (10 minutes to reduce AI costs)
Schedule::command('trading:multi-coin')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Update open positions every minute
Schedule::command('positions:update')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Monitor positions for auto-close every minute
Schedule::command('positions:monitor')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Database cleanup daily at 2 AM
Schedule::command('db:cleanup --days=7 --ai-days=3')
    ->dailyAt('02:00')
    ->onOneServer();

// Sync Binance Futures markets weekly on Sunday at 3 AM
Schedule::command('binance:sync-futures --top=30')
    ->weeklyOn(0, '03:00')
    ->onOneServer();
