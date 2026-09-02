<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;

beforeEach(function (): void {
    afttClubTeams('get-club-teams-bbw214-two-divisions.xml');
    afttFaultOn(reset: true);
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

it('creates a club it has never met, from its full name and its hall', function (): void {
    knownOpponents(except: ['BBW145']);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $created = Club::where('licence', 'BBW145')->first();

    // The short name is "Muppets"; three clubs in the province answer to some
    // variant of the same word, so the long one is what a member needs to read.
    expect($created->name)->toBe("Muppet's TT Auderghem")
        ->and($created->is_own_club)->toBeFalse()
        ->and($created->street)->toBe('Chaussee de Wavre 1690')
        ->and($created->city_code)->toBe('1160')
        ->and($created->city_name)->toBe('Bruxelles');
});

it('never renames a club the club itself curates', function (): void {
    knownOpponents(except: ['BBW145']);

    $curated = Club::factory()->create([
        'licence' => 'BBW145',
        'name' => 'MUPPETS T.T. AUDERGHEM',
        'is_own_club' => false,
        'street' => 'Chaussée de Wavre 1690',
        'city_code' => '1160',
        'city_name' => 'Bruxelles',
        'email_contact' => 'secretaire@muppets.example',
    ]);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect($curated->fresh()->name)->toBe('MUPPETS T.T. AUDERGHEM')
        ->and($curated->fresh()->street)->toBe('Chaussée de Wavre 1690')
        ->and($curated->fresh()->email_contact)->toBe('secretaire@muppets.example');
});

it('fills in an address the club never had, without touching one it has', function (): void {
    knownOpponents(except: ['BBW145']);

    $incomplete = Club::factory()->create([
        'licence' => 'BBW145',
        'name' => 'MUPPETS T.T. AUDERGHEM',
        'is_own_club' => false,
        'street' => null,
        'city_code' => null,
        'city_name' => 'Auderghem',
    ]);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect($incomplete->fresh()->street)->toBe('Chaussee de Wavre 1690')
        ->and($incomplete->fresh()->city_code)->toBe('1160')
        ->and($incomplete->fresh()->city_name)->toBe('Auderghem');
});

it('corrects a fixture in place, so what members said about it survives', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $match = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();
    $player = User::factory()->create();
    $match->users()->attach($player->id, ['availability' => 'yes']);

    // The federation moves a fixture; our row is stale until the next run.
    $match->update(['start_date_time' => '2026-12-25 09:00:00', 'address' => 'Somewhere else']);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $reimported = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($reimported->id)->toBe($match->id)
        ->and($reimported->start_date_time->toDateTimeString())->toBe('2026-09-18 19:45:00')
        ->and($reimported->address)->toStartWith('Complexe Sportif Jean Demeester')
        ->and($reimported->users()->where('users.id', $player->id)->exists())->toBeTrue();
});

it('does not multiply rows when it runs again', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $fixtures = Interclub::count();
    $teams = Team::count();
    $leagues = League::count();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect(Interclub::count())->toBe($fixtures)
        ->and(Team::count())->toBe($teams)
        ->and(League::count())->toBe($leagues);
});

it('drops a fixture the federation no longer lists, unless somebody answered on it', function (): void {
    knownOpponents();

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    $league = League::where('aftt_division_id', 9611)->first();
    $ourTeam = Team::where('club_id', $this->ownClub->id)->where('league_id', $league->id)->first();

    // Two fixtures the federation has since withdrawn — one nobody ever saw,
    // one a player already answered on.
    $forgotten = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH99/998',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $ourTeam->id,
    ]);

    $answered = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH99/999',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $ourTeam->id,
    ]);
    $answered->users()->attach(User::factory()->create()->id, ['availability' => 'yes']);

    $report = app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect(Interclub::find($forgotten->id))->toBeNull()
        ->and(Interclub::find($answered->id))->not->toBeNull();

    expect($report->changes)->toHaveKey('kept_orphans')
        ->and($report->changes['kept_orphans'])->toContain('PBBWH99/999');
});

it('leaves a hand-entered fixture alone, having never claimed it', function (): void {
    knownOpponents();

    $byHand = Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $this->season->id,
    ]);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect(Interclub::find($byHand->id))->not->toBeNull();
});

it('refuses a division it cannot model, by name, instead of guessing', function (): void {
    knownOpponents();

    // The federation's own youth division, attached to a team of ours. The club
    // has no youth team today; the day it enters one, this is what happens.
    afttClubTeams('get-club-teams-bbw214-with-youth.xml');

    $report = app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect(League::where('aftt_division_id', 9824)->exists())->toBeFalse()
        ->and($report->skipped_count)->toBe(1)
        ->and($report->changes['refused_divisions'][0])
        ->toContain('Jeunes');

    // The two divisions it does understand are imported all the same.
    expect(League::where('aftt_division_id', 9756)->exists())->toBeTrue()
        ->and(League::where('aftt_division_id', 9611)->exists())->toBeTrue();
});

it('counts what it created, and names what moved on the next run', function (): void {
    knownOpponents();

    $first = app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect($first->created_count)->toBe(Interclub::count())
        ->and($first->updated_count)->toBe(0);

    Interclub::where('aftt_match_id', 'PBBWH01/113')
        ->update(['start_date_time' => '2026-12-25 09:00:00']);

    $second = app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    expect($second->created_count)->toBe(0)
        ->and($second->updated_count)->toBe(1)
        ->and($second->changes['moved'])->toContain('PBBWH01/113');
});

it('writes nothing at all when the federation fails halfway through', function (): void {
    knownOpponents();

    // One division answers, the next faults — what a federation timeout looks
    // like from here. Nothing may reach the database.
    afttFaultOn(9611);

    expect(fn () => app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214'))
        ->toThrow(RuntimeException::class);

    expect(Interclub::count())->toBe(0)
        ->and(League::count())->toBe(0)
        ->and(Team::count())->toBe(0);
});

it('rebuilds the season from scratch when asked, and only that season', function (): void {
    knownOpponents();

    $otherSeason = Season::factory()->create(['name' => '2025-2026']);
    $keptLeague = League::factory()->create(['season_id' => $otherSeason->id]);
    $keptFixture = Interclub::factory()->create([
        'season_id' => $otherSeason->id,
        'league_id' => $keptLeague->id,
    ]);

    $staleLeague = League::factory()->create(['season_id' => $this->season->id]);
    $staleTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $staleLeague->id,
    ]);
    $staleFixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $staleLeague->id,
        'visited_team_id' => $staleTeam->id,
    ]);

    $report = app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214', fresh: true);

    expect(Interclub::find($staleFixture->id))->toBeNull()
        ->and(Team::find($staleTeam->id))->toBeNull()
        ->and(League::find($staleLeague->id))->toBeNull()
        ->and($report->is_fresh)->toBeTrue();

    // Last season is none of its business.
    expect(Interclub::find($keptFixture->id))->not->toBeNull()
        ->and(League::find($keptLeague->id))->not->toBeNull();

    // And the season is genuinely rebuilt, not merely emptied.
    expect(Interclub::where('season_id', $this->season->id)->count())->toBeGreaterThan(0);
});
