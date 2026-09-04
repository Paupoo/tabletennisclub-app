<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\SubscribeToSeasonAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Http\Controllers\ClubAdmin\Contact\ContactController;
use App\Http\Controllers\ClubAdmin\Contact\InvitationController;
use App\Http\Controllers\ClubAdmin\DashboardController;
use App\Http\Controllers\ClubAdmin\Users\UserDocumentController;
use App\Http\Controllers\ClubEvents\Interclub\ResultsController;
use App\Http\Controllers\ClubEvents\Meeting\MeetingPollController;
use App\Http\Controllers\ClubEvents\Meeting\MeetingRsvpController;
use App\Http\Controllers\ClubEvents\Tournament\TableScoreController;
use App\Http\Controllers\ClubEvents\Tournament\TournamentController;
use App\Http\Controllers\ClubEvents\Tournament\TournamentPrintController;
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

// Le flux ICS personnel (admin.user.calendar.ics) est déclaré dans
// bootstrap/app.php : il vit hors du groupe `web`, parce qu'un fichier lu par
// une machine n'a besoin ni de session, ni de cookie, ni de jeton CSRF.

Route::prefix('admin/my-space/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        // Onboarding wizard — always self (no {user} binding), exempt from profile.complete.
        Route::livewire('onboarding', 'pages::club-admin.users.user-space.onboarding')->name('admin.user.onboarding');
        Route::livewire('{user}/profile', 'pages::club-admin.users.user-space.profile')->name('admin.user.profile');
        Route::livewire('{user}/settings', 'pages::club-admin.users.user-space.settings')->name('admin.user.settings');
        Route::livewire('{user}/teams', 'pages::club-admin.users.user-space.user-teams')->name('admin.user.teams');
        Route::livewire('{user}/calendar', 'pages::club-admin.users.user-space.calendar')->name('admin.user.calendar');
        Route::livewire('{user}/event-subscription', 'pages::club-admin.users.user-space.event-subscription')->name('admin.user.event-subscription');
        Route::livewire('{user}/registration-management', 'pages::club-admin.users.user-space.registration-management')->name('admin.user.registration-management');
        Route::livewire('{user}/reglement', 'pages::club-admin.users.user-space.reglement')->name('admin.user.reglement');
        Route::livewire('{user}/directory', 'pages::club-admin.users.user-space.directory')->name('admin.user.directory');
        Route::livewire('{user}/payments', 'pages::club-admin.users.user-space.payments')->name('admin.user.payments');
        // Private member documents — authorization handled in the controller
        // (self, admin, committee, guardians), not limited to the my-space owner.
        Route::get('{user}/documents/{type}', [UserDocumentController::class, 'download'])->name('admin.user.documents.download');
    });

// Help — every signed-in member. The library is not split per role: each task is
// written once and tagged, and HelpAudience decides what a given member is shown.
Route::prefix('admin/aide')
    ->middleware(['auth', 'verified', 'feature:help_centre'])
    ->group(function (): void {
        Route::livewire('/', 'pages::help.index')->name('admin.help.index');
        Route::livewire('{slug}', 'pages::help.show')->name('admin.help.show');
    });

// Members administration — reserved to the management committee (admins + committee members).
// Directly reachable by URL, so the whole group is gated here (the nav only hides the links).
Route::prefix('admin/club-admin/users/')
    ->middleware(['auth', 'verified'])
    ->group(function (): void {
        // The directory is the committee baseline; creating and editing members
        // is the `membres` délégation.
        Route::livewire('list', 'pages::club-admin.users.index')
            ->middleware('can:users.view')
            ->name('admin.users.index');
        Route::livewire('create', 'pages::club-admin.users.form')
            ->middleware('can:users.create')
            ->name('admin.users.create');
        // Two duties, one screen: whoever keeps the member's data up to date, and
        // whoever hands out their rights. Neither holds the other's permission,
        // and the form renders only the sections the visitor may actually write.
        Route::livewire('{user}/edit', 'pages::club-admin.users.form')
            ->middleware('can.any:users.update,access.manage')
            ->name('admin.users.edit');
        // Seeding the roster from the federation listing is creating members in
        // bulk, and belongs to whoever may create them one at a time.
        Route::livewire('import', 'pages::club-admin.users.import')
            ->middleware('can:users.import')
            ->name('admin.users.import');
        Route::livewire('registrations', 'pages::club-admin.users.registrations')
            ->middleware('can:subscriptions.view')
            ->name('admin.users.registrations');
        // Who holds what: readable by whoever hands the duties out, and by whoever
        // edits the members — the overview is where both go to check coverage.
        // Read-only on purpose: assigning happens on the member's own form.
        Route::livewire('delegations', 'pages::club-admin.users.delegations')
            ->middleware('can.any:users.update,access.manage')
            ->name('admin.users.delegations');
        // Season roster — readable at the committee baseline, editing reserved to
        // the members délégation (guarded inside the component).
        Route::livewire('roster', 'pages::club-admin.subscriptions.roster')
            ->middleware('can:subscriptions.view')
            ->name('admin.subscriptions.roster');
        // Legacy redirect — kept for backward compatibility
        Route::get('payments', fn () => redirect()->route('admin.treasury.payments'))->name('admin.users.payments');
    });
