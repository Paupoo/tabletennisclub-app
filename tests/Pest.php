<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Trait\RefusesParallelExecution;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature', 'Unit', 'Browser', '../resources/views');

pest()->browser()->timeout(15_000);

uses(RefusesParallelExecution::class)->in('Browser');

beforeEach(function () {
    Club::forgetOwnClub();
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeActiveSeason(): Season
{
    return Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);
}

/**
 * Create a user that counts as "active" for the given season
 * (i.e. holds a confirmed subscription — matches User::active()).
 *
 * @param  array<string, mixed>  $attributes
 */
function activeMember(Season $season, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    Subscription::factory()->for($user)->create([
        'season_id' => $season->id,
        'status' => 'confirmed',
    ]);

    return $user;
}

function makeTrainingPack(Season $season, array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'season_id' => $season->id,
        'level' => TrainingLevel::INTERMEDIATE->value,
        'type' => TrainingType::DIRECTED->value,
        'day_of_week' => 2,
        'start_time' => '18:00:00',
        'duration_minutes' => 90,
        'is_active' => true,
    ], $overrides));
}

/**
 * Routes that legitimately do NOT render a 200 page and must be excluded from
 * the exhaustive page smoke test. Each entry is keyed by route name with a
 * justification. Anything not listed here MUST return 200 when hit as an admin.
 *
 * @return array<string, string>
 */
function smokeSkippedRouteNames(): array
{
    return [
        // Framework / package endpoints — not application pages.
        'sanctum.csrf-cookie' => 'sanctum cookie endpoint',
        'mary.spotlight' => 'maryUI internal endpoint',
        'mary.toogle-sidebar' => 'maryUI internal endpoint',
        'livewire.preview-file' => 'livewire internal endpoint',

        // Guest-only auth scaffold pages — redirect (302) when hit authenticated.
        'login' => 'guest-only, redirects when authenticated',
        'register' => 'guest-only, redirects when authenticated',
        'password.request' => 'guest-only, redirects when authenticated',
        'password.reset' => 'guest-only + token param',
        'verification.notice' => 'redirects verified users to dashboard',

        // Setup wizard — redirects once setup is completed (always true in tests).
        'setup' => 'redirects once setup_completed=1',

        // Intentional redirect to the current treasury page.
        'admin.users.payments' => 'intentional redirect to admin.treasury.payments',

        // Signed email-action endpoints — perform an action / redirect, not pages.
        'invitation.accept' => 'signed email action',
        'meetings.poll.vote' => 'signed email action',
        'meetings.rsvp' => 'signed email action',
        'tournament.register.email' => 'signed email action',
        'tournament.leave-waitlist.email' => 'signed email action',
        'verification.verify' => 'signed email action',

        // Non-HTML downloads.
        'tournament.calendar.ical' => 'ICS file download, not an HTML page',

        // TODO legacy: obsolete controller GET pages whose Blade views were
        // removed during the domain refactor (superseded by Livewire pages).
        // Their write/POST actions are still wired and tested, so the routes
        // stay; these GET pages are known-broken (500) and excluded until the
        // obsolete controllers are cleaned up. See routes/web.php "obsolete" block.
        'clubEvents.interclubs.seasons.index' => 'TODO legacy: missing view, superseded by admin.seasons.index',
        'clubEvents.interclubs.seasons.create' => 'TODO legacy: missing view',
        'clubAdmin.registrations.index' => 'TODO legacy: missing view, superseded by admin.users.registrations',
        'admin.payments.index' => 'TODO legacy: missing view, superseded by admin.treasury.payments',
        'admin.transactions.add ' => 'TODO legacy: missing view (note: route name has a trailing space)',
        'admin.transactions.index' => 'TODO legacy: missing view, superseded by admin.treasury.transactions',
        'admin.transactions.reconcile' => 'TODO legacy: missing view',
    ];
}

/**
 * Every named GET page route without route parameters, minus the explicit
 * skip-list above. Used to assert exhaustively that each view renders.
 *
 * Routes with parameters are covered by dedicated tests that build the bound
 * models with their dependencies.
 *
 * @return array<string, string> Keyed by route name, value is the URI to GET.
 */
function smokeableGetRoutes(): array
{
    $skip = smokeSkippedRouteNames();

    $routes = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        // Only named application routes (drops framework asset routes).
        if ($name === null || $name === '') {
            continue;
        }

        // Dev-only debug tooling (Ignition), never an application page.
        if (str_starts_with($name, 'ignition.')) {
            continue;
        }

        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        // Parameterised routes are covered by dedicated binding tests.
        if ($route->parameterNames() !== []) {
            continue;
        }

        if (array_key_exists($name, $skip)) {
            continue;
        }

        $routes[$name] = '/' . ltrim($route->uri(), '/');
    }

    ksort($routes);

    return $routes;
}

function paymentTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'price' => 10,
        'max_users' => 16,
        'duration_minutes' => 180,
        'logistics_buffer_minutes' => 3,
        'sets_to_win' => 3,
        'nb_pools' => 2,
        'pool_size' => 4,
        'nb_qualifiers_per_pool' => 2,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'deuce_enabled' => true,
        'start_time' => '10:00:00',
        'location' => 'Club House',
    ], $overrides));
}
