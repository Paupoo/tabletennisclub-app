<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/dashboard', function () {
    return view('admin.dashboard', [
        'roles' => Role::orderby('name')->get(),
        'members' => User::latest()->take(5)->get(),
        'members_total' => User::count(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

/**
 * Users
 */
Route::post('/admin/members/setForceIndex', [
    UserController::class,
    'setForceIndex',
])->name('setForceIndex');

Route::post('/admin/members/deleteForceIndex', [
    UserController::class,
    'deleteForceIndex',
])->name('deleteForceIndex');

Route::resource('admin/members', UserController::class);

/**
 * Roles management
 */

Route::resource('admin/roles', RoleController::class);


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
