<?php

declare(strict_types=1);

use App\Data\Interclub\AfttMatchSheet;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttResultsImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // L'ordre réel des appels de l'importeur, division par division : les
    // feuilles, puis le classement. `Http::fake()` fusionne les stubs au lieu
    // de les remplacer, donc un second appel dans un test ne corrigerait rien —
    // la séquence se pose ici, une fois.
    Http::fake([
        'api.aftt.be/*' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-with-details.xml')))
            ->whenEmpty(Http::response(
                file_get_contents(base_path('tests/Fixtures/Aftt/get-division-ranking-8860.xml'))
            )),
    ]);

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
        'aftt_division_id' => 8860,
    ]);

    // The licence is what a ranking line is claimed by, so it has to be the
    // one the fixture names.
    $this->ourClub = Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
    $this->theirClub = Club::factory()->create(['is_own_club' => false, 'licence' => 'BBW350']);
});

/**
 * A local fixture carrying the federation's own handle for a tie.
 *
 * `$atHome` decides which side of the fixture our club sits on, which is the
 * whole point of most of these tests: the federation writes every sheet
 * home-first and the importer has to turn it round.
 */
function localFixture(string $afttMatchId, bool $atHome = true): Interclub
{
    $ours = Team::factory()->create([
        'club_id' => test()->ourClub->id,
        'league_id' => test()->league->id,
        'season_id' => test()->season->id,
    ]);
    $theirs = Team::factory()->create([
        'club_id' => test()->theirClub->id,
        'league_id' => test()->league->id,
        'season_id' => test()->season->id,
    ]);

    return Interclub::factory()->create([
        'aftt_match_id' => $afttMatchId,
        'league_id' => test()->league->id,
        'season_id' => test()->season->id,
        'start_date_time' => now()->subDays(3),
        'visited_team_id' => $atHome ? $ours->id : $theirs->id,
        'visiting_team_id' => $atHome ? $theirs->id : $ours->id,
    ]);
}

function runImport(): array
{
    return app(AfttResultsImporter::class)->import(
        test()->season,
        26,
        app(TabtClient::class),
    );
}

it('writes the official score and verdict onto the fixture we already had', function (): void {
    $match = localFixture('PBBWH01/021');

    runImport();

    $result = InterclubResult::where('interclub_id', $match->id)->first();

    expect($result->score)->toBe('9-7')
        ->and($result->result)->toBe(InterclubResultEnum::WIN);
});

it('reads the verdict from our own side when we play away', function (): void {
    $match = localFixture('PBBWH01/021', atHome: false);

    runImport();

    $result = InterclubResult::where('interclub_id', $match->id)->first();

    // Stored home-first, as every other reader of this column expects…
    expect($result->score)->toBe('9-7')
        // …but 9-7 to the home side is a defeat for the visitors.
        ->and($result->result)->toBe(InterclubResultEnum::LOSS);
});

it('turns every individual match round to our side', function (): void {
    $match = localFixture('PBBWH01/021', atHome: false);

    runImport();

    $lines = InterclubIndividualMatch::where('interclub_id', $match->id)->orderBy('position')->get();

    expect($lines)->toHaveCount(16);

    // The federation writes position 1 as 3-2 to the home side. We are the
    // visitors, so the same line is a 2-3 defeat for us.
    $first = $lines->firstWhere('position', 1);
    expect($first->our_sets)->toBe(2)
        ->and($first->their_sets)->toBe(3)
        ->and($first->we_won)->toBeFalse()
        ->and($first->opponent_licence)->toBe('101137');
});

it('stores the double as a line nobody played', function (): void {
    $this->league->update(['aftt_division_id' => 8721]);
    $match = localFixture('PBBWV01/305');

    runImport();

    $double = InterclubIndividualMatch::where('interclub_id', $match->id)->where('is_double', true)->first();

    expect($double)->not->toBeNull()
        ->and($double->position)->toBe(7)
        ->and($double->user_id)->toBeNull()
        ->and($double->our_player_licence)->toBeNull()
        ->and($double->opponent_name)->toBeNull()
        ->and($double->we_won)->toBeTrue();
});

it('keeps a forfeited line with both players and no sets', function (): void {
    $match = localFixture('PBBWH01/022');

    runImport();

    $forfeit = InterclubIndividualMatch::where('interclub_id', $match->id)->where('is_forfeit', true)->first();

    expect($forfeit)->not->toBeNull()
        ->and($forfeit->our_sets)->toBeNull()
        ->and($forfeit->their_sets)->toBeNull()
        ->and($forfeit->opponent_name)->not->toBeNull()
        ->and($forfeit->we_won)->toBeTrue();
});

it('attaches a line to the member whose licence it carries', function (): void {
    $player = User::factory()->create(['licence' => '166488']);
    $match = localFixture('PBBWH01/021');

    runImport();

    $lines = InterclubIndividualMatch::where('interclub_id', $match->id)->where('user_id', $player->id)->get();

    expect($lines)->toHaveCount(4)
        ->and($lines->first()->our_player_licence)->toBeNull()
        ->and($lines->first()->our_player_name)->toBeNull();
});

it('reports a licence of ours that matches no member, and keeps the name beside the gap', function (): void {
    $match = localFixture('PBBWH01/021');

    ['report' => $report] = runImport();

    expect($report['unknown_licences'])->not->toBeEmpty();

    $orphan = InterclubIndividualMatch::where('interclub_id', $match->id)
        ->whereNull('user_id')
        ->where('is_double', false)
        ->first();

    expect($orphan->our_player_licence)->not->toBeNull()
        ->and($orphan->our_player_name)->not->toBeNull();
});

