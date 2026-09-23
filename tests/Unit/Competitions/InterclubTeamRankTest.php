<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Team;

/**
 * Le rang d'une équipe dans son club, déduit de son nom.
 *
 * Rien en base ne dit qu'une équipe est « au-dessus » d'une autre : `teams` porte
 * un nom et une division, et la division ne peut pas servir — « 3A » et « 3C »
 * sont deux séries du *même* niveau, et l'on rencontre des libellés comme
 * « BARRAGES P3 vers P2 » ou « TF ». Le nom est le seul rang exploitable, et
 * c'est celui que le règlement suppose quand il écrit « équipe A », « équipe B »
 * (voir les exemples de l'article C.22.1.1).
 */
it('ranks the lettered teams in alphabetical order', function (string $name, int $rank): void {
    expect(Team::rankOf($name))->toBe($rank);
})->with([
    ['A', 1],
    ['B', 2],
    ['C', 3],
    ['Z', 26],
]);

/**
 * Passé Z, le club numérote : 1, 2, 3… Ces équipes viennent *après* les lettres,
 * et un tri alphabétique naïf ferait exactement l'inverse — en ASCII les
 * chiffres précèdent les lettres, donc l'équipe « 1 », la plus faible, passerait
 * devant l'équipe « A ».
 */
it('ranks the numbered teams after the lettered ones', function (string $name, int $rank): void {
    expect(Team::rankOf($name))->toBe($rank);
})->with([
    ['1', 27],
    ['2', 28],
    ['10', 36],
]);

it('places every numbered team below every lettered one', function (): void {
    expect(Team::rankOf('1'))->toBeGreaterThan(Team::rankOf('Z'));
});

/**
 * Un nom qui ne se range pas rend `null`, et l'appelant doit alors renoncer à la
 * règle C.22 pour cette catégorie plutôt que la calculer sur un ordre inventé.
 * Bloquer une composition légitime sur un rang faux coûte plus cher que de
 * laisser passer une infraction que personne ne détecte aujourd'hui.
 */
it('refuses to rank a name that is neither a letter nor a number', function (?string $name): void {
    expect(Team::rankOf($name))->toBeNull();
})->with([
    'deux lettres' => ['AB'],
    'vide' => [''],
    'espaces' => ['   '],
    'nul' => [null],
    'libellé de division' => ['BARRAGES P3 vers P2'],
    'zéro' => ['0'],
    'lettre et chiffre' => ['A1'],
]);

it('reads a name whatever its case and padding', function (): void {
    expect(Team::rankOf(' b '))->toBe(2);
});
