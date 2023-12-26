<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Role;
use App\Models\Room;
use App\Models\Team;
use App\Models\Training;

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
        'trainings' => Training::latest()->take(5)->get(),
        'teams' => Team::all(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

/**
 * Users
 */
Route::post('/admin/members/setForceIndex', [
    UserController::class,
    'setForceIndex',
])->middleware(['auth', 'verified'])->name('setForceIndex');

Route::post('/admin/members/deleteForceIndex', [
    UserController::class,
    'deleteForceIndex',
])->middleware(['auth', 'verified'])->name('deleteForceIndex');

Route::resource('admin/members', UserController::class)->middleware(['auth', 'verified']);

/**
 * Roles management
 */

Route::resource('admin/roles', RoleController::class);

/**
 * Rooms managements
 */
Route::resource('/admin/rooms', RoomController::class)->middleware(['auth', 'verified']);

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
])->middleware(['auth', 'verified'])->name('proposeTeamsAmount');

Route::get('/admin/teams/proposeTeamsCompositions', [
    TeamController::class, 
    'proposeTeamsCompositions',
])->middleware(['auth', 'verified'])->name('proposeTeamsCompositions');

Route::get('/admin/teams/bulkComposer', function () {
    return view ('/admin/teams/bulk-composer');
})->middleware(['auth', 'verified'])->name('teamBulkComposer');

Route::resource('/admin/teams', TeamController::class)->middleware(['auth', 'verified']);

/**
 * Testing/temporary
 */
Route::get('/test', [
    RoomController::class, 'checkCapacity',
]);

require __DIR__ . '/auth.php';
