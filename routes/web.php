<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePayment;
use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\ConfirmSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkPaidSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkRefundSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\SubscribeToSeasonController;
use App\Actions\ClubAdmin\Subscriptions\UnconfirmSubscriptionAction;
use App\Actions\User\CreateNewUserAction;
use App\Actions\User\InviteExistingUserAction;
use App\Http\Controllers\ClubAdmin\Club\RoomController;
use App\Http\Controllers\ClubAdmin\Club\TableController;
use App\Http\Controllers\ClubAdmin\Contact\ContactAdminController;
use App\Http\Controllers\ClubAdmin\Contact\ContactController;
use App\Http\Controllers\ClubAdmin\Contact\InvitationController;
use App\Http\Controllers\ClubAdmin\Contact\SpamController;
use App\Http\Controllers\ClubAdmin\Users\ProfileController;
use App\Http\Controllers\ClubAdmin\Users\UserController;
use App\Http\Controllers\ClubEvents\Interclub\InterclubController;
use App\Http\Controllers\ClubEvents\Interclub\ResultsController;
use App\Http\Controllers\ClubEvents\Interclub\TeamController;
use App\Http\Controllers\ClubEvents\Tournament\ChangeTournamentStatusController;
use App\Http\Controllers\ClubEvents\Tournament\KnockoutPhaseController;
use App\Http\Controllers\ClubEvents\Tournament\ToggleHasPaidController;
use App\Http\Controllers\ClubEvents\Tournament\TournamentController;
use App\Http\Controllers\ClubEvents\Training\TrainingController;
use App\Http\Controllers\ClubPosts\AdminNewsPostController;
use App\Http\Controllers\ClubPosts\PublicNewsPostController;
use App\Http\Controllers\ClubPosts\AdminEventPostController;
use App\Http\Controllers\ClubPosts\PublicEventPostController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ClubAdmin\Payment\PaymentController;
use App\Http\Controllers\ClubAdmin\Subscription\RegistrationController;
use App\Http\Controllers\ClubEvents\Interclub\SeasonController;
use App\Http\Controllers\ClubAdmin\Subscription\SubscriptionController;
use App\Http\Controllers\ClubEvents\Training\TrainingPackController;
use App\Http\Controllers\ClubAdmin\Payment\TransactionController;
use App\Http\Middleware\ProtectAgainstSpam;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Team;
use App\Models\ClubEvents\Training\Training;
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

/** Public routes */
Route::get('/', [HomeController::class, 'index'])
    ->name('home');
Route::get('/results', [ResultsController::class, 'index'])
    ->name('results');
Route::get('/eventPosts', [PublicEventPostController::class, 'index'])
    ->name('eventPosts');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware(ProtectAgainstSpam::class)
    ->name('contact.store');

/**
 * Dashboard with sample of most data
 */
Route::get('/clubAdmin/dashboard', function () {
    return view('clubAdmin.dashboard', [
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
            ['title' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'home'],
        ],
    ]);
})->middleware(['auth', 'verified'])
    ->name('dashboard');

/**
 * Rooms management
 */
Route::resource('/clubAdmin/club/rooms', RoomController::class)->middleware(['auth', 'verified']);

/**
 * This route is used to manage clubPosts in the clubAdmin panel.
 * It allows authenticated and verified users to perform CRUD operations on clubPosts.
 * The clubPosts  are stored in the database and can be created, read, updated, and deleted through this interface.
 * This route is protected by authentication and verification middleware.
 */
Route::prefix('clubPosts')->middleware('auth')->group(function (): void {
    // Admin NewsPosts
    Route::resource('newsPosts', AdminNewsPostController::class)->names('clubPosts.newsPosts');
    Route::patch('newsPosts/{newspost}/publish', [AdminNewsPostController::class, 'publish'])->name('clubPosts.newsPosts.publish');
    Route::patch('newsPosts/{newspost}/archive', [AdminNewsPostController::class, 'archive'])->name('clubPosts.newsPosts.archive');
    Route::post('newsPosts/{newspost}/duplicate', [AdminNewsPostController::class, 'duplicate'])->name('clubPosts.newsPosts.duplicate');
    // Admin EventPosts
    Route::resource('eventPosts', AdminEventPostController::class)->names('clubPosts.eventPosts');
    Route::patch('eventPosts/{event}/publish', [AdminEventPostController::class, 'publish'])->name('clubPosts.eventPosts.publish');
    Route::patch('eventPosts/{event}/archive', [AdminEventPostController::class, 'archive'])->name('clubPosts.eventPosts.archive');
    Route::post('eventPosts/{event}/duplicate', [AdminEventPostController::class, 'duplicate'])->name('clubPosts.eventPosts.duplicate');
});

