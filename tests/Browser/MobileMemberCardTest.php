<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;

/*
 * Les cartes téléphone posaient l'identité et les actions sur une même ligne,
 * ce que fait <x-list-item>. Le gabarit tenait tant qu'une ligne portait des
 * icônes ; il a cédé quand 0a9d45df a donné à chaque ligne une action nommée :
 * sur 335 px de carte, « Modifier » et « Plus » en prenaient 156, et le nom se
 * retrouvait dans 81 px — « Gilles Bernard » y était déjà coupé.
 *
 * Un nom, un titre, se lisent en entier : ils peuvent passer à la ligne, pas
 * être tranchés.
 */

const CARD_IDENTITY = <<<'JS_WRAP'
(() => {
  // Les préfixes divergent d'un écran à l'autre : « mobile- » partout sauf les
  // réunions, en « mob- ».
  const cards = [...document.querySelectorAll('[wire\\:key^="mobile-"], [wire\\:key^="mob-"]')]
    .filter(el => el.getBoundingClientRect().height > 0);

  const cut = [];
  cards.forEach(card => {
    card.querySelectorAll('div, span').forEach(el => {
      const t = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (!t) return;
      // Une adresse se coupe légitimement : elle n'a pas à tenir sur 335 px.
      if (/@/.test(t)) return;
      if (el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 1) {
        cut.push(t.slice(0, 40) + ' (' + Math.round(el.clientWidth) + '/' + Math.round(el.scrollWidth) + 'px)');
      }
    });
  });

  return JSON.stringify({ cartes: cards.length, coupes: cut.slice(0, 5) });
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
    $this->actingAs($this->admin);
});

it('reads a compound member name in full on a phone', function (): void {
    User::factory()->create([
        'first_name' => 'Jean-Christophe',
        'last_name' => 'Vandenbroecke-Dupuis',
        'email' => 'jc.vandenbroecke@example.com',
    ]);

    $reading = json_decode((string) visit(route('admin.users.index'))->resize(375, 812)->script(CARD_IDENTITY), true);

    expect($reading['cartes'])->toBeGreaterThan(0, 'the phone list should render member cards')
        ->and($reading['coupes'])->toBe([], 'a member name belongs on the card in full, wrapped if need be');
});

it('reads the title of every other phone list in full', function (string $route): void {
    Tournament::factory()->create(['name' => 'Tournoi interclubs des jeunes de la province du Brabant wallon']);
    Meeting::factory()->create(['title' => 'Assemblée générale extraordinaire du comité directeur', 'created_by' => $this->admin->id]);
    NewsPost::factory()->create(['title' => 'Le club remporte le championnat provincial pour la troisième fois']);
    EventPost::factory()->create(['title' => 'Souper annuel des membres et de leurs familles au club-house']);

    $reading = json_decode((string) visit(route($route))->resize(375, 812)->script(CARD_IDENTITY), true);

    expect($reading['cartes'])->toBeGreaterThan(0, 'the phone list should render cards')
        ->and($reading['coupes'])->toBe([], 'a title belongs on the card in full, wrapped if need be');
})->with([
    'admin.tournaments.index',
    'admin.meetings.index',
    'admin.website.articles.index',
    'admin.website.events.index',
]);
