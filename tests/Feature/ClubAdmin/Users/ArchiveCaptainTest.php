<?php

declare(strict_types=1);

use App\Actions\User\SoftDeleteUserAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Archiver quelqu'un qui capitaine.
 *
 * Ce cas était impossible par accident : l'action refuse d'archiver un membre
 * affilié pour la saison en cours, et un capitaine était forcément un
 * compétiteur affilié. En ouvrant la capitainerie à tout le club, on ouvre
 * précisément aux profils qu'on *peut* archiver — le bénévole non affilié.
 *
 * `teams.captain_id` est une clé étrangère `nullOnDelete`, qui ne se déclenche
 * jamais sur un soft delete. Sans nettoyage explicite, la colonne pointerait sur
 * un archivé pendant que l'écran affiche « Non défini », parce que la relation
 * traverse `SoftDeletes`. Même raison que pour les assignations d'entraînement,
 * que cette action nettoie déjà à la main.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->ourClub = Club::factory()->ownClub()->create();
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->volunteer = User::factory()->isNotCompetitor()->create(['last_name' => 'Bénévole']);
});

function teamCaptainedBy(User $captain, string $name): Team
{
    return Team::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'club_id' => test()->ourClub->id,
        'name' => $name,
        'captain_id' => $captain->id,
    ]);
}

it('frees every team the archived member captained', function (): void {
    $teamD = teamCaptainedBy($this->volunteer, 'D');
    $teamE = teamCaptainedBy($this->volunteer, 'E');

    SoftDeleteUserAction::handle($this->volunteer);

    expect($teamD->fresh()->captain_id)->toBeNull()
        ->and($teamE->fresh()->captain_id)->toBeNull();
});

it('names the teams it freed, so the caller can say so', function (): void {
    teamCaptainedBy($this->volunteer, 'D');
    teamCaptainedBy($this->volunteer, 'E');

    $freed = SoftDeleteUserAction::handle($this->volunteer);

    expect($freed)->toEqualCanonicalizing(['D', 'E']);
});

it('returns nothing when the archived member captained no team', function (): void {
    expect(SoftDeleteUserAction::handle($this->volunteer))->toBe([]);
});

it('leaves other captains alone', function (): void {
    $other = User::factory()->isNotCompetitor()->create();
    $theirs = teamCaptainedBy($other, 'F');

    SoftDeleteUserAction::handle($this->volunteer);

    expect($theirs->fresh()->captain_id)->toBe($other->id);
});

/*
|--------------------------------------------------------------------------
| Et l'écran le dit
|--------------------------------------------------------------------------
|
| Nettoyer en silence reviendrait à faire perdre son capitaine à une équipe sans
| que personne ne l'apprenne. Le message arrive après coup plutôt qu'en
| pré-confirmation : il y a deux chemins d'archivage, dont un en masse avec sa
| propre modale, et un message a posteriori est exact pour les deux.
*/

/**
 * Le titre du toast Mary émis par le dernier appel.
 *
 * Mary n'écrit pas ses toasts dans le HTML : elle pousse un appel JS dans
 * l'effet `xjs` de la réponse Livewire. `assertSee()` ne prouverait donc rien
 * ici, et le flash de session ne survit pas au test.
 */
function toastTitle(object $component): string
{
    foreach ($component->effects['xjs'] ?? [] as $effect) {
        if (preg_match('/^toast\((.*)\)$/s', (string) ($effect['expression'] ?? ''), $matches) === 1) {
            return json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return '';
}

it('tells the operator which teams just lost their captain', function (): void {
    teamCaptainedBy($this->volunteer, 'D');
    teamCaptainedBy($this->volunteer, 'E');

    $admin = User::factory()->isAdmin()->create(['licence' => null]);

    $component = Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->volunteer->id)
        ->call('delete');

    expect(toastTitle($component))
        ->toContain(__('Teams left without a captain: :teams', ['teams' => 'D, E']));
});

it('says nothing about teams when the archived member captained none', function (): void {
    $admin = User::factory()->isAdmin()->create(['licence' => null]);

    $component = Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->volunteer->id)
        ->call('delete');

    expect(toastTitle($component))->toBe(__('User archived.'));
});

it('reports the teams freed by a bulk archive too', function (): void {
    teamCaptainedBy($this->volunteer, 'D');

    $admin = User::factory()->isAdmin()->create(['licence' => null]);

    $component = Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->set('selected', [(string) $this->volunteer->id])
        ->call('bulkArchive');

    expect(toastTitle($component))
        ->toContain(__('Teams left without a captain: :teams', ['teams' => 'D']));
});