/**
 * Articles management (public)
 */
Route::get('/clubPosts', [PublicNewsPostController::class, 'index'])->name('public.clubPosts.index');
Route::get('/clubPosts/{slug}', [PublicNewsPostController::class, 'show'])->name('public.clubPosts.show');

/**
 * Profile management
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/invitation/accept/{user}', [InvitationController::class, 'showForm'])
    ->name('invitation.accept')
    ->middleware('signed');
Route::post('/invitation/accept/{user}', [InvitationController::class, 'store'])
    ->name('invitation.store');

/**
 * Tables management
 */
Route::resource('/clubAdmin/club/tables', TableController::class)->middleware(['auth', 'verified']);

/**
 * Teams management
 */
Route::get('/clubEvents/interclubs/teams/team-builder', [
    TeamController::class,
    'initiateTeamsBuilder',
])->middleware(['auth', 'verified'])->name('teamBuilder.prepare');

Route::post('/clubEvents/interclubs/teams/team-builder', [
    TeamController::class,
    'validateTeamsBuilder',
])->middleware(['auth', 'verified'])->name('teamBuilder.create');

Route::post('clubEvents/interclubs/teams/saveTeams', [
    TeamController::class,
    'saveTeams',
])->middleware(['auth', 'verified'])->name('saveTeams');

Route::resource('clubEvents/interclubs/teams', TeamController::class)->middleware(['auth', 'verified']);

/**
 * Training management
 */
Route::resource('/clubEvents/trainings', TrainingController::class)
    ->middleware(['auth', 'verified']);
Route::get('/clubAdmin/trainings/{training}/register', [TrainingController::class, 'register'])
    ->middleware(['auth', 'verified'])
    ->name('trainings.register');
Route::get('/clubAdmin/trainings/{training}/unregister', [TrainingController::class, 'unregister'])
    ->middleware(['auth', 'verified'])
    ->name('trainings.unregister');

/**
 * Interclub management
 */
Route::post('clubEvents/interclubs/subscribe', [
    InterclubController::class,
    'subscribe',
])
    ->middleware(['auth', 'verified'])
    ->name('interclubs.subscription');

Route::resource('clubEvents/interclubs', InterclubController::class)->middleware(['auth', 'verified']);

Route::post('/clubEvents/interclub/add/{interclub}/{user}', [
    InterclubController::class,
    'addToSelection',
])->middleware(['auth', 'verified'])
    ->name('interclubs.addToSelection');

Route::post('/clubEvents/interclub/toggle/{interclub}/{user}', [
    InterclubController::class,
    'toggleSelection',
])->middleware(['auth', 'verified'])
    ->name('interclubs.toggleSelection');

Route::get('/clubEvents/interclub/selections', [
    InterclubController::class,
    'showSelections',
])->name('interclubs.selections');

/**
 * Users
 */
Route::get('/clubAdmin/users/setForceList', [
    UserController::class,
    'setForceList',
])->middleware(['auth', 'verified'])->name('setForceList');

Route::get('/clubAdmin/users/deleteForceList', [
    UserController::class,
    'deleteForceList',
])->middleware(['auth', 'verified'])->name('deleteForceList');

Route::get('/clubAdmin/{user}/subscription', [UserController::class, 'toggleHasPaid'])->name('users.toggleHasPaid');

Route::resource('clubAdmin/users', UserController::class)->middleware(['auth', 'verified']);

Route::post('clubAdmin/users/{user}/invite', [InviteExistingUserAction::class, 'handle'])->name('clubAdmin.users.invite-existing-user');

