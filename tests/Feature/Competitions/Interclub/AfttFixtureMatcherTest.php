<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttFixtureMatcher;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeDivision9611(): void
{
    // clubTeams first, then divisionMatches — the order the matcher calls them.
    Http::fake([
        'api.aftt.be/*' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-club-teams-bbw214-division-9611.xml')))
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-division-9611.xml'))),
    ]);
}

beforeEach(function (): void {
    fakeDivision9611();

    $this->season = Season::factory()->create(['name' => '2025-2026', 'is_active' => false]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->ourClub = Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
    $this->theirClub = Club::factory()->create(['is_own_club' => false, 'licence' => 'BBW299']);

    $this->ours = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'E',
    ]);
    $this->theirs = Team::factory()->create([
        'club_id' => $this->theirClub->id, 'league_id' => $this->league->id,
        'season_id' => $this->season->id, 'name' => 'D',
    ]);
});

function runMatcher(bool $dryRun = false): array
{
    return app(AfttFixtureMatcher::class)->link(
        test()->season, 27, 'BBW214', app(TabtClient::class), $dryRun,
    );
}

it('gives a hand-typed fixture the federation id it was missing', function (): void {
    $match = Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->ours->id,
        'visiting_team_id' => $this->theirs->id,
        'start_date_time' => '2026-09-18 20:00:00',
    ]);

    ['linked' => $linked] = runMatcher();

    expect($linked)->toBe(1)
        ->and($match->fresh()->aftt_match_id)->toBe('PBBWH01/113');
});

it('pairs a fixture the club wrote down on the wrong date', function (): void {
    // Postponed by the federation, never corrected in the club's own list: the
    // two teams are what says it is the same match.
    $match = Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->ours->id,
        'visiting_team_id' => $this->theirs->id,
        'start_date_time' => '2026-09-25 20:00:00',
    ]);

    runMatcher();

    expect($match->fresh()->aftt_match_id)->toBe('PBBWH01/113');
});

it('refuses two candidates a fortnight apart rather than guessing', function (): void {
    foreach (['2026-09-18 20:00:00', '2027-01-20 20:00:00'] as $date) {
        Interclub::factory()->create([
            'aftt_match_id' => null,
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $this->ours->id,
            'visiting_team_id' => $this->theirs->id,
            'start_date_time' => $date,
        ]);
    }

    ['linked' => $linked] = runMatcher();

    // The nearest one wins; it is on the very day the federation states.
    expect($linked)->toBe(1)
        ->and(Interclub::whereNotNull('aftt_match_id')->value('start_date_time')->toDateString())
        ->toBe('2026-09-18');
});

it('never touches a fixture that already carries an id', function (): void {
    $match = Interclub::factory()->create([
        'aftt_match_id' => 'TYPED/BY/HAND',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->ours->id,
        'visiting_team_id' => $this->theirs->id,
        'start_date_time' => '2026-09-18 20:00:00',
    ]);

    ['linked' => $linked, 'report' => $report] = runMatcher();

    expect($linked)->toBe(0)
        ->and($match->fresh()->aftt_match_id)->toBe('TYPED/BY/HAND')
        ->and($report['unmatched'])->not->toBeEmpty();
});

it('names what it could not pair instead of inventing a link', function (): void {
    ['linked' => $linked, 'report' => $report] = runMatcher();

    // Eighteen of the division's fixtures involve our club, two of which are
    // byes — a round with no opponent, so nothing to pair. The sixteen real
    // ones have no local row, and each is named rather than silently skipped.
    expect($linked)->toBe(0)
        ->and($report['unmatched'])->toHaveCount(16)
        ->and(implode("\n", $report['unmatched']))->toContain('PBBWH01/113');
});

it('writes nothing on a dry run but reports what it would do', function (): void {
    $match = Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->ours->id,
        'visiting_team_id' => $this->theirs->id,
        'start_date_time' => '2026-09-18 20:00:00',
    ]);

    ['linked' => $linked, 'linked_ids' => $ids] = runMatcher(dryRun: true);

    // Named as well as counted: a dry run has to be able to say which local
    // rows it would rescue, or it reports them as duplicates-to-be.
    expect($linked)->toBe(1)
        ->and($ids)->toBe([$match->id])
        ->and($match->fresh()->aftt_match_id)->toBeNull();
});

it('ignores a team of the same letter in another category', function (): void {
    // Veterans E, not men's E: the letter alone would have paired them.
    $veterans = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
    $this->ours->update(['league_id' => $veterans->id]);

    Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $this->season->id,
        'league_id' => $veterans->id,
        'visited_team_id' => $this->ours->id,
        'visiting_team_id' => $this->theirs->id,
        'start_date_time' => '2026-09-18 20:00:00',
    ]);

    ['linked' => $linked] = runMatcher();

    expect($linked)->toBe(0);
});