// Season planning board — visible to the whole committee, mutations reserved to managers (decision #18).
Route::prefix('admin/club-admin/planning/')
    ->middleware(['auth', 'verified', 'can:training_plans.manage', 'feature:training_planning'])
    ->group(function (): void {
        Route::livewire('board', 'pages::club-admin.planning.board')->name('admin.planning.board');
    });

Route::prefix('admin/treasury/')
    ->middleware(['auth', 'verified', 'feature:treasury'])
    ->group(function (): void {
        // Each screen answers to the délégation that owns it, not to committee
        // membership: holding the cash box and reconciling the accounts are two
        // distinct duties, and someone may well hold one without the other.
        Route::livewire('payments', 'pages::club-admin.treasury.payments')
            ->middleware('can:payments.view')
            ->name('admin.treasury.payments');

        Route::livewire('transactions', 'pages::club-admin.treasury.transactions')
            ->middleware('can:transactions.view')
            ->name('admin.treasury.transactions');

        Route::livewire('fines', 'pages::club-admin.treasury.fines')
            ->middleware('can:fines.view')
            ->name('admin.treasury.fines');

        // Was reachable by any verified member — balances included.
        Route::livewire('cash-register', 'pages::club-admin.treasury.cash-register')
            ->middleware(['feature:cash_register', 'can:cash_register.view'])
            ->name('admin.treasury.cash');
    });

// Audit log — readable by platform admins and the management committee (decision: audit access).
Route::prefix('admin/club-admin/audit/')
    ->middleware(['auth', 'verified', 'can:view-audit-log', 'feature:supervision'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.audit.index')->name('admin.audit.index');
    });

// Queue monitoring — pending/failed jobs and worker health, for admins and the committee.
Route::prefix('admin/club-admin/queue/')
    ->middleware(['auth', 'verified', 'can:view-queue-monitoring', 'feature:supervision'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.queue.index')->name('admin.queue.index');
    });

Route::prefix('admin/club-admin/')
    ->middleware(['auth', 'verified', 'can:update,App\Domains\Competitions\Interclub\Models\Club'])
    ->group(function (): void {
        Route::livewire('club-info', 'pages::club-admin.club-info')->name('admin.club-info');
    });

Route::prefix('admin/club-admin/seasons/')
    ->middleware(['auth', 'verified', 'can:viewAny,App\Domains\Competitions\Interclub\Models\Season'])
    ->group(function (): void {
        Route::livewire('list', 'pages::club-admin.seasons.index')->name('admin.seasons.index');
    });

Route::prefix('admin/club-admin/rooms/')
    ->middleware(['auth', 'verified', 'can:rooms.manage'])
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

        Route::livewire('{room}', 'pages::club-admin.rooms.show')->name('admin.rooms.show');
    });

// Tables have no list of their own: a table is always looked at through the
// room that holds it, on admin.rooms.show.
Route::prefix('admin/club-admin/tables/')
    ->middleware(['auth', 'verified', 'can:tables.manage'])
    ->group(function (): void {
        Route::middleware('can:update,table')
            ->group(function (): void {
                Route::livewire('{table}/edit', 'pages::club-admin.tables.form')->name('admin.tables.edit');
            });

        Route::middleware('can:create,' . Table::class)
            ->group(function (): void {
                Route::livewire('create', 'pages::club-admin.tables.form')->name('admin.tables.create');
            });
    });

