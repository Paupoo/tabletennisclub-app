<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Everything here comes from one evening of running the imports against the
 * real federation for the first time. Each case cost a rolled-back import and
 * a puzzling error message; none of them can be reached from the current
 * season, which is why the suite was green throughout.
 */

it('reads a numbered bye as a bye', function (): void {
    // A division with two byes calls them "Bye 1" and "Bye 2". Compared for
    // equality with "Bye", both came through as ordinary fixtures — and the
    // import then asked the federation about a club called "-".
    Http::fake(['api.aftt.be/*' => Http::response(
        file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-numbered-byes.xml'))
    )]);

    $matches = app(TabtClient::class)->divisionMatches(8896, 26);

    expect($matches)->toHaveCount(3);

    $byes = collect($matches)->filter(fn ($match): bool => $match->isBye);

    expect($byes)->toHaveCount(2)
        ->and($byes->pluck('matchId')->all())
        ->toBe(['BAR.PBBWH.BAR-3.02/002', 'PBBWH15/114']);
});

it('holds a play-off identifier, which is longer than a division one', function (): void {
    // 22 characters against the 20 the column used to allow. The regular
    // season never produces one, so this only ever surfaced on a past season.
    $season = Season::factory()->create();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $club = Club::factory()->create(['is_own_club' => true]);
    $other = Club::factory()->create(['is_own_club' => false]);

    $fixture = Interclub::factory()->create([
        'aftt_match_id' => 'BAR.PBBWH.BAR-3.02/002',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => Team::factory()->create(['club_id' => $club->id, 'league_id' => $league->id, 'season_id' => $season->id])->id,
        'visiting_team_id' => Team::factory()->create(['club_id' => $other->id, 'league_id' => $league->id, 'season_id' => $season->id])->id,
    ]);

    expect($fixture->fresh()->aftt_match_id)->toBe('BAR.PBBWH.BAR-3.02/002');
});

it('adopts the division the club typed in rather than building a twin', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-divisions.xml')))
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-club-teams-bbw214.xml')))
            ->whenEmpty(Http::response(
                file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-division-9756.xml'))
            )),
    ]);

    $season = Season::factory()->create(['is_active' => true]);
    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);

    // The division as the club recorded it, before it ever spoke to TabT.
    $typed = League::factory()->create([
        'season_id' => $season->id,
        'category' => 'VETERANS',
        'division' => '3D',
        'aftt_division_id' => null,
    ]);

    app(AfttCalendarImporter::class)->import($season, 27, 'BBW214');

    $veterans = League::where('season_id', $season->id)
        ->where('category', 'VETERANS')
        ->where('division', '3D')
        ->get();

    expect($veterans)->toHaveCount(1)
        ->and($veterans->first()->id)->toBe($typed->id)
        ->and($veterans->first()->aftt_division_id)->toBe(9756);
});
