<?php

declare(strict_types=1);

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * « A joué » se lit sur la feuille de match de la fédération, pas sur la compo.
 *
 * La fixture `get-matches-with-details.xml` porte quatre feuilles :
 * - PBBWH01/021, rapportée : domicile 166488, 105175, 136783, 101137 ;
 * - PBBWH01/022, rapportée : le visiteur 101564 est forfait sur ses quatre
 *   lignes, ses trois coéquipiers jouent normalement ;
 * - PBBWV01/305, rapportée, avec un double anonyme en position 7 ;
 * - PBBWH01/001, pas encore encodée (DetailsCreated faux).
 */
beforeEach(function (): void {
    // Un seul stub, posé une fois : `Http::fake()` empile, et un second appel
    // dans un test ne serait jamais consulté.
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

    $this->ourClub = Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
    $this->theirClub = Club::factory()->create(['is_own_club' => false, 'licence' => 'BBW350']);

    $this->ourTeam = Team::factory()->create([
        'club_id' => $this->ourClub->id,
        'league_id' => $this->league->id,
        'season_id' => $this->season->id,
    ]);
});

/** A past fixture of our team, carrying the federation's handle. */
function hasPlayedFixture(string $afttMatchId, bool $atHome = true): Interclub
{
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
        'visited_team_id' => $atHome ? test()->ourTeam->id : $theirs->id,
        'visiting_team_id' => $atHome ? $theirs->id : test()->ourTeam->id,
    ]);
}

function hasPlayedImport(): void
{
    app(AfttResultsImporter::class)->import(test()->season, 26, app(TabtClient::class));
}

/** @return object{has_played: int|bool, is_selected: int|bool, is_subscribed: int|bool, availability: string|null, availability_note: string|null, selection_confirmed_at: string|null}|null */
function rosterRow(Interclub $interclub, User $member): ?object
{
    return DB::table('interclub_user')
        ->where('interclub_id', $interclub->id)
        ->where('user_id', $member->id)
        ->first();
}

/** Save a 10-6 for this fixture from the results screen, as a captain would. */
function hasPlayedTypeScore(Interclub $match): void
{
    $result = InterclubResult::where('interclub_id', $match->id)->firstOrFail();

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $result->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 10)
        ->set('scoreThem', 6)
        ->call('save')
        ->assertHasNoErrors();
}