// Tournaments
Route::middleware(['auth', 'verified'])
    ->group(function (): void {
        // Tournament CRUD
        Route::get('/clubEvents/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
        Route::get('/clubEvents/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
        Route::post('/clubEvents/tournaments/store', [TournamentController::class, 'store'])->name('tournaments.store');
        Route::put('/clubEvents/tournaments/{tournament}/update', [TournamentController::class, 'update'])->name('tournaments.update');
        Route::get('/clubEvents/tournament/{id}', [TournamentController::class, 'show'])->name('tournaments.show');
        Route::get('/clubEvents/tournament/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournament.edit');
        Route::get('/clubEvents/tournament/{tournament}/delete', [TournamentController::class, 'destroy'])->name('tournaments.destroy');

        // Tournament Actions
        Route::get('/clubEvents/tournament/{tournament}/register/{user}', [TournamentController::class, 'registerUser'])->name('tournament.register');
        Route::get('/clubEvents/tournament/{tournament}/unregister/{user}', [TournamentController::class, 'unregisterUser'])->name('tournament.unregister');
        Route::get('/clubEvents/tournament/payment/{tournament}/{user}', ToggleHasPaidController::class)->name('tournaments.toggleHasPaid');

        // Others to sort
        Route::get('/clubEvents/tournament/{id}/players', [TournamentController::class, 'showPlayers'])->name('tournamentShowPlayers');
        Route::get('/clubEvents/tournament/{id}/pools', [TournamentController::class, 'showPools'])->name('tournamentShowPools');
        Route::get('/clubEvents/tournament/{id}/matches', [TournamentController::class, 'showMatches'])->name('tournamentShowMatches');
        Route::get('/clubEvents/tournament/{id}/tables', [TournamentController::class, 'showTables'])->name('tournamentShowTables');
        Route::get('/clubEvents/tournament/{tournament}/erasePools', [TournamentController::class, 'erasePools'])->name('erasePools');
        Route::get('/clubEvents/tournament/{tournament}/updateStatus/{newStatus}', ChangeTournamentStatusController::class)->name('tournament.changeStatus');
        // Route::get('/clubEvents/tournament/{tournament}/draft', [TournamentController::class, 'unpublish'])->name('unpublishTournament'); // has been refactored with change status
        // Route::get('/clubEvents/tournament/{tournament}/publish', [TournamentController::class, 'publish'])->name('publishTournament'); // has been refactored with change status
        // Route::get('/clubEvents/tournament/{tournament}/start', [TournamentController::class, 'startTournament'])->name('startTournament'); // has been refactored with change status
        // Route::get('/clubEvents/tournament/{tournament}/closed', [TournamentController::class, 'closeTournament'])->name('closeTournament'); // has been refactored with change status
        Route::get('/clubEvents/tournament/{tournament}/set_max_players', [TournamentController::class, 'setMaxPlayers'])->name('tournamentSetMaxPlayers');
        Route::get('/clubEvents/tournament/{tournament}/set_start_date', [TournamentController::class, 'setStartTime'])->name('tournamentSetStartTime');
        Route::get('/clubEvents/tournament/{tournament}/set_end_date', [TournamentController::class, 'setEndTime'])->name('tournamentSetEndTime');
        Route::get('/clubEvents/tournaments/{tournament}/pools', [TournamentController::class, 'managePools'])
            ->name('tournaments.manage-pools');
        Route::post('/clubEvents/tournaments/{tournament}/generate-pools', [TournamentController::class, 'generatePools'])
            ->name('tournaments.generate-pools');

        Route::put('/clubEvents/tournaments/{tournament}/generate-pools', [TournamentController::class, 'updatePoolPlayers'])
            ->name('tournament.updatePoolPlayers');

        // Routes pour les matches
        Route::post('/clubEvents/tournaments/{tournament}/generate-matches', [TournamentController::class, 'generatePoolMatches'])
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
        Route::get('/clubEvents/tournament/{tournament}/tables-overview', [TableController::class, 'tableOverview'])
            ->name('tablesOverview');

        // Routes pour la phase finale
        Route::get('/clubEvents/tournaments/{tournament}/knockout/setup', [KnockoutPhaseController::class, 'setup'])
            ->name('knockoutSetup');
        Route::post('/clubEvents/tournaments/{tournament}/knockout/configure', [KnockoutPhaseController::class, 'configure'])
            ->name('configureKnockout');
        Route::get('/clubEvents/tournaments/{tournament}/knockout/bracket', [KnockoutPhaseController::class, 'showBracket'])
            ->name('knockoutBracket');
        Route::get('/knockout-matches/{match}/start', [KnockoutPhaseController::class, 'startMatch'])
            ->name('startKnockoutMatch');
        Route::delete('/knockout-matches/{match}/reset', [KnockoutPhaseController::class, 'resetMatch'])
            ->name('resetKnockoutMatch');
    });

Route::prefix('clubAdmin')->middleware(['auth', 'verified'])->group(function (): void {
    Route::resource('contacts', ContactAdminController::class)->names('clubAdmin.contacts');
    Route::post('contacts/create-new-user', [CreateNewUserAction::class, 'handle'])->name('clubAdmin.contacts.invite-new-user');
    Route::post('/{contact}/send-email', [ContactAdminController::class, 'sendEmail'])->name('clubAdmin.contacts.send-email');
    Route::get('/{contact}/compose-email', [ContactAdminController::class, 'composeEmail'])->name('clubAdmin.contacts.compose-email');
    Route::post('/{contact}/send-custom-email', [ContactAdminController::class, 'sendCustomEmail'])->name('clubAdmin.contacts.send-custom-email');
    Route::resource('trainingpacks', TrainingPackController::class)->names('admin.trainingpacks');
});

Route::prefix('clubAdmin')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('spams', [SpamController::class, 'index'])->name('clubAdmin.spams.index');
    Route::post('contacts/create-new-user', [CreateNewUserAction::class, 'handle'])->name('clubAdmin.contacts.invite-new-user');
    Route::post('/{contact}/send-email', [ContactAdminController::class, 'sendEmail'])->name('clubAdmin.contacts.send-email');
    Route::get('/{contact}/compose-email', [ContactAdminController::class, 'composeEmail'])->name('clubAdmin.contacts.compose-email');
    Route::post('/{contact}/send-custom-email', [ContactAdminController::class, 'sendCustomEmail'])->name('clubAdmin.contacts.send-custom-email');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    // ... autres routes admin existantes

    // (eventPosts admin routes moved earlier to match newsPosts routing structure)
});

