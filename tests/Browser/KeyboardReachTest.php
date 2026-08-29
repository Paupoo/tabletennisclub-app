<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

/*
 * Fiche KB-1 de l'audit UX. Quinze commandes de l'application étaient écrites
 * en <div wire:click> : la touche Tab passait par-dessus, Entrée et Espace ne
 * déclenchaient rien, et un lecteur d'écran les annonçait comme du texte.
 *
 * Le pire cas est ici : le choix de formule de l'affiliation est un choix
 * exclusif et obligatoire. Sans souris, on ne pouvait pas s'affilier au club.
 *
 * Un choix exclusif est un groupe de boutons radio. L'élément natif apporte
 * gratuitement le focus, les flèches, le groupement et l'annonce « 1 sur 2 » —
 * c'est le premier principe d'ARIA : ne pas réécrire ce que le navigateur sait
 * déjà faire.
 */

/**
 * Les commandes du choix de licence, telles qu'un clavier les voit.
 *
 * On lit le vocabulaire du domaine (`competitive` / `recreative`) plutôt qu'une
 * classe ou une position : c'est ce qui reste vrai après un changement de style.
 */
const LICENCE_CONTROLS = <<<'JS'
(() => {
  const radios = [...document.querySelectorAll('input[type="radio"]')]
    .filter((r) => ['competitive', 'recreative'].includes(r.value));

  return {
    count: radios.length,
    tabbable: radios.filter((r) => !r.disabled && r.tabIndex >= 0).length,
    groups: [...new Set(radios.map((r) => r.name))].length,
    checked: (radios.find((r) => r.checked) || {}).value ?? null,
    named: radios.filter((r) => {
      const label = r.labels && r.labels.length
        ? r.labels[0].textContent
        : (r.getAttribute('aria-label') || '');

      return label.trim().length > 0;
    }).length,
  };
})()
JS;

/**
 * Le nom accessible de chaque commande atteignable au Tab dans la première carte
 * téléphone de la liste.
 *
 * Un `<div>` cliquable n'y apparaît pas : c'est exactement ce qu'on veut voir.
 */
const CARD_KEYBOARD_ACTIONS = <<<'JS'
(() => {
  const card = document.querySelector('[wire\\:key^="mobile-contact-"]');
  if (! card) return ['AUCUNE CARTE TÉLÉPHONE RENDUE'];

  return [...card.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
    .filter((el) => ! el.disabled && el.tabIndex >= 0)
    .map((el) => (el.getAttribute('aria-label') || el.textContent || '').replace(/\s+/g, ' ').trim())
    .filter((name) => name.length > 0);
})()
JS;

beforeEach(function (): void {
    Season::factory()->create([
        'is_active' => true,
        'affiliations_open' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);

    $this->member = User::factory()->create(['federation_licence_type' => 'LR']);
});

it('lets a keyboard choose the affiliation formula', function (): void {
    $this->actingAs($this->member);

    $page = visit(route('admin.user.registration-management', $this->member));

    $result = $page->script(LICENCE_CONTROLS);
    $licence = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($licence['count'])->toBe(2, 'Les deux formules doivent être des champs de formulaire, pas des <div> cliquables.');
    expect($licence['tabbable'])->toBe(2, 'Les deux formules doivent être atteignables au Tab.');
    expect($licence['groups'])->toBe(1, 'Les deux formules sont un choix exclusif : un seul groupe radio, donc un seul nom.');
    expect($licence['named'])->toBe(2, 'Chaque formule doit porter un nom accessible.');
    expect($licence['checked'])->toBe('recreative', 'La formule courante doit être celle qui est cochée.');
});

/*
 * La carte contact sur téléphone n'offrait au clavier que « Supprimer » : on
 * pouvait détruire un contact, pas l'ouvrir.
 *
 * Le remède n'est pas d'ajouter un bouton « Détails » — <x-list-item> pose
 * l'identité et les actions sur une même ligne, et une action nommée de plus
 * réduirait le nom à 81 px (voir MobileMemberCardTest). C'est le nom lui-même
 * qui devient la commande : zéro largeur en plus, et son nom accessible dit
 * déjà quel contact on ouvre.
 */
it('lets a keyboard open a contact, not only delete it', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();

    Contact::factory()->create([
        'first_name' => 'Gilles',
        'last_name' => 'Bernard',
        'email' => 'gilles.bernard@example.test',
    ]);

    $this->actingAs($admin);

    $page = visit(route('admin.website.contacts.index'))->resize(375, 812);

    $result = $page->script(CARD_KEYBOARD_ACTIONS);
    $actions = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($actions)->toBe(['Gilles Bernard', 'Supprimer']);
});
