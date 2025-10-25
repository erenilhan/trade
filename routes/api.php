<?php

use App\Http\Controllers\Api\PositionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TradingController;
use App\Http\Controllers\Api\MultiCoinTradingController;
use App\Http\Controllers\TradeDashboardController;

Route::post('positions/close', [PositionController::class, 'close']);

Route::prefix('trade')->group(function () {
    // Execute auto trade
    Route::post('/execute', [TradingController::class, 'execute']);

    // Get status
    Route::get('/status', [TradingController::class, 'status']);

    // Trade history
    Route::get('/history', [TradingController::class, 'history']);

    // Trade logs
    Route::get('/logs', [TradingController::class, 'logs']);

    // Manual buy
    Route::post('/buy', [TradingController::class, 'buy']);

    // Close position
    Route::post('/close/{positionId}', [TradingController::class, 'close']);
});

// Multi-Coin Trading Routes
Route::prefix('multi-coin')->group(function () {
    // Execute for all 6 coins
    Route::post('/execute', [MultiCoinTradingController::class, 'execute']);

    // Get status for all coins
    Route::get('/status', [MultiCoinTradingController::class, 'status']);
});

// Trade Dashboard Routes
Route::prefix('dashboard')->group(function () {
    Route::get('/balance', [TradeDashboardController::class, 'getBalance']);
    Route::get('/purchases', [TradeDashboardController::class, 'getPurchases']);
    Route::get('/sales', [TradeDashboardController::class, 'getSales']);
    Route::get('/all-data', [TradeDashboardController::class, 'getAllData']);
    Route::get('/status', [TradeDashboardController::class, 'getStatus']);
});
