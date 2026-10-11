<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\InterclubForfeit;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| « Sélections de la semaine » (grilling du 2026-10-11) : d'un coup d'œil, qui
| joue, dans quelle équipe, contre qui, quand et où — les rencontres des sept
| prochains jours, en tuiles groupées par jour.
*/
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->member = activeMember($this->season);
    $this->ownClub = Club::factory()->ownClub()->create();
    $this->menLeague = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
});

function weeklyTeam(Club $club, League $league, string $name): Team
{
    return Team::factory()->create([
        'season_id' => $league->season_id,
        'league_id' => $league->id,
        'club_id' => $club->id,
        'name' => $name,
    ]);
}

function weeklyFixture(Team $home, Team $away, CarbonInterface $at): Interclub
{
    return Interclub::factory()->create([
        'season_id' => $home->season_id,
        'league_id' => $home->league_id,
        'visited_team_id' => $home->id,
        'visiting_team_id' => $away->id,
        'total_players' => 4,
        'is_bye' => false,
        'forfeit' => null,
        'start_date_time' => $at,
    ]);
}

it('shows the fixtures of the next seven days, and only those', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'A');
    $soon = weeklyTeam(Club::factory()->create(['name' => 'CTT Tourinnes']), $this->menLeague, 'A');
    $later = weeklyTeam(Club::factory()->create(['name' => 'Logis Auderghem']), $this->menLeague, 'N');
    $past = weeklyTeam(Club::factory()->create(['name' => 'AS Beauchamp']), $this->menLeague, 'B');

    weeklyFixture($soon, $ours, now()->addDays(2));
    weeklyFixture($ours, $later, now()->addDays(8));
    weeklyFixture($ours, $past, now()->subDay());

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertSee(__('Weekly selections'))
        ->assertSee('CTT Tourinnes A')
        ->assertDontSee('Logis Auderghem N')
        ->assertDontSee('AS Beauchamp B');
});

it('names the players of a lineup sent, and never those of a draft', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'A');
    $theirs = weeklyTeam(Club::factory()->create(['name' => 'CTT Tourinnes']), $this->menLeague, 'A');
    $sent = weeklyFixture($ours, $theirs, now()->addDays(1));
    $draft = weeklyFixture($theirs, $ours, now()->addDays(3));

    $sent->users()->attach(User::factory()->create(['first_name' => 'Julien', 'last_name' => 'Hermans'])->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    $draft->users()->attach(User::factory()->create(['first_name' => 'Loïc', 'last_name' => 'Dumont'])->id, ['is_selected' => true]);

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertSee('Hermans')
        ->assertDontSee('Dumont')
        ->assertSee(__('Lineup not sent yet'));
});

it('lists the players by force list, then surname, then first name, the walkover last', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'A');
    $fixture = weeklyFixture($ours, weeklyTeam(Club::factory()->create(['name' => 'CTT Tourinnes']), $this->menLeague, 'A'), now()->addDays(1));
    $sent = ['is_selected' => true, 'selection_confirmed_at' => now()];

    $fixture->users()->attach(User::factory()->create(['first_name' => 'Zoé', 'last_name' => 'Walkover', 'force_list' => 1, 'ranking' => 'B6'])->id, [...$sent, 'is_walkover' => true]);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Yann', 'last_name' => 'Nolist', 'force_list' => null, 'ranking' => 'NA'])->id, $sent);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Martin', 'force_list' => 4, 'ranking' => 'C2'])->id, $sent);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin', 'force_list' => 4, 'ranking' => 'C2'])->id, $sent);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'force_list' => 4, 'ranking' => 'C2'])->id, $sent);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Luc', 'last_name' => 'Zeller', 'force_list' => 2, 'ranking' => 'C0'])->id, $sent);

    $html = $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertSeeInOrder(['Zeller', 'Dupont', 'Martin</span>', 'Alice', 'Martin</span>', 'Bruno', 'Nolist', 'Walkover'], false)
        ->getContent();

    expect($html)->toMatch('/#2<\/span>\s*<span class="min-w-0 flex-1">\s*<span class="font-bold">Zeller<\/span>/')
        ->toMatch('/Zeller<\/span>.*?C0<\/span>/s')
        ->toMatch('/—<\/span>\s*<span class="min-w-0 flex-1">\s*<span class="font-bold">Nolist<\/span>/')
        ->not->toMatch('/Nolist.*?>NA<\/span>/s');
});

