<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Sleep::fake();

    // One stub, steered from the test: `Http::fake()` stacks, and a second one
    // would never be consulted.
    $this->refusing = false;

    Http::fake([
        'api.aftt.be/*' => fn (Request $request) => Http::response(match (true) {
            str_contains($request->body(), 'GetSeasons') => file_get_contents(base_path('tests/Fixtures/Aftt/get-seasons.xml')),
            test()->refusing => afttQuotaRefusal(),
            default => file_get_contents(base_path('tests/Fixtures/Aftt/get-members-with-results-bbw214.xml')),
        }),
    ]);

    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
    $this->season = Season::factory()->create(['is_active' => true, 'name' => '2026-2027']);
});

it('imports the tournament matches of the active season', function (): void {
    $this->artisan('interclubs:import-tournaments')
        ->expectsOutputToContain('2026-2027')
        ->assertSuccessful();

    expect(OfficialTournamentMatch::where('season_id', $this->season->id)->count())->toBe(11);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<t:Season>27</t:Season>')
        && str_contains($request->body(), 'GetMembersRequest'));
});

it('names every licence that matches no member', function (): void {
    User::factory()->create(['licence' => '176409']);

    $this->artisan('interclubs:import-tournaments')
        ->expectsOutputToContain('172446')
        ->assertSuccessful();
});

it('names a player the federation returned nothing for, and keeps their matches', function (): void {
    OfficialTournamentMatch::factory()->create([
        'season_id' => $this->season->id,
        'player_licence' => '101683',
        'player_name' => 'AUGUSTIN DOCQUIER',
    ]);

    $this->artisan('interclubs:import-tournaments')
        ->expectsOutputToContain('AUGUSTIN DOCQUIER')
        ->assertSuccessful();
});

it('fails, writing nothing, when the federation keeps refusing', function (): void {
    $this->refusing = true;

    $this->artisan('interclubs:import-tournaments')->assertFailed();

    expect(OfficialTournamentMatch::count())->toBe(0);
});

it('loads, for history, exactly the seasons the interclub match sheets reach', function (): void {
    $withSheets = Season::factory()->create(['is_active' => false, 'name' => '2025-2026']);
    $older = Season::factory()->create(['is_active' => false, 'name' => '2024-2025']);
    Season::factory()->create(['is_active' => false, 'name' => '2023-2024']);

    foreach ([$withSheets, $older] as $season) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => Interclub::factory()->create(['season_id' => $season->id])->id,
        ]);
    }

    $this->artisan('interclubs:import-tournaments', ['--history' => true])
        ->expectsOutputToContain('2025-2026')
        ->expectsOutputToContain('2024-2025')
        ->doesntExpectOutputToContain('2023-2024')
        ->assertSuccessful();

    expect(OfficialTournamentMatch::where('season_id', $withSheets->id)->count())->toBe(11)
        ->and(OfficialTournamentMatch::where('season_id', $older->id)->count())->toBe(11)
        ->and(OfficialTournamentMatch::where('season_id', $this->season->id)->count())->toBe(0);

    // Each season costs more than the whole quota: never two calls back to back.
    Sleep::assertSlept(fn ($duration): bool => $duration->totalMinutes >= 5, times: 1);
});

it('runs every night at 04:30, clear of the other two federation imports', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'interclubs:import-tournaments'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 4 * * *');
});
