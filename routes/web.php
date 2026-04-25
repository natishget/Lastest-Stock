<?php

use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\PurchaseManagementController;
use App\Http\Controllers\SalesManagementController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'admin'])->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductManagementController::class, 'index'])->name('index');
    Route::post('/', [ProductManagementController::class, 'store'])->name('store');
    Route::post('/{product}/variants', [ProductManagementController::class, 'storeVariant'])->name('variants.store');
    Route::put('/{product}', [ProductManagementController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductManagementController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('purchases')->name('purchases.')->group(function () {
    Route::get('/', [PurchaseManagementController::class, 'index'])->name('index');
    Route::post('/', [PurchaseManagementController::class, 'store'])->name('store');
});

Route::middleware(['auth'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', [SalesManagementController::class, 'index'])->name('index');
    Route::post('/', [SalesManagementController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->name('index');
    Route::post('/', [UserManagementController::class, 'store'])->name('store');
    Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
