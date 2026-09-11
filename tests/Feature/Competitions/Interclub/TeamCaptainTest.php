<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const TEAM_EDIT_COMPONENT = 'pages::club-events.interclubs.teams.edit';

/**
 * Captaining a team one does not play in.
 *
 * Until now the captain had to be a member of the core — the picker only ever
 * offered `$teamMembers`, and `setCaptain()` added the player to the core as a
 * safeguard nobody could trigger. The only way round it was to sit in two cores
 * of the same category, which is precisely what the exclusivity rule now forbids.
 * Closing that door without opening this one would have removed a real capability.
 *
 * Widening the pool beyond competitors — a guardian standing in for a youth team,
 * say — is deliberately NOT here: `teams.captain_id` grants `access-selections`
 * and `access-results` through {@see AuthServiceProvider}, so it is
 * a permissions decision, not a team one.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'name' => 'D',
        'captain_id' => null,
    ]);

    $this->player = User::factory()->isCompetitor()->create(['ranking' => 'C6']);
    $this->team->users()->attach($this->player->id);

    $this->outsider = User::factory()->isCompetitor()->create(['ranking' => 'B6']);
    $this->admin = User::factory()->isAdmin()->create(['licence' => null]);
});

it('offers a competitor who is not in the core as a possible captain', function (): void {
    // Viser les options du sélecteur, pas le HTML : ce compétiteur figure aussi
    // dans la liste des candidats au noyau, et un assertSee() passerait même si
    // le sélecteur de capitaine ne l'offrait pas.
    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('search', $this->outsider->last_name);

    expect(collect($component->get('captainOptions'))->pluck('id'))
        ->toContain($this->outsider->id);
});

it('does not drag the captain into the core', function (): void {
    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('setCaptain', $this->outsider->id);

    expect($component->get('memberIds'))->not->toContain($this->outsider->id)
        ->and($component->get('captainId'))->toBe($this->outsider->id);
});

it('saves a captain who plays elsewhere', function (): void {
    Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('setCaptain', $this->outsider->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->team->fresh()->captain_id)->toBe($this->outsider->id)
        ->and($this->team->users()->pluck('users.id')->all())->toBe([$this->player->id]);
});

it('lets someone captain two teams of the same category', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'name' => 'E',
        'captain_id' => null,
    ]);

    foreach ([$this->team, $otherTeam] as $team) {
        Livewire::actingAs($this->admin)
            ->test(TEAM_EDIT_COMPONENT, ['team' => $team])
            ->call('setCaptain', $this->outsider->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    expect($this->team->fresh()->captain_id)->toBe($this->outsider->id)
        ->and($otherTeam->fresh()->captain_id)->toBe($this->outsider->id);
});

/*
|--------------------------------------------------------------------------
| N'importe quel membre du club peut capitainer
|--------------------------------------------------------------------------
|
| Capitainer n'est pas jouer. Un pensionné sympathisant, non affilié cette
| saison, ou un tuteur qui encadre bénévolement une équipe, doit pouvoir être
| nommé — c'est du temps donné au club, pas une place dans un noyau.
|
| Le vivier est donc « toute ligne `users` non archivée ». Un tuteur y entre le
| jour où il accepte son invitation : `teams.captain_id` pointe vers `users`, et
| un capitaine qui ne peut pas se connecter ne peut pas composer.
*/

it('offers a member who holds no competitive licence at all', function (): void {
    $volunteer = User::factory()->isNotCompetitor()->create([
        'last_name' => 'Bénévole',
        'licence' => null,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('search', 'Bénévole');

    expect(collect($component->get('captainOptions'))->pluck('id'))
        ->toContain($volunteer->id);
});

it('keeps an archived member out of the pool', function (): void {
    $gone = User::factory()->isNotCompetitor()->create(['last_name' => 'Parti']);
    $gone->delete();

    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('search', 'Parti');

    expect(collect($component->get('captainOptions'))->pluck('id'))
        ->not->toContain($gone->id);
});

it('puts the core first when nothing has been typed', function (): void {
    User::factory()->isNotCompetitor()->count(15)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('search', '');

    expect(collect($component->get('captainOptions'))->pluck('id')->first())
        ->toBe($this->player->id);
});

it('keeps the named captain visible once the search moves on', function (): void {
    $volunteer = User::factory()->isNotCompetitor()->create(['last_name' => 'Bénévole']);

    $component = Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->call('setCaptain', $volunteer->id)
        ->call('search', 'zzzzz-personne');

    expect(collect($component->get('captainOptions'))->pluck('id'))
        ->toContain($volunteer->id);
});

/*
|--------------------------------------------------------------------------
| Deux murs que la nomination ne voit pas
|--------------------------------------------------------------------------
|
| Les routes interclubs portent `verified`, et `EnsureProfileIsComplete` renvoie
| tout /admin/* vers l'assistant tant que la fiche est incomplète. Le profil que
| le club veut nommer — bénévole, parent, pensionné — est justement celui qui a
| le plus de chances de buter dessus.
|
| On ne bloque pas la nomination : nommer d'abord et régulariser ensuite est un
| ordre légitime. On refuse seulement que ça se découvre par téléphone.
*/

it('warns that the named captain has not confirmed their email', function (): void {
    $volunteer = User::factory()->isNotCompetitor()->create([
        'email_verified_at' => null,
        'birthdate' => now()->subYears(70),
        'phone_number' => '0470112233',
        'street' => 'Rue du Club 1',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->set('captainId', $volunteer->id)
        ->assertSee(__('This captain has not confirmed their email address yet.'));
});

it('warns that the named captain still has an incomplete profile', function (): void {
    $volunteer = User::factory()->isNotCompetitor()->create([
        'email_verified_at' => now(),
        'street' => null,
        'city_code' => null,
        'city_name' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->set('captainId', $volunteer->id)
        ->assertSee(__('This captain must complete their profile before they can compose a lineup.'));
});

it('says nothing when the named captain is ready', function (): void {
    $ready = User::factory()->isNotCompetitor()->create([
        'email_verified_at' => now(),
        'birthdate' => now()->subYears(70),
        'phone_number' => '0470112233',
        'street' => 'Rue du Club 1',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->set('captainId', $ready->id)
        ->assertDontSee(__('This captain has not confirmed their email address yet.'))
        ->assertDontSee(__('This captain must complete their profile before they can compose a lineup.'));
});

it('does not block saving a captain who is not ready yet', function (): void {
    $volunteer = User::factory()->isNotCompetitor()->create([
        'email_verified_at' => null,
        'street' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(TEAM_EDIT_COMPONENT, ['team' => $this->team])
        ->set('captainId', $volunteer->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->team->fresh()->captain_id)->toBe($volunteer->id);
});
