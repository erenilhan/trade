<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradeDashboardController;

Route::get('/', [TradeDashboardController::class, 'index'])->name('home');
Route::get('/trade-dashboard', [TradeDashboardController::class, 'index'])->name('trade.dashboard');
Route::get('/api/dashboard/data', [TradeDashboardController::class, 'getData'])->name('dashboard.data');
Route::get('/documentation', [TradeDashboardController::class, 'documentation'])->name('documentation');
Route::get('/about', [TradeDashboardController::class, 'about'])->name('about');