Route::get('/test', function () {
    return view('test', ['breadcrumbs' => []]);
});
Route::get('/test2', function () {
    return view('wizzard', ['breadcrumbs' => []]);
})->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::resource('seasons', SeasonController::class)->names('clubEvents.interclubs.seasons');
    Route::resource('registrations', RegistrationController::class)->names('clubAdmin.registrations');
    Route::resource('subscriptions', SubscriptionController::class)->names('clubAdmin.subscriptions');
    Route::resource('payments', PaymentController::class)->names('admin.payments');
    Route::post('seasons/{season}/subscribe/', SubscribeToSeasonController::class)->name('clubEvents.interclubs.seasons.subscribe');
    Route::post('seasons/{season}/unsubscribe', [SubscriptionController::class, 'unsubscribe'])->name('clubAdmin.subscriptions.unsubscribe');
    Route::post('subscriptions/sendPaymentInvite/', [PaymentController::class, 'sendInvite'])->name('clubAdmin.subscriptions.sendPaymentInvite');
    Route::post('subscriptions/{subscription}/confirm', ConfirmSubscriptionAction::class)->name('clubAdmin.subscriptions.confirm');
    Route::post('subscriptions/{subscription}/unconfirm', UnconfirmSubscriptionAction::class)->name('clubAdmin.subscriptions.unconfirm');
    Route::post('subscriptions/{subscription}/cancel', CancelSubscriptionAction::class)->name('clubAdmin.subscriptions.cancel');
    Route::post('subscriptions/{subscription}/markPaid', MarkPaidSubscriptionAction::class)->name('clubAdmin.subscriptions.markPaid');
    Route::post('subscriptions/{subscription}/markRefunded', MarkRefundSubscriptionAction::class)->name('clubAdmin.subscriptions.markRefunded');
    Route::post('payments/{subscription}/generate', GeneratePayment::class)->name('admin.subscription.generatePayment');
    Route::post('subscription/{subscription}/addTrainingPack', [SubscriptionController::class, 'syncTrainingPacks'])->name('clubAdmin.subscriptions.addTrainingPack');
    Route::get('/admin/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
        ->name('clubAdmin.subscriptions.show');
});

Route::prefix('admin/transactions')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('add', [TransactionController::class, 'add'])->name('admin.transactions.add ');
    Route::post('upload', [TransactionController::class, 'upload'])->name('admin.transactions.upload');
    Route::get('/', [TransactionController::class, 'index'])->name('admin.transactions.index');
    Route::get('/reconcile', [TransactionController::class, 'reconcile'])->name('admin.transactions.reconcile');
    Route::post('/reconcile', [TransactionController::class, 'reconcileStore'])->name('admin.transactions.reconcile.store');
});

require __DIR__ . '/auth.php';