describe('the import of a reported sheet', function (): void {
    it('marks as played every member of ours the sheet names, creating the roster row when there is none', function (): void {
        $first = User::factory()->create(['licence' => '166488']);
        $second = User::factory()->create(['licence' => '105175']);
        $match = hasPlayedFixture('PBBWH01/021');

        hasPlayedImport();

        expect((bool) rosterRow($match, $first)->has_played)->toBeTrue()
            ->and((bool) rosterRow($match, $second)->has_played)->toBeTrue()
            ->and((bool) rosterRow($match, $first)->is_selected)->toBeFalse();
    });

    it('never touches the availability nor the selection of an existing roster row', function (): void {
        $member = User::factory()->create(['licence' => '166488']);
        $other = User::factory()->create(['licence' => '105175']);
        $match = hasPlayedFixture('PBBWH01/021');
        $sentAt = now()->subDays(6)->startOfMinute();

        $match->users()->attach($member->id, [
            'is_subscribed' => true,
            'is_selected' => true,
            'availability' => 'maybe',
            'availability_note' => 'Après 20 h',
            'selection_confirmed_at' => $sentAt,
        ]);
        $match->users()->attach($other->id, ['is_selected' => false, 'availability' => 'available']);

        hasPlayedImport();

        $row = rosterRow($match, $member);

        expect((bool) $row->has_played)->toBeTrue()
            ->and((bool) $row->is_subscribed)->toBeTrue()
            ->and((bool) $row->is_selected)->toBeTrue()
            ->and($row->availability)->toBe('maybe')
            ->and($row->availability_note)->toBe('Après 20 h')
            ->and($row->selection_confirmed_at)->not->toBeNull()
            ->and((bool) rosterRow($match, $other)->is_selected)->toBeFalse()
            ->and(rosterRow($match, $other)->availability)->toBe('available')
            ->and(DB::table('interclub_user')->where('interclub_id', $match->id)->count())->toBe(2);
    });

    it('marks as not played a member of ours the sheet does not name', function (): void {
        User::factory()->create(['licence' => '166488']);
        $benched = User::factory()->create(['licence' => '999001']);
        $match = hasPlayedFixture('PBBWH01/021');

        // Ce qu'aurait écrit la saisie manuelle du score : la compo, jouée ou non.
        $match->users()->attach($benched->id, ['is_selected' => true, 'has_played' => true]);

        hasPlayedImport();

        expect((bool) rosterRow($match, $benched)->has_played)->toBeFalse()
            ->and((bool) rosterRow($match, $benched)->is_selected)->toBeTrue();
    });

    it('writes nothing for a licence of ours that matches no member', function (): void {
        $member = User::factory()->create(['licence' => '166488']);
        $match = hasPlayedFixture('PBBWH01/021');

        hasPlayedImport();

        // Quatre licences de notre côté sur la feuille, une seule connue.
        expect(DB::table('interclub_user')->where('interclub_id', $match->id)->pluck('user_id')->all())
            ->toBe([$member->id]);
    });

    it('reads our side only, when we play away', function (): void {
        $homePlayer = User::factory()->create(['licence' => '166488']);
        $awayPlayer = User::factory()->create(['licence' => '101106']);
        $match = hasPlayedFixture('PBBWH01/021', atHome: false);

        hasPlayedImport();

        // 166488 joue pour le club d'en face ce soir-là : un membre de chez nous
        // ne peut pas avoir joué pour l'adversaire.
        expect(rosterRow($match, $homePlayer))->toBeNull()
            ->and((bool) rosterRow($match, $awayPlayer)->has_played)->toBeTrue();
    });

    it('leaves the roster alone while the sheet is an empty shell', function (): void {
        $member = User::factory()->create(['licence' => '166488']);
        $match = hasPlayedFixture('PBBWH01/001');
        $match->users()->attach($member->id, ['is_selected' => true, 'has_played' => true]);
        $stranger = User::factory()->create(['licence' => '105175']);

        hasPlayedImport();

        expect((bool) rosterRow($match, $member)->has_played)->toBeTrue()
            ->and(rosterRow($match, $stranger))->toBeNull();
    });

    it('does not count a player named forfeit on every line of theirs', function (): void {
        $forfeited = User::factory()->create(['licence' => '101564']);
        $teammate = User::factory()->create(['licence' => '131772']);
        $match = hasPlayedFixture('PBBWH01/022', atHome: false);

        hasPlayedImport();

        expect((bool) rosterRow($match, $teammate)->has_played)->toBeTrue()
            ->and(rosterRow($match, $forfeited))->toBeNull();
    });

    it('counts the player whose opponent did not turn up', function (): void {
        // 101331 joue domicile ; son adversaire de la ligne 4 est forfait.
        $member = User::factory()->create(['licence' => '101331']);
        $match = hasPlayedFixture('PBBWH01/022');

        hasPlayedImport();

        expect((bool) rosterRow($match, $member)->has_played)->toBeTrue();
    });

    it('names nobody for the double', function (): void {
        $this->league->update(['aftt_division_id' => 8721]);
        $match = hasPlayedFixture('PBBWV01/305');

        hasPlayedImport();

        expect(InterclubIndividualMatch::where('interclub_id', $match->id)->where('is_double', true)->exists())->toBeTrue()
            ->and(DB::table('interclub_user')->where('interclub_id', $match->id)->count())->toBe(0);
    });

    it('lands on the same roster when run twice', function (): void {
        $member = User::factory()->create(['licence' => '166488']);
        $benched = User::factory()->create(['licence' => '999001']);
        $match = hasPlayedFixture('PBBWH01/021');
        $match->users()->attach($benched->id, ['is_selected' => true, 'has_played' => true]);

        hasPlayedImport();
        $firstPass = DB::table('interclub_user')->where('interclub_id', $match->id)->orderBy('user_id')->get(['user_id', 'has_played'])->toArray();

        hasPlayedImport();
        $secondPass = DB::table('interclub_user')->where('interclub_id', $match->id)->orderBy('user_id')->get(['user_id', 'has_played'])->toArray();

        expect($secondPass)->toEqual($firstPass)
            ->and((bool) rosterRow($match, $member)->has_played)->toBeTrue()
            ->and((bool) rosterRow($match, $benched)->has_played)->toBeFalse();
    });
});

