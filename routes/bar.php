<?php

declare(strict_types=1);

use App\Http\Controllers\Bar\BarCashSheetController;
use App\Http\Controllers\Bar\BarCategoryController;
use App\Http\Controllers\Bar\BarOrderController;
use App\Http\Controllers\Bar\BarPaymentController;
use App\Http\Controllers\ClubAdmin\Users\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/* Bar Home */
Route::livewire('/', 'pages::bar.counter')->middleware('can:bar.orders.manage')->name('index');

/* Tabs */
Route::post('/orders/{order}/rename', [BarOrderController::class, 'rename'])->middleware('can:bar.orders.manage')->name('orders.rename');

/* Cart */
Route::livewire('/cart', 'pages::bar.cart')->middleware('can:bar.orders.manage')->name('cart.show');

/* Payments */
Route::get('/orders/{order}/payment', [BarPaymentController::class, 'show'])->middleware('can:bar.orders.pay')->name('payment.show');
Route::post('/orders/{order}/payment/pay', [BarPaymentController::class, 'pay'])->middleware('can:bar.orders.pay')->name('payment.pay');

/* Orders */
Route::get('/orders', [BarOrderController::class, 'index'])->middleware('can:bar.orders.manage')->name('orders.index');
Route::get('/orders/history', [BarOrderController::class, 'history'])->middleware('can:bar.orders.manage')->name('orders.history');
Route::get('/orders/{order}/modify', [BarOrderController::class, 'modify'])->middleware('can:bar.orders.manage')->name('orders.modify');
Route::delete('/orders/{order}', [BarOrderController::class, 'destroy'])->middleware('can:bar.orders.manage')->name('orders.destroy');

/* Categories */
Route::prefix('categories')->middleware('can:bar.categories.manage')->name('categories.')->group(function (): void {
    Route::get('/', [BarCategoryController::class, 'index'])->name('index');
    Route::post('/', [BarCategoryController::class, 'store'])->name('store');
    Route::put('/{category}', [BarCategoryController::class, 'update'])->name('update');
    Route::delete('/{category}', [BarCategoryController::class, 'destroy'])->name('destroy');
});

/* Products */
Route::prefix('products')->middleware('can:bar.products.manage')->name('products.')->group(function (): void {
    Route::livewire('/', 'pages::bar.products')->name('index');
});

/* Restocking */
Route::livewire('/restocking', 'pages::bar.restocking')->middleware('can:bar.restocking.shop')->name('restocking.index');

/* Inventories */
Route::prefix('inventories')->name('inventories.')->group(function (): void {
    Route::livewire('/', 'pages::bar.inventories')->withoutMiddleware('can:bar.access')->middleware('can:bar.stats.view')->name('index');
    Route::livewire('/current', 'pages::bar.inventory')->middleware('can:bar.stock.manage')->name('current');
    Route::livewire('/{inventory}', 'pages::bar.inventory-show')->withoutMiddleware('can:bar.access')->middleware('can:bar.stats.view')->whereNumber('inventory')->name('show');
});

/* Sales */
Route::livewire('/stats', 'pages::bar.stats')->withoutMiddleware('can:bar.access')->middleware('can:bar.stats.view')->name('stats.index');

/* Cash sheet */
Route::prefix('cashsheet')
    ->withoutMiddleware('can:bar.access')
    ->middleware('can:bar.cash_sheet.send')
    ->name('cashSheet.')
    ->group(function (): void {
        Route::get('/', [BarCashSheetController::class, 'index'])->name('index');
        Route::post('/send', [BarCashSheetController::class, 'send'])->name('send');
        Route::get('/download', [BarCashSheetController::class, 'download'])->name('download');
    });

/* Logout */
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