// Training packs administration — committee only.
Route::prefix('admin/club-events/interclubs/')
    ->middleware(['auth', 'verified', 'can:trainings.manage', 'feature:trainings'])
    ->group(function (): void {
        Route::livewire('trainings', 'pages::club-events.trainings.index')->name('admin.trainings.index');
    });

// Coach's personal sessions — coaches (and admins for oversight).
Route::prefix('coach')
    ->middleware(['auth', 'verified', 'can:access-coach-area', 'feature:trainings'])
    ->group(function (): void {
        Route::livewire('trainings', 'pages::club-events.trainings.coach')->name('coach.trainings');
    });

Route::prefix('admin/club-events/meetings')
    ->middleware(['auth', 'verified', 'can:meetings.view', 'feature:meetings'])
    ->group(function (): void {
        Route::livewire('/', 'pages::club-events.meetings.index')->name('admin.meetings.index');
        Route::livewire('/create', 'pages::club-events.meetings.create')->name('admin.meetings.create');
        Route::livewire('/{meeting}', 'pages::club-events.meetings.show')->name('admin.meetings.show');
        Route::livewire('/{meeting}/minutes', 'pages::club-events.meetings.minutes')->name('admin.meetings.minutes');
    });

// Meeting signed-URL actions (no auth required)
Route::get('/meetings/{meeting}/poll/{user}', [MeetingPollController::class, 'show'])
    ->name('meetings.poll.vote')
    ->middleware('signed');
Route::post('/meetings/{meeting}/poll/{user}', [MeetingPollController::class, 'vote'])
    ->name('meetings.poll.vote.submit')
    ->middleware('signed');
Route::get('/meetings/{meeting}/rsvp/{user}', [MeetingRsvpController::class, 'show'])
    ->name('meetings.rsvp')
    ->middleware('signed');
Route::post('/meetings/{meeting}/rsvp/{user}', [MeetingRsvpController::class, 'submit'])
    ->name('meetings.rsvp.submit')
    ->middleware('signed');

// Tournament administration (events) — committee only.
Route::prefix('admin/club-events/tournaments')
    ->middleware(['auth', 'verified', 'can:tournaments.manage', 'feature:tournaments'])
    ->group(function (): void {
        Route::livewire('/', 'pages::club-events.tournaments.index')->name('admin.tournaments.index');
        Route::livewire('{tournament}/live-center', 'pages::club-events.tournaments.live-center')->name('admin.tournaments.live-center');
        Route::livewire('wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard');
        Route::livewire('{tournament}/wizard', 'pages::club-events.tournaments.wizard')->name('admin.tournaments.wizard.edit');
        Route::get('{tournament}/print/pools', [TournamentPrintController::class, 'poolsPoster'])->name('admin.tournaments.print.pools');
        Route::get('{tournament}/print/match-sheets', [TournamentPrintController::class, 'matchSheets'])->name('admin.tournaments.print.matches');
    });

/*
 * La même journée par l'autre bout : le tournoi vu par un joueur.
 *
 * Hors du groupe ci-dessus, qui est fermé au comité. La page ne sait rien
 * écrire — pas un score, pas une table, pas un statut — et c'est ce qui permet
 * de l'ouvrir aux inscrits. Elle vérifie elle-même l'inscription dans mount(),
 * parce que « être inscrit à ce tournoi-ci » n'est pas une permission mais une
 * ligne de pivot.
 */
Route::prefix('admin/club-events/tournaments')
    ->middleware(['auth', 'verified', 'feature:tournaments'])
    ->group(function (): void {
        Route::livewire('{tournament}/live', 'pages::club-events.tournaments.live')->name('admin.tournaments.live');
    });

