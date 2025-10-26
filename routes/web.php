<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradeDashboardController;

// Dashboard routes (no password protection)
Route::get('/', [TradeDashboardController::class, 'index'])->name('home');
Route::get('/trade-dashboard', [TradeDashboardController::class, 'index'])->name('trade.dashboard');
Route::get('/dashboard', [TradeDashboardController::class, 'index'])->name('dashboard');
Route::get('/api/dashboard/data', [TradeDashboardController::class, 'getData'])->name('dashboard.data');
Route::get('/documentation', [TradeDashboardController::class, 'documentation'])->name('documentation');
Route::get('/about', [TradeDashboardController::class, 'about'])->name('about');

// Performance Analytics
Route::get('/analytics', [\App\Http\Controllers\PerformanceAnalyticsController::class, 'index'])->name('analytics');
Route::get('/api/analytics/data', [\App\Http\Controllers\PerformanceAnalyticsController::class, 'getData'])->name('analytics.data');
