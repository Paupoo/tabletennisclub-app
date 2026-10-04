<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\OfficialTournamentImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use App\Domains\Competitions\Interclub\Services\TabtQuotaExceeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
});

/**
 * Three members in the fixture: 172446 played three tournament matches,
 * 176409 eight, 101683 only interclub.
 */
function fakeClubTournamentResults(): void
{
    Http::fake([
        'api.aftt.be/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/Aftt/get-members-with-results-bbw214.xml'))
        ),
    ]);
}

function importTournaments(): array
{
    return app(OfficialTournamentImporter::class)->import(test()->season, 27, app(TabtClient::class));
}

it('files every tournament match against the member who holds the licence', function (): void {
    fakeClubTournamentResults();
    $tom = User::factory()->create(['licence' => '176409']);

    importTournaments();

    $lines = OfficialTournamentMatch::where('user_id', $tom->id)->get();

    expect($lines)->toHaveCount(8)
        ->and($lines->pluck('season_id')->unique()->all())->toBe([$this->season->id])
        ->and($lines->where('we_won', true))->toHaveCount(2);
});

it('keeps the matches of a licence no member holds, and names it', function (): void {
    fakeClubTournamentResults();

    $outcome = importTournaments();

    expect(OfficialTournamentMatch::where('player_licence', '172446')->whereNull('user_id')->count())->toBe(3)
        ->and($outcome['report']['unknown_licences'])->toHaveKey('172446');
});

it('replaces a player\'s season instead of stacking a second copy beside it', function (): void {
    fakeClubTournamentResults();

    // A match the federation has since struck off.
    OfficialTournamentMatch::factory()->create([
        'season_id' => $this->season->id,
        'player_licence' => '176409',
        'tournament_name' => 'Tournoi annulé',
    ]);

    importTournaments();
    importTournaments();

    expect(OfficialTournamentMatch::count())->toBe(11)
        ->and(OfficialTournamentMatch::where('tournament_name', 'Tournoi annulé')->exists())->toBeFalse();
});

it('never empties a player the federation returned nothing for', function (): void {
    fakeClubTournamentResults();

    OfficialTournamentMatch::factory()->count(2)->create([
        'season_id' => $this->season->id,
        'player_licence' => '101683',
        'player_name' => 'AUGUSTIN DOCQUIER',
    ]);

    $outcome = importTournaments();

    expect(OfficialTournamentMatch::where('player_licence', '101683')->count())->toBe(2)
        ->and($outcome['report']['emptied_licences'])->toBe(['101683' => 'AUGUSTIN DOCQUIER']);
});

it('leaves every other season alone', function (): void {
    fakeClubTournamentResults();
    $previous = Season::factory()->create(['is_active' => false]);

    OfficialTournamentMatch::factory()->create(['season_id' => $previous->id, 'player_licence' => '176409']);

    importTournaments();

    expect(OfficialTournamentMatch::where('season_id', $previous->id)->count())->toBe(1);
});

it('touches nothing when the federation refuses the call', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><SOAP-ENV:Fault>'
            . '<faultcode>8</faultcode><faultstring>Internal error</faultstring>'
            . '</SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>'
        ),
    ]);

    OfficialTournamentMatch::factory()->create(['season_id' => $this->season->id, 'player_licence' => '176409']);

    expect(fn () => importTournaments())->toThrow(RuntimeException::class)
        ->and(OfficialTournamentMatch::count())->toBe(1);
});

it('hands a member the matches filed under their licence before the roster knew it, whatever the season', function (): void {
    fakeClubTournamentResults();
    $previous = Season::factory()->create(['is_active' => false]);

    $orphan = OfficialTournamentMatch::factory()->create([
        'season_id' => $previous->id,
        'player_licence' => '555555',
        'user_id' => null,
    ]);
    $member = User::factory()->create(['licence' => '555555']);

    importTournaments();

    expect($orphan->fresh()->user_id)->toBe($member->id);
});

it('waits for the quota to drain and asks once more', function (): void {
    Sleep::fake();
    Http::fake([
        'api.aftt.be/*' => Http::sequence()
            ->push(afttQuotaRefusal())
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-members-with-results-bbw214.xml'))),
    ]);

    importTournaments();

    expect(OfficialTournamentMatch::count())->toBe(11);
    Sleep::assertSleptTimes(1);
});

it('gives up after a second quota refusal, touching nothing', function (): void {
    Sleep::fake();
    Http::fake([
        'api.aftt.be/*' => Http::sequence()->push(afttQuotaRefusal())->push(afttQuotaRefusal()),
    ]);

    expect(fn () => importTournaments())->toThrow(TabtQuotaExceeded::class)
        ->and(OfficialTournamentMatch::count())->toBe(0);
});
