<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePayment;
use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\ConfirmSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkPaidSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkRefundSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\SubscribeToSeasonAction;
use App\Actions\ClubAdmin\Subscriptions\UnconfirmSubscriptionAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Controllers\ClubAdmin\Contact\ContactController;
use App\Http\Controllers\ClubAdmin\Contact\InvitationController;
use App\Http\Controllers\ClubAdmin\Payment\PaymentController;
use App\Http\Controllers\ClubAdmin\Payment\TransactionController;
use App\Http\Controllers\ClubAdmin\Subscription\RegistrationController;
use App\Http\Controllers\ClubAdmin\Subscription\SubscriptionController;
use App\Http\Controllers\ClubEvents\Interclub\ResultsController;
use App\Http\Controllers\ClubEvents\Interclub\SeasonController;
use App\Http\Controllers\ClubEvents\Meeting\MeetingPollController;
use App\Http\Controllers\ClubEvents\Meeting\MeetingRsvpController;
use App\Http\Controllers\ClubEvents\Tournament\TableScoreController;
use App\Http\Controllers\ClubEvents\Tournament\TournamentController;
use App\Http\Controllers\ClubPosts\PublicEventPostController;
use App\Http\Controllers\ClubPosts\PublicNewsPostController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\ProtectAgainstSpam;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup Wizard
|--------------------------------------------------------------------------
|
| Accessible to anyone on first installation. Blocked once setup is done.
|
*/
Route::livewire('/setup', 'pages::setup.wizard')
    ->middleware('setup.not_complete')
    ->name('setup');

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
Route::get('/clubPosts', [PublicNewsPostController::class, 'index'])
    ->name('public.clubPosts.index');
Route::get('/clubPosts/{slug}', [PublicNewsPostController::class, 'show'])
    ->name('public.clubPosts.show');
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
        Route::livewire('registrations', 'pages::club-admin.users.registrations')->name('admin.users.registrations');
        // Legacy redirect — kept for backward compatibility
        Route::get('payments', fn () => redirect()->route('admin.treasury.payments'))->name('admin.users.payments');
    });
Route::prefix('admin/treasury/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('payments', 'pages::club-admin.treasury.payments')->name('admin.treasury.payments');
        Route::livewire('transactions', 'pages::club-admin.treasury.transactions')->name('admin.treasury.transactions');
        Route::livewire('cash-register', 'pages::club-admin.treasury.cash-register')->name('admin.treasury.cash');
    });

Route::prefix('admin/club-admin/')
    ->middleware(['auth', 'verified', 'can:update,App\Models\ClubEvents\Interclub\Club'])
    ->group(function (): void {
        Route::livewire('club-info', 'pages::club-admin.club-info')->name('admin.club-info');
    });

Route::prefix('admin/club-admin/seasons/')
    ->middleware(['auth', 'verified', 'can:viewAny,App\Models\ClubEvents\Interclub\Season'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.seasons.index')->name('admin.seasons.index');
    });

Route::prefix('admin/club-admin/rooms/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.rooms.index')->name('admin.rooms.index');

        Route::middleware('can:create,' . Room::class)
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

        Route::middleware('can:create,' . Table::class)
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

Route::prefix('admin/club-events/meetings')
    ->middleware(['auth', 'verified', 'committee'])
    ->group(function (): void {
        Route::livewire('/', 'pages::club-events.meetings.index')->name('admin.meetings.index');
        Route::livewire('/create', 'pages::club-events.meetings.form')->name('admin.meetings.create');
        Route::livewire('/{meeting}', 'pages::club-events.meetings.show')->name('admin.meetings.show');
        Route::livewire('/{meeting}/edit', 'pages::club-events.meetings.form')->name('admin.meetings.edit');
    });

// Meeting signed-URL actions (no auth required)
Route::get('/meetings/{meeting}/poll/{user}', [MeetingPollController::class, 'show'])
    ->name('meetings.poll.vote')
    ->middleware('signed');
Route::post('/meetings/{meeting}/poll/{user}', [MeetingPollController::class, 'vote'])
    ->name('meetings.poll.vote.submit')
    ->middleware('signed');
Route::get('/meetings/{meeting}/rsvp/{user}/{response}', [MeetingRsvpController::class, 'handle'])
    ->name('meetings.rsvp')
    ->middleware('signed');