it('leaves a fixture alone while its sheet is an empty shell', function (): void {
    $match = localFixture('PBBWH01/001');
    InterclubResult::where('interclub_id', $match->id)->update(['score' => '8-8']);

    ['tally' => $tally] = runImport();

    expect($tally['sheets_pending'])->toBe(1)
        ->and(InterclubResult::where('interclub_id', $match->id)->value('score'))->toBe('8-8')
        ->and(InterclubIndividualMatch::where('interclub_id', $match->id)->count())->toBe(0);
});

it('ignores a sheet for a fixture we never imported', function (): void {
    ['tally' => $tally] = runImport();

    expect($tally['sheets_unknown'])->toBe(4)
        ->and($tally['fixtures_updated'])->toBe(0)
        ->and(InterclubIndividualMatch::count())->toBe(0);
});

it('corrects a sheet in place when run twice', function (): void {
    $match = localFixture('PBBWH01/021');

    runImport();
    $firstPass = InterclubIndividualMatch::where('interclub_id', $match->id)->count();

    runImport();

    expect(InterclubIndividualMatch::where('interclub_id', $match->id)->count())
        ->toBe($firstPass)
        ->toBe(16);
});

it('writes where our teams finished, in the words the club already uses', function (): void {
    $this->league->update(['aftt_division_id' => 8860]);

    $a = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'A',
    ]);
    $b = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'B',
    ]);

    ['tally' => $tally] = runImport();
    expect($a->fresh()->final_position)->toBe('1ère place')
        ->and($b->fresh()->final_position)->toBe('3ème place')
        ->and($tally['positions_written'])->toBe(2);
});

it('leaves another club alone, however it finished', function (): void {
    $this->league->update(['aftt_division_id' => 8860]);

    // "CTT Tourinnes A" sits second in the same table, and shares our letter.
    $theirs = Team::factory()->create([
        'club_id' => $this->theirClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'A',
    ]);

    runImport();

    expect($theirs->fresh()->final_position)->toBeNull();
});

it('counts nothing when the position has not moved', function (): void {
    $this->league->update(['aftt_division_id' => 8860]);

    Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'A',
        'final_position' => '1ère place',
    ]);

    ['tally' => $tally] = runImport();

    expect($tally['positions_written'])->toBe(0);
});

/**
 * The federation decorates a score it did not simply record as played.
 *
 * Eleven characters into a column of ten is how a whole season's import died;
 * the marker also had to stay out of a column every reader splits on "-" and
 * casts to int.
 */
dataset('decorated scores', [
    // 16-0 to the home side is the federation saying the visitors did not come:
    // the flag sits on them, not on the side that collected the points.
    'tie forfeited by them' => ['16-0 ff', false, true, '16-0', InterclubResultEnum::FORFEIT_WIN],
    'tie forfeited by us' => ['0-16 ff', true, false, '0-16', InterclubResultEnum::FORFEIT_LOSS],
    'withdrawal, both flagged' => ['0-0 fg (fg)', true, true, '0-0', InterclubResultEnum::WITHDRAWAL],
    'adjusted result, nobody forfeited' => ['3-13 sm', false, false, '3-13', InterclubResultEnum::LOSS],
]);

it('keeps the pair and drops the marker', function (string $raw, bool $homeFf, bool $awayFf, string $stored, InterclubResultEnum $verdict): void {
    $match = localFixture('PBBWH01/021');

    $sheet = new AfttMatchSheet(
        matchId: 'PBBWH01/021',
        detailsCreated: true,
        score: $raw,
        matchSystem: 2,
        isHomeForfeited: $homeFf,
        isAwayForfeited: $awayFf,
    );

    Http::fake(['api.aftt.be/*' => Http::response(
        file_get_contents(base_path('tests/Fixtures/Aftt/get-division-ranking-8860.xml'))
    )]);

    $importer = new ReflectionClass(AfttResultsImporter::class);
    $instance = app(AfttResultsImporter::class);
    $write = $importer->getMethod('writeTeamScore');
    $write->invoke($instance, $match, $sheet, true);

    $result = InterclubResult::where('interclub_id', $match->id)->first();

    expect($result->score)->toBe($stored)
        ->and($result->result)->toBe($verdict);
})->with('decorated scores');

it('never files a sheet against another season carrying the same identifier', function (): void {
    // The unique index is on the pair, so the federation reusing an identifier
    // from one year to the next is legal — and an unscoped lookup would file
    // last season's sheet against this season's evening.
    $ours = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id,
    ]);
    $theirs = Team::factory()->create([
        'club_id' => $this->theirClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id,
    ]);

    $otherSeason = Season::factory()->create(['is_active' => false]);
    $otherLeague = League::factory()->create(['season_id' => $otherSeason->id, 'category' => 'MEN']);

    $decoy = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH01/021',
        'season_id' => $otherSeason->id,
        'league_id' => $otherLeague->id,
        'visited_team_id' => Team::factory()->create(['club_id' => $this->ourClub->id, 'league_id' => $otherLeague->id, 'season_id' => $otherSeason->id])->id,
        'visiting_team_id' => Team::factory()->create(['club_id' => $this->theirClub->id, 'league_id' => $otherLeague->id, 'season_id' => $otherSeason->id])->id,
        'start_date_time' => now()->subYears(2),
    ]);

    $ours = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH01/021',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $ours->id,
        'visiting_team_id' => $theirs->id,
        'start_date_time' => now()->subDays(3),
    ]);

    runImport();

    expect(InterclubIndividualMatch::where('interclub_id', $ours->id)->count())->toBe(16)
        ->and(InterclubIndividualMatch::where('interclub_id', $decoy->id)->count())->toBe(0);
});
