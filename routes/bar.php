<?php

declare(strict_types=1);

use App\Http\Controllers\Bar\BarCashSheetController;
use App\Http\Controllers\Bar\BarCategoryController;
use App\Http\Controllers\Bar\BarOrderController;
use App\Http\Controllers\Bar\BarPaymentController;
use App\Http\Controllers\ClubAdmin\Users\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bar Home
|--------------------------------------------------------------------------
*/
// Composant Livewire : le comptoir est une saisie en série — on y répète le même
// geste des dizaines de fois d'affilée, debout, avec quelqu'un en face. Chaque « + »
// était un rechargement complet de page.
Route::livewire('/', 'pages::bar.counter')
    ->name('index');

/*
|--------------------------------------------------------------------------
| Tabs — pour qui on sert
|--------------------------------------------------------------------------
*/
// Ouvrir, rejoindre et quitter une ardoise sont des actions du composant du
// comptoir : elles n'ont pas de route à elles. Les trois POST qui vivaient ici
// servaient la version Blade de l'écran de choix.
Route::post('/orders/{order}/rename', [BarOrderController::class, 'rename'])
    ->name('orders.rename');

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/
// Le ticket est un composant : ajouter, retirer, vider et clore sont ses actions,
// pas des routes. Les quatre POST qui vivaient ici servaient les formulaires de la
// version Blade, où chaque « + » coûtait un rechargement complet.
Route::livewire('/cart', 'pages::bar.cart')
    ->name('cart.show');
/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/
Route::get('/orders/{order}/payment', [BarPaymentController::class, 'show'])
    ->name('payment.show');
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
Route::delete('/orders/{order}', [BarOrderController::class, 'destroy'])
    ->middleware('can:bar.orders.manage')
    ->name('orders.destroy');
/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/
Route::prefix('categories')->middleware('can:bar.products.manage')->name('categories.')->group(function (): void {
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
Route::prefix('products')->middleware('can:bar.products.manage')->name('products.')->group(function (): void {
    // Une seule route : l'écran est un composant Livewire qui écrit lui-même.
    //
    // Les quatre routes POST/PUT/DELETE qui vivaient ici servaient les formulaires
    // de l'ancienne grille de tuiles, et `products/state` mettait une saisie de côté
    // le temps d'aller créer une catégorie — un détour que la modale a rendu inutile.
    // Elles sont parties avec BarProductController, qu'aucune d'elles n'atteignait
    // plus.
    Route::livewire('/', 'pages::bar.products')
        ->name('index');
});

/*
|--------------------------------------------------------------------------
| Cash sheet
|--------------------------------------------------------------------------
*/
Route::prefix('cashsheet')->middleware('can:bar.cash_sheet.send')->name('cashSheet.')->group(function (): void {
    Route::get('/', [BarCashSheetController::class, 'index'])
        ->name('index');
    Route::post('/send', [BarCashSheetController::class, 'send'])
        ->name('send');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');
