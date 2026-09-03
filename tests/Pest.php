<?php

declare(strict_types=1);

use App\Data\User\FederationRow;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;
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

beforeEach(function (): void {
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

expect()->extend('toBeOne', fn () => $this->toBe(1));

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

/**
 * A row of the federation affiliate listing as the parser hands it over, with
 * only the fields a given test cares about set.
 *
 * @param  array<string, mixed>  $overrides
 */
function federationRow(array $overrides = []): FederationRow
{
    return new FederationRow(
        lineNumber: $overrides['lineNumber'] ?? 2,
        licence: $overrides['licence'] ?? '166036',
        lastName: $overrides['lastName'] ?? 'Dupont',
        firstName: $overrides['firstName'] ?? 'Marc',
        birthdate: array_key_exists('birthdate', $overrides)
            ? $overrides['birthdate']
            : CarbonImmutable::parse('1990-06-05'),
        ranking: $overrides['ranking'] ?? 'C2',
        gender: $overrides['gender'] ?? Gender::MEN,
        federationLicenceType: $overrides['federationLicenceType'] ?? 'JO',
        email: array_key_exists('email', $overrides) ? $overrides['email'] : 'marc@example.com',
        phone: $overrides['phone'] ?? '0475123456',
        // `array_key_exists` rather than `??`: a test saying the export left the
        // column empty passes null, and must not be handed the default back.
        street: array_key_exists('street', $overrides) ? $overrides['street'] : 'RUE DU TEST 13',
        cityCode: array_key_exists('cityCode', $overrides) ? $overrides['cityCode'] : '1348',
        cityName: array_key_exists('cityName', $overrides) ? $overrides['cityName'] : 'LOUVAIN-LA-NEUVE',
    );
}

/**
 * L'id d'un niveau semé par la migration `create_training_levels_table`.
 *
 * Les tests ciblent le libellé, jamais l'id : celui-ci dépend de l'ordre des
 * insertions et changerait au moindre ajout de niveau.
 */
function trainingLevelId(string $label): int
{
    return TrainingLevel::where('label', $label)->value('id')
        ?? TrainingLevel::factory()->create(['label' => $label])->id;
}

function makeTrainingPack(Season $season, array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'season_id' => $season->id,
        'training_level_id' => trainingLevelId('Confirmé'),
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

        // Route::redirect() keeps an old URL alive after a screen moves. It
        // renders nothing by design, so a 302 here is the feature, not a
        // failure — and skipping the category means the next move does not
        // have to remember to come back and edit a list.
        if (str_contains($route->getActionName(), 'RedirectController')) {
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
