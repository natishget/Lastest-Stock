<?php

use App\Http\Controllers\CogsController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/cogs', [CogsController::class, 'report'])->name('cogs.report');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/financials', [DashboardController::class, 'financials'])->name('financials');
        Route::get('/top-products', [DashboardController::class, 'topProducts'])->name('top-products');
    });
});
