<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;

/*
 * Le panneau du jour vit dans une colonne de 384 px. La carte d'événement
 * basculait titre et badges sur une même ligne dès 640 px de *fenêtre*, sans
 * jamais regarder la place réelle : sur un match d'interclub, la pile
 * « Dom. » + division + « Pas de réponse » mangeait la ligne et laissait
 * ~60 px au titre, soit un mot par ligne et une carte étirée en hauteur.
 *
 * Le même match vu en « Tout le club » (un seul badge) tenait confortablement :
 * c'est donc la largeur disponible, pas celle de la fenêtre, qui doit décider
 * si les badges restent à droite ou passent à la ligne.
 */

const DAY_PANEL_MEASURE = <<<'JS'
(() => {
  const panel = document.querySelector('[x-ref="dayPanel"]');
  if (! panel) return { erreur: 'panneau introuvable' };

  const cards = [...panel.querySelectorAll('.border-l-4')]
    .filter(el => el.getBoundingClientRect().height > 0);

  if (! cards.length) return { cartes: 0, titre_largeur: 0, carte_hauteur: 0, badges_sur_la_ligne: false };

  const card = cards[0];
  const blocks = [...card.children].filter(el => el.classList.contains('z-10'));

  return {
    cartes: cards.length,
    titre_largeur: Math.round(card.querySelector('p').getBoundingClientRect().width),
    carte_hauteur: Math.round(card.getBoundingClientRect().height),
    badges_sur_la_ligne: blocks.length === 2
      && blocks[1].getBoundingClientRect().left >= blocks[0].getBoundingClientRect().right - 1,
  };
})()
JS;

beforeEach(function (): void {
    $season = makeActiveSeason();
    $this->player = User::factory()->create();

    $match = now()->addDays(7)->setTime(19, 45);

    $ours = Club::factory()->create(['is_own_club' => true, 'name' => 'CTT Ottignies-Blocry']);
    $theirs = Club::factory()->create(['is_own_club' => false, 'name' => 'REP Nivellois']);
    $league = League::factory()->create(['season_id' => $season->id, 'division' => '4B']);

    $shared = ['season_id' => $season->id, 'league_id' => $league->id, 'captain_id' => $this->player->id];
    $our = Team::factory()->create([...$shared, 'club_id' => $ours->id, 'name' => 'D']);
    $opponent = Team::factory()->create([...$shared, 'club_id' => $theirs->id, 'name' => 'E']);

    $our->users()->attach($this->player->id);

    Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $our->id,
        'visiting_team_id' => $opponent->id,
        'week_number' => 3,
        'start_date_time' => $match,
        'address' => 'Complexe Sportif Jean Demeester, Rue de l\'Invasion, 80, 1340 Ottignies Lln',
    ]);

    $this->dayUrl = route('admin.user.calendar', $this->player, absolute: false)
        . '?month=' . $match->format('Y-m') . '&selectedDay=' . $match->format('Y-m-d');

    $this->actingAs($this->player);
});

it('leaves the event title a readable width in the narrow day panel', function (): void {
    $panel = (array) visit($this->dayUrl)->resize(1440, 900)->script(DAY_PANEL_MEASURE);

    expect($panel['cartes'])->toBe(1)
        ->and($panel['titre_largeur'])->toBeGreaterThan(200,
            'a title squeezed under 200 px in a 384 px panel breaks into one word per line')
        ->and($panel['carte_hauteur'])->toBeLessThan(140,
            'a single match should not stretch down the whole panel');
});

/*
 * Le garde-fou inverse : quand la colonne est large (sous `lg`, le panneau
 * passe pleine largeur), les badges doivent rester à droite du titre.
 */
it('keeps the badges beside the title when the panel is wide', function (): void {
    $panel = (array) visit($this->dayUrl)->resize(1000, 900)->script(DAY_PANEL_MEASURE);

    expect($panel['cartes'])->toBe(1)
        ->and($panel['badges_sur_la_ligne'])->toBeTrue();
});
