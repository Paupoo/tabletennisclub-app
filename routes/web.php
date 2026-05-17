<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePayment;
use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\ConfirmSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkPaidSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkRefundSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\SubscribeToSeasonAction;
use App\Actions\ClubAdmin\Subscriptions\UnconfirmSubscriptionAction;
use App\Actions\User\InviteExistingUserAction;
use App\Http\Controllers\ClubAdmin\Club\RoomController;
use App\Http\Controllers\ClubAdmin\Club\TableController;
use App\Http\Controllers\ClubAdmin\Contact\ContactController;
use App\Http\Controllers\ClubAdmin\Contact\InvitationController;
use App\Http\Controllers\ClubAdmin\Payment\PaymentController;
use App\Http\Controllers\ClubAdmin\Payment\TransactionController;
use App\Http\Controllers\ClubAdmin\Subscription\RegistrationController;
use App\Http\Controllers\ClubAdmin\Subscription\SubscriptionController;
use App\Http\Controllers\ClubAdmin\Users\ProfileController;
use App\Http\Controllers\ClubAdmin\Users\UserController;
use App\Http\Controllers\ClubEvents\Interclub\InterclubController;
use App\Http\Controllers\ClubEvents\Interclub\ResultsController;
use App\Http\Controllers\ClubEvents\Interclub\SeasonController;
use App\Http\Controllers\ClubEvents\Interclub\TeamController;
use App\Http\Controllers\ClubEvents\Tournament\TableScoreController;
use App\Http\Controllers\ClubEvents\Tournament\TournamentController;
use App\Http\Controllers\ClubPosts\AdminEventPostController;
use App\Http\Controllers\ClubPosts\PublicEventPostController;
use App\Http\Controllers\ClubPosts\PublicNewsPostController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\ProtectAgainstSpam;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Team;
use App\Models\ClubEvents\Training\Training;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| Here are defined the routes accessible to all visitors of the website.
| These routes handle the public-facing pages such as the homepage,
| content display, and general user interactions.
|
*/
Route::get('/', [HomeController::class, 'index'])
    ->name('home');
Route::get('/results', [ResultsController::class, 'index'])
    ->name('results');
Route::get('/eventPosts', [PublicEventPostController::class, 'index'])
    ->name('eventPosts');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware(ProtectAgainstSpam::class, 'throttle:10,1')
    ->name('contact.store');

/*
|--------------------------------------------------------------------------
| Backoffice Routes
|--------------------------------------------------------------------------
|
| Here are defined the routes dedicated to the administration panel.
| These routes are restricted and allow authorized users to manage
| the website's content, settings, and internal features.
|
*/

Route::prefix('admin/my-space/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('{user}/profile', 'pages::club-admin.users.user-space.profile')->name('admin.user.profile');
        Route::livewire('{user}/settings', 'pages::club-admin.users.user-space.settings')->name('admin.user.settings');
        Route::livewire('{user}/teams', 'pages::club-admin.users.user-space.user-teams')->name('admin.user.teams');
        Route::livewire('{user}/calendar', 'pages::club-admin.users.user-space.calendar')->name('admin.user.calendar');
        Route::livewire('{user}/event-subscription', 'pages::club-admin.users.user-space.event-subscription')->name('admin.user.event-subscription');
        Route::livewire('{user}/registration-management', 'pages::club-admin.users.user-space.registration-management')->name('admin.user.registration-management');
    });

Route::prefix('admin/club-admin/users/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        // Users admin
        Route::livewire('list', 'pages::club-admin.users.index')->name('admin.users.index');
        Route::livewire('create', 'pages::club-admin.users.form')->name('admin.users.create');
        Route::livewire('{user}/edit', 'pages::club-admin.users.form')->name('admin.users.edit');
        Route::livewire('payments', 'pages::club-admin.users.payments')->name('admin.users.payments');
        Route::livewire('registrations', 'pages::club-admin.users.registrations')->name('admin.users.registrations');
    });
Route::prefix('admin/club-admin/')
    ->middleware(['auth', 'verified', 'can:update,App\Models\ClubEvents\Interclub\Club'])
    ->group(function (): void {
        Route::livewire('club-info', 'pages::club-admin.club-info')->name('admin.club-info');
    });

Route::prefix('admin/club-admin/rooms/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.rooms.index')->name('admin.rooms.index');

        Route::middleware('can:create,App\Models\ClubAdmin\Club\Room')
            ->group(function (): void {
                Route::livewire('create', 'pages::club-admin.rooms.form')->name('admin.rooms.create');
            });

        Route::middleware('can:update,room')
            ->group(function (): void {
                Route::livewire('{room}/edit', 'pages::club-admin.rooms.form')->name('admin.rooms.edit');
            });
    });

