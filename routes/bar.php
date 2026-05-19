<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Bar\BarController;
use App\Http\Controllers\Bar\BarCategoryController;
use App\Http\Controllers\Bar\BarProductController;

Route::get('/', [BarController::class, 'index'])->name('index');

/*
|--------------------------------------------------------------------------
| Bar Categories
|--------------------------------------------------------------------------
*/
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [BarCategoryController::class, 'index'])->name('index');
    Route::post('/', [BarCategoryController::class, 'store'])->name('store');
    Route::put('/{category}', [BarCategoryController::class, 'update'])->name('update');
    Route::delete('/{category}', [BarCategoryController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Bar Products
|--------------------------------------------------------------------------
*/
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [BarProductController::class, 'index'])->name('index');
    Route::post('/', [BarProductController::class, 'store'])->name('store');
    Route::put('/{product}', [BarProductController::class, 'update'])->name('update');
    Route::delete('/{product}', [BarProductController::class, 'destroy'])->name('destroy');
});
