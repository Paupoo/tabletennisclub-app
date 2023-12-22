<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Resources\RoomController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Role;
use App\Models\Room;

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
    return view('/public/welcome');
})->name('welcome');

/**
 * Dashboard with sample of most data
 */
Route::get('/admin/dashboard', function () {
    return view('admin.dashboard', [
        'roles' => Role::orderby('name')->get(),
        'members' => User::latest()->take(5)->get(),
        'members_total_active' => User::where('is_active', '=', true)->count(),
        'members_total_inactive' => User::where('is_active', '=', false)->count(),
        'members_total_competitors' => User::where('is_competitor', '=', true)->count(),
        'members_total_casuals' => User::where('is_competitor', '=', false)->count(),
        'rooms' => Room::orderby('name')->get(),
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

/**
 * Rooms managements
 */
Route::resource('/admin/rooms', RoomController::class);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * Teams management
 */
Route::post('/admin/teams/proposeTeamsAmount', [
    TeamController::class, 
    'proposeTeamsAmount',
])->name('proposeTeamsAmount');

Route::get('/admin/teams/proposeTeamsCompositions', [
    TeamController::class, 
    'proposeTeamsCompositions',
])->name('proposeTeamsCompositions');

require __DIR__ . '/auth.php';