Route::prefix('admin/club-events/interclubs/')
    ->middleware(['auth', 'verified', 'feature:interclubs'])
    ->group(function (): void {
        // Personal matches — self-scoped, left broad for any player for now.
        Route::livewire('my-matches', 'pages::club-events.interclubs.my-matches')->name('admin.interclubs.my-matches');

        // Le centre de contrôle a fusionné avec l'écran des sélections : il en
        // était la transposée (une journée, toutes les équipes) et dupliquait
        // tiroir, recherche, statuts et score. L'ancienne URL redirige pour ne
        // pas casser un signet.
        Route::redirect('control-center', '/admin/club-events/interclubs/captain-selection')
            ->name('admin.interclubs.control-center');

        // Selections & results: the permission gates the route, and each
        // component narrows it down to the teams the caller actually captains.
        Route::livewire('captain-selection', 'pages::club-events.interclubs.captain-selection')
            ->middleware('can:access-selections')
            ->name('admin.interclubs.captain-selection');
        Route::livewire('results', 'pages::club-events.interclubs.results')
            ->middleware('can:access-results')
            ->name('admin.interclubs.results');

        // Interclub configuration & control — the interclubs délégation.
        Route::middleware('can:interclubs.manage')->group(function (): void {
            Route::livewire('teams', 'pages::club-events.interclubs.teams.index')->name('admin.interclubs.teams');
            Route::livewire('teams/builder', 'pages::club-events.interclubs.teams.builder')->name('admin.interclubs.teams.builder');
            Route::livewire('teams/{team}', 'pages::club-events.interclubs.teams.show')->name('admin.interclubs.teams.show');
            Route::livewire('teams/{team}/edit', 'pages::club-events.interclubs.teams.edit')->name('admin.interclubs.teams.edit');
            Route::livewire('interclubs', 'pages::club-events.interclubs.interclubs')->name('admin.interclubs.interclubs');
            Route::livewire('division-setup', 'pages::club-events.interclubs.division-setup')->name('admin.interclubs.division-setup');
            Route::livewire('clubs', 'pages::club-events.interclubs.clubs')->name('admin.interclubs.clubs');
        });
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
    ->middleware(['signed', 'throttle:6,1']);
Route::post('/invitation/accept/{user}', [InvitationController::class, 'store'])
    ->name('invitation.store')
    ->middleware(['signed', 'throttle:6,1']);
Route::post('/invitation/resend/{user}', [InvitationController::class, 'resend'])
    ->name('invitation.resend')
    ->middleware('throttle:3,60');

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

Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

Route::prefix('admin/website')->middleware(['auth', 'verified', 'feature:website'])->group(function (): void {
    Route::middleware('can:news_posts.manage')->group(function (): void {
        Route::livewire('/articles', 'pages::website.articles.index')->name('admin.website.articles.index');
        Route::livewire('/articles/create', 'pages::website.articles.edit')->name('admin.website.articles.create');
        Route::livewire('/articles/{newsPost}/edit', 'pages::website.articles.edit')->name('admin.website.articles.edit');
    });
    Route::middleware('feature:contacts')->group(function (): void {
        Route::livewire('/contacts', 'pages::website.contacts.index')
            ->middleware('can:contacts.view')
            ->name('admin.website.contacts.index');
        Route::livewire('/contacts/email-templates', 'pages::website.contacts.email-templates')
            ->middleware('can:contacts.manage')
            ->name('admin.website.contacts.email-templates');
        Route::livewire('/spams', 'pages::website.spams.index')
            ->middleware('can:spams.manage')
            ->name('admin.website.spams.index');
    });
    Route::livewire('/events', 'pages::website.events.index')
        ->middleware('can:event_posts.manage')
        ->name('admin.website.events.index');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    // ... autres routes admin existantes

    // (eventPosts admin routes moved earlier to match newsPosts routing structure)
});

/*
|--------------------------------------------------------------------------
| Season subscription
|--------------------------------------------------------------------------
|
| All that survives of the old "obsolete" block. The three resource routes it
| carried (seasons, registrations, payments) exposed empty controllers whose
| views were deleted during the domain refactor — except `seasons.store`,
| which really created a season for any verified member. Season management now
| lives entirely in `admin.seasons.index`, which is gated properly.
|
| The action authorizes the caller against the member being subscribed.
|
*/
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('seasons/{season}/subscribe/', SubscribeToSeasonAction::class)->name('clubEvents.interclubs.seasons.subscribe');
});

require __DIR__ . '/auth.php';
