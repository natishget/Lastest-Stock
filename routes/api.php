<?php

use App\Http\Controllers\CogsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/cogs', [CogsController::class, 'report'])->name('cogs.report');
});
