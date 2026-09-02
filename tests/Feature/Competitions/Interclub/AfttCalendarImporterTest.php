<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Serves the committed fixtures the way TabT would.
 *
 * The club-team response is a trimmed copy of the real one, cut down to the two
 * divisions we hold fixtures for — 9756 (veterans) and 9611 (men, the one with
 * the byes). Every field is the federation's own; only the seven other entries
 * were removed, so the shape under test stays real while the fixture set stays
 * small enough to read.
 */
function fakeTabt(): void
{
    Http::fake(function (Request $request): PromiseInterface {
        $body = $request->body();

        $fixture = match (true) {
            str_contains($body, 'GetSeasonsRequest') => 'get-seasons.xml',
            str_contains($body, 'GetClubTeamsRequest') => 'get-club-teams-bbw214-two-divisions.xml',
            str_contains($body, '<t:DivisionId>9756</t:DivisionId>') => 'get-matches-division-9756.xml',
            str_contains($body, '<t:DivisionId>9611</t:DivisionId>') => 'get-matches-division-9611.xml',
            str_contains($body, 'GetDivisions') => 'get-divisions.xml',
            str_contains($body, 'GetClubs') => 'get-clubs-bbw145.xml',
            default => throw new RuntimeException('No fixture for: ' . $body),
        };

        return Http::response(file_get_contents(base_path('tests/Fixtures/Aftt/' . $fixture)));
    });
}

/**
 * The opponent clubs that appear in the two fixture divisions.
 *
 * Created up front because that is the real situation: the club's table already
 * holds every opponent it plays, each keyed by its federation code. Creating a
 * club from the federation is a separate, rarer path with its own tests.
 */
function knownOpponents(): void
{
    foreach (['BBW015', 'BBW034', 'BBW118', 'BBW134', 'BBW145', 'BBW165',
        'BBW223', 'BBW299', 'BBW319', 'BBW323', 'BBW348', 'BBW349'] as $licence) {
        Club::factory()->create(['licence' => $licence, 'is_own_club' => false]);
    }
}

beforeEach(function (): void {
    fakeTabt();

    $this->season = Season::factory()->create(['name' => '2026-2027']);
    $this->ownClub = Club::factory()->create([
        'is_own_club' => true,
        'licence' => 'BBW214',
        'name' => 'C.T.T Ottignies-Blocry',
    ]);
});

it('turns a federation division into a league, reading the codes and not the label', function (): void {
    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $veterans = League::where('aftt_division_id', 9756)->first();

    expect($veterans->division)->toBe('3D')
        ->and($veterans->category)->toBe('VETERANS')
        ->and($veterans->level)->toBe('PROVINCIAL_BW')
        ->and($veterans->season_id)->toBe($this->season->id);

    $men = League::where('aftt_division_id', 9611)->first();

    expect($men->division)->toBe('5H')
        ->and($men->category)->toBe('MEN')
        ->and($men->level)->toBe('PROVINCIAL_BW');
});

it('names our own teams by their letter and puts them in our club', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $veterans = League::where('aftt_division_id', 9756)->first();
    $ours = Team::where('league_id', $veterans->id)->where('club_id', $this->ownClub->id)->first();

    expect($ours->name)->toBe('A')
        ->and($ours->season_id)->toBe($this->season->id);

    $men = League::where('aftt_division_id', 9611)->first();

    expect(Team::where('league_id', $men->id)->where('club_id', $this->ownClub->id)->value('name'))
        ->toBe('E');
});

it('takes an opponent team letter from the last word of its name', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $men = League::where('aftt_division_id', 9611)->first();
    $logis = Club::where('licence', 'BBW165')->first();

    expect(Team::where('league_id', $men->id)->where('club_id', $logis->id)->value('name'))
        ->toBe('Z');

    // "Bye " is not a club and never becomes a team.
    expect(Team::where('name', 'Bye')->exists())->toBeFalse()
        ->and(Club::where('licence', '-')->exists())->toBeFalse();
});

it('imports only the fixtures our own club plays', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    // The two divisions hold 118 fixtures between them; ours are the ones with
    // BBW214 on one side. Fetching whole divisions is how a bye gets a date.
    $ourTeamIds = Team::where('club_id', $this->ownClub->id)->pluck('id');

    expect(Interclub::count())->toBe(
        Interclub::whereIn('visited_team_id', $ourTeamIds)
            ->orWhereIn('visiting_team_id', $ourTeamIds)
            ->count()
    );
});

it('writes a fixture with its venue, its round and its calendar week', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $match = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($match->start_date_time->toDateTimeString())->toBe('2026-09-18 19:45:00')
        ->and($match->round_number)->toBe(1)
        ->and($match->week_number)->toBe(38)
        ->and($match->total_players)->toBe(4)
        ->and($match->is_bye)->toBeFalse()
        ->and($match->season_id)->toBe($this->season->id);

    expect($match->address)
        ->toBe("Complexe Sportif Jean Demeester, Rue de l'Invasion, 80, 1340 Ottignies Lln");

    expect($match->visitedTeam->club->licence)->toBe('BBW214')
        ->and($match->visitedTeam->name)->toBe('E')
        ->and($match->visitingTeam->club->licence)->toBe('BBW299')
        ->and($match->visitingTeam->name)->toBe('D');
});

it('gives a bye the day its division plays that round', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $bye = Interclub::where('aftt_match_id', 'PBBWH05/114')->first();

    expect($bye->is_bye)->toBeTrue()
        ->and($bye->round_number)->toBe(5)
        ->and($bye->address)->toBe('');

    // Round 05 of division 9611 is played on the 16th (two fixtures) and the
    // 17th (two more). The federation states no date for a bye, so the earliest
    // of them is used — an inference, and the reason byes stay off the calendar.
    expect($bye->start_date_time->toDateTimeString())->toBe('2026-10-16 19:45:00')
        ->and($bye->week_number)->toBe(42);

    // We are at home against nobody, so only our own side is filled in.
    expect($bye->visitedTeam->club->licence)->toBe('BBW214')
        ->and($bye->visitedTeam->name)->toBe('E')
        ->and($bye->visiting_team_id)->toBeNull();
});

it('fills our own side of a bye whichever side the federation put us on', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $away = Interclub::where('aftt_match_id', 'PBBWH16/114')->first();

    expect($away->is_bye)->toBeTrue()
        ->and($away->visited_team_id)->toBeNull()
        ->and($away->visitingTeam->name)->toBe('E');
});
