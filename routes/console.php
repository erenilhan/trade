<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Multi-coin trading scheduler
Schedule::command('trading:multi-coin')
    ->everyThreeMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Update open positions every minute
Schedule::command('positions:update')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
