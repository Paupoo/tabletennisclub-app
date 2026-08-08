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
    expect($state['announced'])->toBe('Control+K Slash', 'Les deux raccourcis doivent être annoncés aux technologies d\'assistance.');
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

/*
 * Un raccourci que rien n'annonce ne sert qu'à ceux qui le devinent. Le badge
 * est posé par le composant partagé, à l'endroit exact où Mary rend un
 * `suffix` — dernier enfant du <label class="input"> — donc il suit la mise en
 * page du champ sans que dix-neuf écrans aient à le déclarer.
 *
 * Le libellé suit le clavier : ⌘K sur un Mac, Ctrl K ailleurs. C'est ce qu'une
 * chaîne PHP ne peut pas faire, le serveur ne sachant rien de la machine.
 */
it('shows the shortcut on the search field', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(<<<'JS'
    (() => {
      const hint = document.querySelector('[data-search-hint]');
      if (! hint) return { found: false };

      const field = hint.closest('label.input')?.querySelector('input');
      const bound = [...field?.attributes || []].find((a) => a.name.startsWith('wire:model'));

      return {
        found: true,
        label: hint.textContent.replace(/\s+/g, ' ').trim(),
        visible: hint.getClientRects().length > 0,
        besideSearch: bound ? bound.value : null,
      };
    })()
    JS);

    $hint = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($hint['found'])->toBeTrue('Le champ de recherche doit annoncer son raccourci.');
    expect($hint['besideSearch'])->toBe('search', 'Le badge doit être posé sur le champ de recherche.');
    expect($hint['visible'])->toBeTrue();
    expect($hint['label'])->toBe('Ctrl K', 'Sur un clavier non-Mac, le badge annonce Ctrl.');
});

/*
 * Échap vide la recherche. Le champ garde le focus : on efface pour retaper,
 * pas pour partir.
 *
 * Le geste s'arrête là — sans quoi il fermerait aussi le tiroir qui contient la
 * recherche, et on perdrait le contexte en voulant corriger un mot. Sur un
 * champ déjà vide il n'y a rien à effacer : la touche repart, et c'est le
 * tiroir qui se ferme. Un Échap efface, le second referme.
 */
it('clears the search on Escape and keeps the cursor there', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(<<<'JS'
    (() => {
      const field = [...document.querySelectorAll('input')].find((input) =>
        [...input.attributes].some((a) => a.name.startsWith('wire:model') && a.value === 'search')
        && input.getClientRects().length > 0);

      field.focus();
      field.value = 'Dupont';
      field.dispatchEvent(new Event('input', { bubbles: true }));

      const filled = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      field.dispatchEvent(filled);

      const afterFilled = { value: field.value, focused: document.activeElement === field, stopped: filled.defaultPrevented };

      const empty = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      field.dispatchEvent(empty);

      return { afterFilled, emptyStopped: empty.defaultPrevented };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['afterFilled']['value'])->toBe('', 'Échap doit vider le champ.');
    expect($state['afterFilled']['focused'])->toBeTrue('Le curseur doit rester dans le champ pour retaper.');
    expect($state['afterFilled']['stopped'])->toBeTrue('Le geste s\'arrête au champ : il ne doit pas fermer le tiroir par-dessus.');
    expect($state['emptyStopped'])->toBeFalse('Sur un champ vide, Échap repart — c\'est lui qui referme le tiroir.');
});

/*
 * « / » amène aussi le curseur dans la recherche — une frappe au lieu de deux.
 * L'habitude vient de Vim, où « / » ouvre la recherche ; GitHub, Gmail et
 * Wikipédia l'ont reprise.
 *
 * Le piège est que « / » est un caractère qu'on tape. Sans garde, il
 * disparaîtrait au milieu d'une adresse ou d'un IBAN en emportant le curseur
 * ailleurs — un bug qu'on ne reproduit jamais quand on le cherche. La touche
 * n'est donc interceptée que hors d'un champ de saisie.
 */
it('reaches the search with a single slash', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(<<<'JS'
    (() => {
      document.body.focus();

      const fromPage = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true });
      document.body.dispatchEvent(fromPage);

      const active = document.activeElement;
      const bound = [...active.attributes || []].find((a) => a.name.startsWith('wire:model'));

      return { focused: bound ? bound.value : null, prevented: fromPage.defaultPrevented };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['focused'])->toBe('search', '« / » doit amener le curseur dans la recherche.');
    expect($state['prevented'])->toBeTrue('Sans preventDefault, une barre oblique s\'écrirait dans le champ.');
});

/*
 * Le garde : dans un champ de saisie, « / » reste une barre oblique.
 */
it('leaves the slash alone while you are typing', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(<<<'JS'
    (() => {
      const field = [...document.querySelectorAll('input')].find((input) =>
        [...input.attributes].some((a) => a.name.startsWith('wire:model') && a.value === 'search')
        && input.getClientRects().length > 0);

      field.focus();

      const typed = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true });
      field.dispatchEvent(typed);

      return { prevented: typed.defaultPrevented };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['prevented'])->toBeFalse(
        'Dans un champ de saisie, « / » doit rester un caractère — sinon il disparaît au milieu d\'un mot.',
    );
});

/*
 * Une modale prend tout l'écran à qui l'ouvre. La recherche reste pourtant
 * derrière, et « géométriquement visible » : sans garde, « / » irait y placer
 * le curseur sous le voile, là où l'utilisateur ne peut ni le voir ni le sortir.
 *
 * Mary marque la modale ouverte d'un `.modal-open` — le raccourci ne vise donc
 * un champ que s'il n'est pas derrière elle.
 */
it('does not aim behind an open modal', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script(<<<'JS'
    (() => {
      const dialog = document.querySelector('dialog.modal');
      if (! dialog) return { skipped: 'aucune modale sur cet écran' };

      dialog.classList.add('modal-open');
      document.body.focus();

      const slash = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true });
      document.body.dispatchEvent(slash);

      const active = document.activeElement;
      const bound = [...active.attributes || []].find((a) => a.name.startsWith('wire:model'));

      dialog.classList.remove('modal-open');

      return { focused: bound ? bound.value : null, prevented: slash.defaultPrevented };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['skipped'] ?? null)->toBeNull('Cet écran doit porter au moins une modale pour que le test ait un sens.');
    expect($state['focused'])->not->toBe('search', 'Le curseur ne doit pas partir derrière la modale.');
    expect($state['prevented'])->toBeFalse('Sans cible atteignable, la touche repart.');
});
