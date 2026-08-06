<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * La carte téléphone posait l'identité et les actions sur une même ligne.
 * Depuis que chaque ligne porte une action nommée, « Modifier » et « Plus »
 * prenaient 156 px des 335 de la carte : il en restait 81 pour le nom, et
 * « Gilles Bernard » y était déjà coupé.
 *
 * Un nom se lit en entier — il peut passer à la ligne, pas être tranché.
 */

const MEMBER_NAME_FIT = <<<'JS'
(() => {
  const cards = [...document.querySelectorAll('[wire\\:key^="mobile-user-"]')];
  const names = cards.map(card => card.querySelector('.font-medium')).filter(Boolean);

  return JSON.stringify(names.map(el => ({
    texte: (el.textContent || '').replace(/\s+/g, ' ').trim(),
    affiche: Math.round(el.clientWidth),
    reel: Math.round(el.scrollWidth),
    coupe: el.scrollWidth > el.clientWidth + 1,
  })));
})()
JS;

it('reads a compound member name in full on a phone', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($admin);

    User::factory()->create([
        'first_name' => 'Jean-Christophe',
        'last_name' => 'Vandenbroecke-Dupuis',
        'email' => 'jc.vandenbroecke@example.com',
    ]);

    $names = json_decode((string) visit(route('admin.users.index'))->resize(375, 812)->script(MEMBER_NAME_FIT), true);

    expect($names)->not->toBeEmpty('the phone list should render member cards');

    $cut = array_values(array_filter($names, fn (array $name): bool => $name['coupe']));

    expect($cut)->toBe([], 'a member name belongs on the card in full, wrapped if need be');
});