Route::prefix('admin/club-events/tournaments')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('/', 'pages::club-events.tournaments.index')->name('admin.tournaments.index');
        Route::livewire('{tournament}/live-center', 'pages::club-events.tournaments.live-center')->name('admin.tournaments.live-center');
        Route::middleware('committee')->group(function (): void {
            Route::livewire('wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard');
            Route::livewire('{tournament}/wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard.edit');
        });
    });

Route::prefix('admin/club-events/interclubs/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('captain-selection', 'pages::club-events.interclubs.captain-selection')->name('admin.interclubs.captain-selection');
        Route::livewire('control-center', 'pages::club-events.interclubs.control-center')->name('admin.interclubs.control-center');
        Route::livewire('my-matches', 'pages::club-events.interclubs.my-matches')->name('admin.interclubs.my-matches');
        Route::livewire('teams', 'pages::club-events.interclubs.teams.index')->name('admin.interclubs.teams');
        Route::livewire('teams/builder', 'pages::club-events.interclubs.teams.builder')->name('admin.interclubs.teams.builder');
        Route::livewire('teams/{team}', 'pages::club-events.interclubs.teams.show')->name('admin.interclubs.teams.show');
        Route::livewire('teams/{team}/edit', 'pages::club-events.interclubs.teams.edit')->name('admin.interclubs.teams.edit');
        Route::livewire('results', 'pages::club-events.interclubs.results')->name('admin.interclubs.results');
        Route::livewire('interclubs', 'pages::club-events.interclubs.interclubs')->name('admin.interclubs.interclubs');
        Route::livewire('division-setup', 'pages::club-events.interclubs.division-setup')->name('admin.interclubs.division-setup');
        Route::livewire('clubs', 'pages::club-events.interclubs.clubs')->name('admin.interclubs.clubs');
    });

/**
 * Notifications
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('admin/notifications', 'admin.notifications.index')->name('notifications.index');
});

/**
 * Invitations
 */
Route::get('/invitation/accept/{user}', [InvitationController::class, 'showForm'])
    ->name('invitation.accept')
    ->middleware('signed');
Route::post('/invitation/accept/{user}', [InvitationController::class, 'store'])
    ->name('invitation.store');

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
 * Dashboard with sample of most data (to implement, it's a mock (active) for now)
 */
Route::get('/admin/dashboard', function () {
    return view('clubAdmin.dashboard_v4_personas', [
        'members_total' => 42,
        'members_active' => 38,
        'members_inactive' => 4,
        'members_competitors' => 24,
        'members_unpaid' => 3,
        'rooms_count' => 3,
        'teams_count' => 4,
        'trainings_count' => 6,
        'interclubs_pending' => 2,
        'payments_pending' => 18,
        'affiliations_pending' => 5,
        'events_count' => 1,
        'recent_activity' => [
            ['type' => 'member',    'label' => 'Jean Dupont a rejoint le club',        'time' => '1h'],
            ['type' => 'contact',   'label' => 'Nouveau message de Pierre V.',         'time' => '3h'],
            ['type' => 'match',     'label' => 'Match BBW114 vs BBW210 planifié',      'time' => '5h'],
            ['type' => 'payment',   'label' => 'Cotisation payée par Marie L.',        'time' => '1j'],
            ['type' => 'news',      'label' => 'Article "Résultats printemps" publié', 'time' => '1j'],
            ['type' => 'selection', 'label' => 'Sélection équipe A envoyée',           'time' => '2j'],
            ['type' => 'meeting',   'label' => 'CR réunion comité du 22 mai ajouté',  'time' => '3j'],
            ['type' => 'member',    'label' => 'Sophie Martin inscrite',               'time' => '4j'],
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

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

Route::prefix('admin/website')->middleware(['auth', 'verified', 'committee'])->group(function (): void {
    Route::livewire('/articles', 'pages::website.articles.index')->name('admin.website.articles.index');
    Route::livewire('/articles/create', 'pages::website.articles.edit')->name('admin.website.articles.create');
    Route::livewire('/articles/{newsPost}/edit', 'pages::website.articles.edit')->name('admin.website.articles.edit');
    Route::livewire('/contacts', 'pages::website.contacts.index')->name('admin.website.contacts.index');
    Route::livewire('/spams', 'pages::website.spams.index')->name('admin.website.spams.index');
    Route::livewire('/events', 'pages::website.events.index')->name('admin.website.events.index');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    // ... autres routes admin existantes

    // (eventPosts admin routes moved earlier to match newsPosts routing structure)
});

/**
 * => obsolete, to clean and remove related code
 */
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