describe('a line forfeited by one of ours', function (): void {
    it('does not make a player of a member who only forfeited, and does when they played another line', function (): void {
        $match = hasPlayedFixture('PBBWH01/099');
        $onlyForfeited = User::factory()->create();
        $forfeitedOnce = User::factory()->create();

        // Forfait de notre côté : la ligne est perdue, sans sets.
        InterclubIndividualMatch::factory()->forfeited()->for($match)->for($onlyForfeited)->create(['position' => 1, 'we_won' => false]);
        InterclubIndividualMatch::factory()->forfeited()->for($match)->for($onlyForfeited)->create(['position' => 5, 'we_won' => false]);
        InterclubIndividualMatch::factory()->forfeited()->for($match)->for($forfeitedOnce)->create(['position' => 2, 'we_won' => false]);
        InterclubIndividualMatch::factory()->for($match)->for($forfeitedOnce)->create(['position' => 6, 'our_sets' => 3, 'their_sets' => 1, 'we_won' => true]);

        $this->artisan('interclubs:record-who-played')->assertSuccessful();

        expect(rosterRow($match, $onlyForfeited))->toBeNull()
            ->and((bool) rosterRow($match, $forfeitedOnce)->has_played)->toBeTrue();
    });
});

describe('the backfill from the sheets already on file', function (): void {
    it('recomputes who played from the stored lines, without asking the federation', function (): void {
        $match = hasPlayedFixture('PBBWH01/098');
        $played = User::factory()->create();
        $benched = User::factory()->create();
        $match->users()->attach($benched->id, ['is_selected' => true, 'has_played' => true]);

        InterclubIndividualMatch::factory()->for($match)->for($played)->create(['position' => 1]);
        InterclubIndividualMatch::factory()->for($match)->for($played)->create(['position' => 2]);
        InterclubIndividualMatch::factory()->double()->for($match)->create();

        $untouched = hasPlayedFixture('PBBWH01/097');
        $untouched->users()->attach($benched->id, ['is_selected' => true, 'has_played' => true]);

        $this->artisan('interclubs:record-who-played')->assertSuccessful();

        Http::assertNothingSent();

        expect((bool) rosterRow($match, $played)->has_played)->toBeTrue()
            ->and((bool) rosterRow($match, $benched)->has_played)->toBeFalse()
            // Pas de feuille, pas de verdict : la saisie manuelle reste.
            ->and((bool) rosterRow($untouched, $benched)->has_played)->toBeTrue();
    });
});

describe('the captain screen', function (): void {
    it('counts the matches a player played, once the sheet is imported', function (): void {
        $captain = User::factory()->isCompetitor()->create();
        $member = User::factory()->isCompetitor()->create(['licence' => '166488']);
        $this->ourTeam->update(['captain_id' => $captain->id]);
        $this->ourTeam->users()->attach([$captain->id, $member->id]);

        hasPlayedFixture('PBBWH01/021');

        $upcoming = Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $this->ourTeam->id,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);

        hasPlayedImport();

        $roster = Livewire::actingAs($captain)
            ->test('pages::club-events.interclubs.captain-selection')
            ->call('openSelection', $upcoming->id)
            ->viewData('roster');

        expect(collect($roster)->firstWhere('id', $member->id)['matches_played'])->toBe(1)
            ->and(collect($roster)->firstWhere('id', $captain->id)['matches_played'])->toBe(0);
    });
});

describe('a score typed by hand', function (): void {
    it('stands in for the sheet until it comes, without counting the named walkover', function (): void {
        $match = hasPlayedFixture('PBBWH01/096');
        $lined = User::factory()->create();
        $walkover = User::factory()->create();
        $match->users()->attach($lined->id, ['is_selected' => true, 'selection_confirmed_at' => now()->subDays(5)]);
        $match->users()->attach($walkover->id, ['is_selected' => true, 'is_walkover' => true, 'selection_confirmed_at' => now()->subDays(5)]);

        hasPlayedTypeScore($match);

        expect((bool) rosterRow($match, $lined)->has_played)->toBeTrue()
            ->and((bool) rosterRow($match, $walkover)->has_played)->toBeFalse();
    });

    it('never overrules the sheet once it is on file', function (): void {
        $match = hasPlayedFixture('PBBWH01/095');
        $played = User::factory()->create();
        $benched = User::factory()->create();
        $match->users()->attach($benched->id, ['is_selected' => true, 'selection_confirmed_at' => now()->subDays(5)]);
        InterclubIndividualMatch::factory()->for($match)->for($played)->create(['position' => 1]);
        InterclubIndividualMatch::factory()->for($match)->for($played)->create(['position' => 2]);
        $this->artisan('interclubs:record-who-played')->assertSuccessful();

        hasPlayedTypeScore($match);

        expect((bool) rosterRow($match, $benched)->has_played)->toBeFalse()
            ->and((bool) rosterRow($match, $played)->has_played)->toBeTrue();
    });
});
