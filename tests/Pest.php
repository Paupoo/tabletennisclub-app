<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

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
)->in('Feature', 'Unit', '../resources/views');

beforeAll(function (): void {
    // Run migrations and seed once before the whole test suite to avoid
    // re-seeding on every test. This speeds up the test run while keeping
    // a seeded DB available for tests.
    Artisan::call('migrate:fresh', ['--seed' => true]);
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