Route::prefix('admin/club-admin/tables/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.tables.index')->name('admin.tables.index');

        Route::middleware('can:update,table')
            ->group(function (): void {
                Route::livewire('{table}/edit', 'pages::club-admin.tables.form')->name('admin.tables.edit');
            });

        Route::middleware('can:create,App\Models\ClubAdmin\Club\Table')
            ->group(function (): void {
                Route::livewire('create', 'pages::club-admin.tables.form')->name('admin.tables.create');
            });
    });

Route::prefix('admin/club-events/interclubs/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('trainings', 'pages::club-events.trainings.index')->name('admin.trainings.index');
    });

Route::prefix('coach')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('trainings', 'pages::club-events.trainings.coach')->name('coach.trainings');
    });

Route::prefix('admin/club-events/tournaments')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('/', 'pages::club-events.tournaments.index')->name('admin.tournaments.index');
        Route::livewire('{tournament}/live-center', 'pages::club-events.tournaments.live-center')->name('admin.tournaments.live-center');
        Route::livewire('wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard');
        Route::livewire('{tournament}/wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard.edit');
    });

Route::prefix('admin/club-events/interclubs/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('captain-selection', 'pages::club-events.interclubs.captain-selection')->name('admin.interclubs.captain-selection');
        Route::livewire('control-center', 'pages::club-events.interclubs.control-center')->name('admin.interclubs.control-center');
        Route::livewire('teams', 'pages::club-events.interclubs.teams.index')->name('admin.interclubs.teams');
        Route::livewire('teams/builder', 'pages::club-events.interclubs.teams.builder')->name('admin.interclubs.teams.builder');
        Route::livewire('teams/{team}', 'pages::club-events.interclubs.teams.show')->name('admin.interclubs.teams.show');
        Route::livewire('teams/{team}/edit', 'pages::club-events.interclubs.teams.edit')->name('admin.interclubs.teams.edit');
        Route::livewire('results', 'pages::club-events.interclubs.results')->name('admin.interclubs.results');
    });

/*
|--------------------------------------------------------------------------
| Existing Routes Cleanup
|--------------------------------------------------------------------------
|
| The routes defined below are legacy or pre-existing routes.
| They should be reviewed, refactored, or removed to keep
| the routing file clean, consistent, and maintainable.
|
*/

/**
 * Dashboard with sample of most data
 */
Route::get('/admin/dashboard', function () {
    return view('clubAdmin.dashboard', [
        'users' => User::latest()->take(5)->get(),
        'users_total_active' => User::where('is_active', '=', true)->count(),
        'users_total_inactive' => User::where('is_active', '=', false)->count(),
        'users_total_competitors' => User::where('is_competitor', '=', true)->count(),
        'users_total_casuals' => User::where('is_competitor', '=', false)->count(),
        'rooms' => Room::orderby('name')->get(),
        'trainings' => Training::latest()->take(5)->get(),
        'teams' => Team::all()->load(['captain', 'users', 'league']),
        'breadcrumbs' => Breadcrumb::make()->home()->toArray(),
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
// Route::resource('/clubAdmin/club/tables', TableController::class)->middleware(['auth', 'verified']);

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

// Tournament email registration / waitlist actions (signed URLs, no auth required)
Route::get('/tournament/{tournament}/join/{user}', [TournamentController::class, 'registerViaEmail'])
    ->name('tournament.register.email')
    ->middleware('signed');

Route::get('/tournament/{tournament}/leave-waitlist/{user}', [TournamentController::class, 'leaveWaitlistViaEmail'])
    ->name('tournament.leave-waitlist.email')
    ->middleware('signed');

Route::get('/tournament/{tournament}/registration-confirmed', [TournamentController::class, 'registrationConfirmed'])
    ->name('tournament.registration.confirmed');

Route::get('/tournament/{tournament}/calendar.ics', [TournamentController::class, 'downloadIcal'])
    ->name('tournament.calendar.ical');

// Tournament QR table score (auth, URL stable = imprimable/affichable sur table)
Route::middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::get('/tournament/{tournament}/table/{table}/score', [TableScoreController::class, 'show'])
            ->name('tournament.table.score');
        Route::post('/tournament/{tournament}/table/{table}/score', [TableScoreController::class, 'submit'])
            ->name('tournament.table.score.submit');
    });

Route::prefix('admin/website')->middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('/articles', 'pages::website.articles.index')->name('admin.website.articles.index');
    Route::livewire('/articles/create', 'pages::website.articles.edit')->name('admin.website.articles.create');
    Route::livewire('/articles/{newsPost}/edit', 'pages::website.articles.edit')->name('admin.website.articles.edit');
    Route::livewire('/contacts', 'pages::website.contacts.index')->name('admin.website.contacts.index');
    Route::livewire('/spams', 'pages::website.spams.index')->name('admin.website.spams.index');
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
    Route::post('seasons/{season}/subscribe/', SubscribeToSeasonAction::class)->name('clubEvents.interclubs.seasons.subscribe');
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
