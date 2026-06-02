<?php

declare(strict_types=1);

use App\Http\Controllers\Bar\BarCartController;
use App\Http\Controllers\Bar\BarCashSheetController;
use App\Http\Controllers\Bar\BarCategoryController;
use App\Http\Controllers\Bar\BarController;
use App\Http\Controllers\Bar\BarOrderController;
use App\Http\Controllers\Bar\BarPaymentController;
use App\Http\Controllers\Bar\BarProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bar Home
|--------------------------------------------------------------------------
*/
Route::get('/', [BarController::class, 'index'])
    ->name('index');

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/
Route::post('/cart/add', [BarCartController::class, 'add'])
    ->name('cart.add');
Route::post('/cart/remove', [BarCartController::class, 'remove'])
    ->name('cart.remove');
Route::get('/cart', [BarCartController::class, 'show'])
    ->name('cart.show');
Route::post('/cart/clear', [BarCartController::class, 'clear'])
    ->name('cart.clear');
Route::post('/cart/validate', [BarCartController::class, 'validateOrder'])
    ->name('cart.validate');
Route::post('/cart/pay', [BarCartController::class, 'pay'])
    ->name('cart.pay');
/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/
Route::get('/orders/{order}/payment', [BarPaymentController::class, 'show'])
    ->name('payment.show');
Route::post('/orders/{order}/payment', [BarPaymentController::class, 'show'])
    ->name('payment.show.post');
Route::post('/orders/{order}/payment/pay', [BarPaymentController::class, 'pay'])
    ->name('payment.pay');
/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/
Route::get('/orders', [BarOrderController::class, 'index'])
    ->name('orders.index');
// Route::post('/orders/{order}/pay', [BarOrderController::class, 'pay'])
//     ->name('orders.pay');
Route::get('/orders/history', [BarOrderController::class, 'history'])
    ->name('orders.history');
Route::get('/orders/{order}/modify', [BarOrderController::class, 'modify'])
    ->name('orders.modify');
Route::post('/orders/cancel-edit', [BarOrderController::class, 'cancelEdit'])
    ->name('orders.cancelEdit');
Route::delete('/orders/{order}', [BarOrderController::class, 'destroy'])
    ->name('orders.destroy');
/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [BarCategoryController::class, 'index'])
        ->name('index');
    Route::post('/', [BarCategoryController::class, 'store'])
        ->name('store');
    Route::put('/{category}', [BarCategoryController::class, 'update'])
        ->name('update');
    Route::delete('/{category}', [BarCategoryController::class, 'destroy'])
        ->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [BarProductController::class, 'index'])
        ->name('index');
    Route::post('/', [BarProductController::class, 'store'])
        ->name('store');
    Route::put('/{product}', [BarProductController::class, 'update'])
        ->name('update');
    Route::delete('/{product}', [BarProductController::class, 'destroy'])
        ->name('destroy');
    Route::post('/products/state', [BarProductController::class, 'storeState'])
        ->name('storeState');
});

/*
|--------------------------------------------------------------------------
| Cash sheet
|--------------------------------------------------------------------------
*/
Route::prefix('cashsheet')->name('cashSheet.')->group(function () {
    Route::get('/', [BarCashSheetController::class, 'index'])
        ->name('index');
    Route::post('/bar/cashSheet/send', [BarCashSheetController::class, 'send'])
        ->name('send');
});
