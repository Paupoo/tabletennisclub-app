<?php

declare(strict_types=1);

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InterclubController;
use App\Http\Controllers\KnockoutPhaseController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\Tournament\ChangeTournamentStatusController;
use App\Http\Controllers\Tournament\ToggleHasPaidController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Models\Room;
use App\Models\Team;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Facades\Route;

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

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/results', [ResultsController::class, 'index'])->name('results');
Route::get('/events', [EventsController::class, 'index'])->name('events');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Routes pour les actualités
Route::get('/news', [NewsController::class, 'index'])->name('articles.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('articles.show');
Route::resource('/admin/articles', ArticleController::class)->middleware(['auth', 'verified']);


/**
 * Dashboard with sample of most data
 */
Route::get('/admin/dashboard', function () {
    return view('admin.dashboard', [
        'users' => User::latest()->take(5)->get(),
        'users_total_active' => User::where('is_active', '=', true)->count(),
        'users_total_inactive' => User::where('is_active', '=', false)->count(),
        'users_total_competitors' => User::where('is_competitor', '=', true)->count(),
        'users_total_casuals' => User::where('is_competitor', '=', false)->count(),
        'rooms' => Room::orderby('name')->get(),
        'trainings' => Training::latest()->take(5)->get(),
        'teams' => Team::all()->load(['captain', 'users']),
        'breadcrumbs' => [
            ['title' => 'Home', 'url' => route('dashboard'), 'icon' => 'home'],
            ['title' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'home']
        ],
    ]);
})->middleware(['auth', 'verified'])
    ->name('dashboard');
/**
 * Roles management
 */
Route::resource('admin/roles', RoleController::class)->middleware(['auth', 'verified']);

/**
 * Rooms management
 */
Route::resource('/admin/rooms', RoomController::class)->middleware(['auth', 'verified']);

/**
 * Profile management
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * Tables management
 */
Route::resource('/admin/tables', TableController::class)->middleware(['auth', 'verified']);

/**
 * Teams management
 */
Route::get('/admin/teams/team-builder', [
    TeamController::class,
    'initiateTeamsBuilder',
])->middleware(['auth', 'verified'])->name('teamBuilder.prepare');

Route::post('/admin/teams/team-builder', [
    TeamController::class,
    'validateTeamsBuilder',
])->middleware(['auth', 'verified'])->name('teamBuilder.create');

Route::post('/admin/teams/saveTeams', [
    TeamController::class,
    'saveTeams',
])->middleware(['auth', 'verified'])->name('saveTeams');

Route::resource('/admin/teams', TeamController::class)->middleware(['auth', 'verified']);

/**
 * Training management
 */
Route::resource('/admin/trainings', TrainingController::class)->middleware(['auth', 'verified']);

/**
 * Interclub management
 */
Route::post('admin/interclubs/subscribe', [
    InterclubController::class,
    'subscribe',
])
    ->middleware(['auth', 'verified'])
    ->name('interclubs.subscription');

Route::resource('/admin/interclubs', InterclubController::class)->middleware(['auth', 'verified']);

Route::post('/admin/interclub/add/{interclub}/{user}', [
    InterclubController::class,
    'addToSelection',
])->middleware(['auth', 'verified'])
    ->name('interclubs.addToSelection');

Route::post('/admin/interclub/toggle/{interclub}/{user}', [
    InterclubController::class,
    'toggleSelection',
])->middleware(['auth', 'verified'])
    ->name('interclubs.toggleSelection');

Route::get('/admin/interclub/selections', [
    InterclubController::class,
    'showSelections',
])->name('interclubs.selections');

/**
 * Users
 */
Route::get('/admin/users/setForceList', [
    UserController::class,
    'setForceList',
])->middleware(['auth', 'verified'])->name('setForceList');

Route::get('/admin/users/deleteForceList', [
    UserController::class,
    'deleteForceList',
])->middleware(['auth', 'verified'])->name('deleteForceList');

Route::get('/admin/{user}/subscription', [UserController::class, 'toggleHasPaid'])->name('users.toggleHaspaid');

Route::resource('admin/users', UserController::class)->middleware(['auth', 'verified']);

// Tournaments
Route::middleware(['auth', 'verified'])
    ->group(function (): void {
        // Tournament CRUD
        Route::get('/admin/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
        Route::get('/admin/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
        Route::post('/admin/tournaments/store', [TournamentController::class, 'store'])->name('tournaments.store');
        Route::put('/admin/tournaments/{tournament}/update', [TournamentController::class, 'update'])->name('tournaments.update');
        Route::get('/admin/tournament/{id}', [TournamentController::class, 'show'])->name('tournaments.show');
        Route::get('/admin/tournament/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournament.edit');
        Route::get('/admin/tournament/{tournament}/delete', [TournamentController::class, 'destroy'])->name('tournaments.destroy');

        // Tournament Actions
        Route::get('/admin/tournament/{tournament}/register/{user}', [TournamentController::class, 'registerUser'])->name('tournament.register');
        Route::get('/admin/tournament/{tournament}/unregister/{user}', [TournamentController::class, 'unregisterUser'])->name('tournament.unregister');
        Route::get('/admin/tournament/payment/{tournament}/{user}', ToggleHasPaidController::class)->name('tournaments.toggleHasPaid');

        // Others to sort
        Route::get('/admin/tournament/{id}/players', [TournamentController::class, 'showPlayers'])->name('tournamentShowPlayers');
        Route::get('/admin/tournament/{id}/pools', [TournamentController::class, 'showPools'])->name('tournamentShowPools');
        Route::get('/admin/tournament/{id}/matches', [TournamentController::class, 'showMatches'])->name('tournamentShowMatches');
        Route::get('/admin/tournament/{id}/tables', [TournamentController::class, 'showTables'])->name('tournamentShowTables');
        Route::get('/admin/tournament/{tournament}/erasePools', [TournamentController::class, 'erasePools'])->name('erasePools');
        Route::get('/admin/tournament/{tournament}/updateStatus/{newStatus}', ChangeTournamentStatusController::class)->name('tournament.changeStatus');
        // Route::get('/admin/tournament/{tournament}/draft', [TournamentController::class, 'unpublish'])->name('unpublishTournament'); // has been refactored with change status
        // Route::get('/admin/tournament/{tournament}/publish', [TournamentController::class, 'publish'])->name('publishTournament'); // has been refactored with change status
        // Route::get('/admin/tournament/{tournament}/start', [TournamentController::class, 'startTournament'])->name('startTournament'); // has been refactored with change status
        // Route::get('/admin/tournament/{tournament}/closed', [TournamentController::class, 'closeTournament'])->name('closeTournament'); // has been refactored with change status
        Route::get('/admin/tournament/{tournament}/set_max_players', [TournamentController::class, 'setMaxPlayers'])->name('tournamentSetMaxPlayers');
        Route::get('/admin/tournament/{tournament}/set_start_date', [TournamentController::class, 'setStartTime'])->name('tournamentSetStartTime');
        Route::get('/admin/tournament/{tournament}/set_end_date', [TournamentController::class, 'setEndTime'])->name('tournamentSetEndTime');
        Route::get('/admin/tournaments/{tournament}/pools', [TournamentController::class, 'managePools'])
            ->name('tournaments.manage-pools');
        Route::post('/admin/tournaments/{tournament}/generate-pools', [TournamentController::class, 'generatePools'])
            ->name('tournaments.generate-pools');

        Route::put('/admin/tournaments/{tournament}/generate-pools', [TournamentController::class, 'updatePoolPlayers'])
            ->name('tournament.updatePoolPlayers');

        // Routes pour les matches
        Route::post('/admin/tournaments/{tournament}/generate-matches', [TournamentController::class, 'generatePoolMatches'])
            ->name('generatePoolMatches');
        Route::get('/pools/{pool}/matches', [TournamentController::class, 'showPoolMatches'])
            ->name('showPoolMatches');
        Route::get('/matches/{match}/edit', [TournamentController::class, 'editMatch'])
            ->name('editMatch');
        Route::post('/matches/{match}/start', [TournamentController::class, 'startMatch'])
            ->name('startMatch');
        Route::put('/matches/{match}', [TournamentController::class, 'updateMatch'])
            ->name('updateMatch');
        Route::delete('/matches/{match}/reset', [TournamentController::class, 'resetMatch'])
            ->name('resetMatch');

        // Routes pour les tables
        Route::get('/admin/tournament/{tournament}/tables-overview', [TableController::class, 'tableOverview'])
            ->name('tablesOverview');

        // Routes pour la phase finale
        Route::get('/admin/tournaments/{tournament}/knockout/setup', [KnockoutPhaseController::class, 'setup'])
            ->name('knockoutSetup');
        Route::post('/admin/tournaments/{tournament}/knockout/configure', [KnockoutPhaseController::class, 'configure'])
            ->name('configureKnockout');
        Route::get('/admin/tournaments/{tournament}/knockout/bracket', [KnockoutPhaseController::class, 'showBracket'])
            ->name('knockoutBracket');
        Route::get('/knockout-matches/{match}/start', [KnockoutPhaseController::class, 'startMatch'])
            ->name('startKnockoutMatch');
        Route::delete('/knockout-matches/{match}/reset', [KnockoutPhaseController::class, 'resetMatch'])
            ->name('resetKnockoutMatch');
    });

Route::get('/test', function () {
    return view('test');
});


require __DIR__ . '/auth.php';
