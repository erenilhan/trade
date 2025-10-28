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

// Prune AI logs daily
Schedule::command('logs:prune-ai')
    ->daily()
    ->onOneServer();