it('marks a forfeit and names nobody', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'C');
    $theirs = weeklyTeam(Club::factory()->create(['name' => 'Royal CTT Mont-St-Guibert']), $this->menLeague, 'B');
    $fixture = weeklyFixture($ours, $theirs, now()->addDays(2));
    $fixture->update(['forfeit' => InterclubForfeit::OPPONENT_FORFEIT]);
    $fixture->users()->attach(User::factory()->create(['first_name' => 'Benoît', 'last_name' => 'Leroy'])->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertSee(InterclubForfeit::OPPONENT_FORFEIT->label())
        ->assertDontSee('Benoît Leroy');
});

it('leaves a bye out', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'F');
    $bye = weeklyFixture($ours, weeklyTeam(Club::factory()->create(['name' => 'Exempt Club']), $this->menLeague, 'Z'), now()->addDays(2));
    $bye->update(['is_bye' => true]);

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertDontSee('Exempt Club Z')
        ->assertSee(__('No interclub match in the next seven days'));
});

it('sets apart the tiles the reader plays in, and links each to its match page', function (): void {
    $ours = weeklyTeam($this->ownClub, $this->menLeague, 'A');
    $theirs = weeklyTeam(Club::factory()->create(['name' => 'CTT Tourinnes']), $this->menLeague, 'A');
    $mine = weeklyFixture($ours, $theirs, now()->addDays(1));
    $other = weeklyFixture($theirs, $ours, now()->addDays(2));
    $mine->users()->attach($this->member->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);

    $html = $this->actingAs($this->member)->get(route('admin.user.weekly-selections'))->assertOk()->getContent();

    expect($html)->toContain('data-weekly-tile="' . $mine->id . '" data-mine="true"')
        ->not->toContain('data-weekly-tile="' . $other->id . '" data-mine="true"')
        ->toContain(route('admin.interclubs.my-match', $mine->id))
        ->toContain(route('admin.interclubs.my-match', $other->id));
});

it('orders a day by the clock, then by category, then by team', function (): void {
    $veterans = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
    $at = now()->addDays(2)->setTime(20, 0);
    $opponent = fn (League $league, string $name): Team => weeklyTeam(Club::factory()->create(['name' => $name]), $league, 'A');

    weeklyFixture(weeklyTeam($this->ownClub, $veterans, 'A'), $opponent($veterans, 'Fourth Club'), $at);
    weeklyFixture(weeklyTeam($this->ownClub, $this->menLeague, 'B'), $opponent($this->menLeague, 'Third Club'), $at);
    weeklyFixture(weeklyTeam($this->ownClub, $this->menLeague, 'A'), $opponent($this->menLeague, 'Second Club'), $at);
    weeklyFixture(weeklyTeam($this->ownClub, $veterans, 'B'), $opponent($veterans, 'First Club'), $at->copy()->setTime(14, 0));

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertOk()
        ->assertSeeInOrder(['First Club', 'Second Club', 'Third Club', 'Fourth Club']);
});

it('opens to whoever reads the directory, and to no one else', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.user.weekly-selections'))
        ->assertForbidden();

    $this->actingAs(User::factory()->isCommitteeMember()->create())
        ->get(route('admin.user.weekly-selections'))
        ->assertOk();
});

it('is closed while the interclubs module is off', function (): void {
    config(['features.' . Feature::Interclubs->value => false]);

    $this->actingAs($this->member)
        ->get(route('admin.user.weekly-selections'))
        ->assertNotFound();
});
