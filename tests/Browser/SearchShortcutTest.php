<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Ctrl+K (⌘K sur Mac) amène le curseur dans la recherche de la page.
 *
 * Dix-neuf écrans du back-office portent une recherche, toujours au même
 * endroit — l'en-tête. Y aller à la souris coûte un aller-retour depuis la
 * liste qu'on est en train de lire ; le raccourci est ce que tout le monde
 * essaie d'abord, parce que Slack, Linear et GitHub l'ont installé.
 *
 * Le navigateur réserve Ctrl+K à sa barre d'adresse : sans `preventDefault`,
 * le raccourci sortirait de l'application. C'est le point que ce test garde.
 */

/**
 * Presse Ctrl+K et rapporte ce que le raccourci a fait.
 *
 * L'événement est synthétique : il prouve la logique de l'écouteur, pas la
 * remise de la touche par le navigateur.
 */
const PRESS_CTRL_K = <<<'JS'
(() => {
  const event = new KeyboardEvent('keydown', {
    key: 'k', code: 'KeyK', ctrlKey: true, bubbles: true, cancelable: true,
  });

  window.dispatchEvent(event);

  const active = document.activeElement;
  const bound = [...active.attributes || []]
    .find((a) => a.name.startsWith('wire:model'));

  return {
    prevented: event.defaultPrevented,
    tag: active ? active.tagName.toLowerCase() : null,
    bound: bound ? bound.value : null,
    announced: active ? active.getAttribute('aria-keyshortcuts') : null,
  };
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('puts the cursor in the search box on Ctrl+K', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(PRESS_CTRL_K);
    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['tag'])->toBe('input', 'Ctrl+K doit donner le focus au champ de recherche.');
    expect($state['bound'])->toBe('search', 'Le champ focalisé doit être celui qui pilote la recherche.');
    expect($state['prevented'])->toBeTrue('Sans preventDefault, Ctrl+K part dans la barre d\'adresse du navigateur.');
    expect($state['announced'])->toBe('Control+K', 'Le raccourci doit être annoncé aux technologies d\'assistance.');
});

it('leaves a screen without a search box alone', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.interclubs.results'));

    $result = $page->script(PRESS_CTRL_K);
    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['prevented'])->toBeFalse(
        "Sans recherche à atteindre, le raccourci doit rendre la touche au navigateur plutôt que l'avaler.",
    );
});

/*
 * Sur téléphone la recherche vit derrière une loupe : le champ n'existe pas
 * encore quand la touche tombe. Le raccourci ouvre le panneau, puis y place le
 * curseur — sinon il ne ferait qu'ouvrir, et il faudrait viser au doigt.
 */
it('opens the phone search panel before aiming at it', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'))->resize(375, 812);

    $result = $page->script(<<<'JS'
    (() => {
      window.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'k', code: 'KeyK', ctrlKey: true, bubbles: true, cancelable: true,
      }));

      return new Promise((resolve) => setTimeout(() => {
        const active = document.activeElement;
        const bound = [...active.attributes || []]
          .find((a) => a.name.startsWith('wire:model'));

        resolve({
          tag: active ? active.tagName.toLowerCase() : null,
          bound: bound ? bound.value : null,
          visible: active ? active.getClientRects().length > 0 : false,
        });
      }, 400));
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['tag'])->toBe('input');
    expect($state['bound'])->toBe('search');
    expect($state['visible'])->toBeTrue('Le champ doit être réellement visible, pas seulement focalisé.');
});
