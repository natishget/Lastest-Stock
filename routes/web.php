<?php

use App\Http\Controllers\CogsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\PurchaseManagementController;
use App\Http\Controllers\SalesManagementController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/financials', [DashboardController::class, 'financials'])->name('dashboard.web-financials');
    Route::get('dashboard/top-products', [DashboardController::class, 'topProducts'])->name('dashboard.web-top-products');
});

Route::middleware(['auth', 'role:ADMIN,AUDITOR'])->group(function () {
    Route::get('cogs', [CogsController::class, 'index'])->name('cogs.index');
    Route::get('cogs/report', [CogsController::class, 'report'])->name('cogs.web-report');
    Route::get('cogs/export-csv', [CogsController::class, 'exportCsv'])->name('cogs.export-csv');
});

Route::middleware(['auth', 'role:ADMIN,AUDITOR'])->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductManagementController::class, 'index'])->name('index');
});

Route::middleware(['auth', 'admin'])->prefix('products')->name('products.')->group(function () {
    Route::get('/import', [ProductManagementController::class, 'import'])->name('import');
    Route::post('/import', [ProductManagementController::class, 'storeImport'])->name('import.store');
    Route::post('/', [ProductManagementController::class, 'store'])->name('store');
    Route::post('/variants', [ProductManagementController::class, 'storeVariant'])->name('variants.store');
    Route::put('/{product}', [ProductManagementController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductManagementController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'role:ADMIN,SALES,AUDITOR'])->prefix('purchases')->name('purchases.')->group(function () {
    Route::get('/', [PurchaseManagementController::class, 'index'])->name('index');
});

Route::middleware(['auth', 'role:ADMIN,SALES'])->prefix('purchases')->name('purchases.')->group(function () {
    Route::post('/', [PurchaseManagementController::class, 'store'])->name('store');
    Route::put('/{purchase}', [PurchaseManagementController::class, 'update'])->name('update');
    Route::post('/{purchase}/void', [PurchaseManagementController::class, 'void'])->name('void');
    Route::post('/{purchase}/returns', [PurchaseManagementController::class, 'returnItems'])->name('returns.store');
});

Route::middleware(['auth', 'role:ADMIN,SALES,AUDITOR'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', [SalesManagementController::class, 'index'])->name('index');
});

Route::middleware(['auth', 'role:ADMIN,SALES'])->prefix('sales')->name('sales.')->group(function () {
    Route::post('/', [SalesManagementController::class, 'store'])->name('store');
    Route::put('/{sale}', [SalesManagementController::class, 'update'])->name('update');
    Route::post('/{sale}/void', [SalesManagementController::class, 'void'])->name('void');
    Route::post('/{sale}/returns', [SalesManagementController::class, 'returnItems'])->name('returns.store');
});

Route::middleware(['auth', 'admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->name('index');
    Route::post('/', [UserManagementController::class, 'store'])->name('store');
    Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'role:ADMIN,SALES,AUDITOR'])->group(function () {
    Route::get('/warehouses', function () {
        return Inertia::render('warehouses/index');
    })->name('warehouses.index');
});

Route::middleware(['auth', 'role:ADMIN,SALES,AUDITOR'])->prefix('api/warehouses')->name('api.warehouses.')->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('index');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
    Route::get('/{warehouse}/stock', [WarehouseController::class, 'stock'])->name('stock');
});

Route::middleware(['auth', 'admin'])->prefix('api/warehouses')->name('api.warehouses.')->group(function () {
    Route::post('/', [WarehouseController::class, 'store'])->name('store');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
