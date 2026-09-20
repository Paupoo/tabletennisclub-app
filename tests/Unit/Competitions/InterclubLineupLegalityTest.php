<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Services\InterclubLineupLegalityService;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LineupLegality;
use App\Domains\Shared\Enums\LineupLegalityReason;

/**
 * L'arithmétique de l'article C.22, et rien d'autre : aucune base de données,
 * aucun modèle — des indices de référence et des bornes.
 *
 * Règle (Règlements Sportifs Nationaux FRBTT, éd. 01/07/2024) :
 *
 *   C.22.1.3 — « Le premier joueur d'une équipe ne peut avoir un indice de
 *   référence plus petit que celui du troisième joueur ayant effectivement
 *   participé à la rencontre de l'équipe supérieure. »
 *
 *   C.22.2.3 — chez les dames et les catégories d'âge, c'est le *deuxième*
 *   joueur, et de *n'importe laquelle* des équipes supérieures.
 *
 * Un indice plus petit désigne un joueur plus fort : #1 est le meilleur du club.
 * Tout le fichier se lit avec cette inversion en tête.
 */
beforeEach(function (): void {
    $this->rule = new InterclubLineupLegalityService;
});

it('reads the threshold off the third player when the superior team has composed', function (): void {
    $bounds = $this->rule->thresholdBounds(
        [['lineup' => [1, 2, 5, 8], 'core' => [1, 2, 5, 8, 11, 14, 17]]],
        LeagueCategory::MEN,
    );

    // La compo est connue : il n'y a plus d'incertitude, les deux bornes se
    // rejoignent sur le troisième aligné.
    expect($bounds)->toBe(['strongest' => 5, 'weakest' => 5]);
});

/**
 * L'équipe supérieure n'a pas encore composé : on ne connaît pas son seuil, mais
 * son noyau le borne. Si elle aligne ses meilleurs, le seuil est le troisième du
 * noyau ; si elle aligne ses plus faibles, c'est le troisième des quatre plus
 * faibles — donc l'avant-dernier du noyau.
 */
it('brackets the threshold between the two lineups the core allows, for men', function (): void {
    $bounds = $this->rule->thresholdBounds(
        [['lineup' => null, 'core' => [1, 2, 5, 8, 11, 14, 17]]],
        LeagueCategory::MEN,
    );

    // Meilleurs alignés : 1, 2, 5, 8 → troisième = 5.
    // Plus faibles alignés : 8, 11, 14, 17 → troisième = 14.
    expect($bounds)->toBe(['strongest' => 5, 'weakest' => 14]);
});

it('brackets on the second player and a squad of three, for women', function (): void {
    $bounds = $this->rule->thresholdBounds(
        [['lineup' => null, 'core' => [1, 2, 5, 8, 11]]],
        LeagueCategory::WOMEN,
    );

    // Meilleures alignées : 1, 2, 5 → deuxième = 2.
    // Plus faibles alignées : 5, 8, 11 → deuxième = 8.
    expect($bounds)->toBe(['strongest' => 2, 'weakest' => 8]);
});

it('has no threshold to offer when there is no superior team', function (): void {
    expect($this->rule->thresholdBounds([], LeagueCategory::MEN))
        ->toBe(['strongest' => null, 'weakest' => null]);
});

/**
 * Un noyau trop court pour porter la place du seuil ne contraint personne : il
 * n'y a pas de troisième joueur à comparer.
 */
it('has no threshold when the core is shorter than the threshold position', function (): void {
    expect($this->rule->thresholdBounds([['lineup' => null, 'core' => [1, 2]]], LeagueCategory::MEN))
        ->toBe(['strongest' => null, 'weakest' => null]);
});

/**
 * Un noyau qui remplit tout juste l'équipe ne laisse aucun choix : les deux
 * bornes se rejoignent sans que personne ait composé.
 */
it('collapses both bounds when the core cannot field two different lineups', function (): void {
    expect($this->rule->thresholdBounds([['lineup' => null, 'core' => [1, 2, 5, 8]]], LeagueCategory::MEN))
        ->toBe(['strongest' => 5, 'weakest' => 5]);
});

/**
 * Chez les dames et les catégories d'âge, la contrainte vient de *n'importe
 * laquelle* des équipes supérieures (C.22.2.3) : on retient la plus exigeante,
 * c'est-à-dire le seuil le plus grand.
 */
it('keeps the most constraining of several superior teams', function (): void {
    $bounds = $this->rule->thresholdBounds([
        ['lineup' => [1, 2, 4], 'core' => [1, 2, 4, 9]],
        ['lineup' => [3, 7, 12], 'core' => [3, 7, 12, 15]],
    ], LeagueCategory::WOMEN);

    // Deuxièmes alignées : 2 et 7. Le seuil qui laisse le moins de joueuses est 7.
    expect($bounds)->toBe(['strongest' => 7, 'weakest' => 7]);
});

/**
 * Une composition partielle est un brouillon, pas une composition : deux joueurs
 * alignés sur quatre ne portent aucun troisième joueur. On ne conclut pas à
 * l'absence de contrainte — la plus dangereuse des trois réponses — on retombe
 * sur ce que le noyau permet.
 */
it('falls back to the core when the superior lineup is only a draft', function (): void {
    expect($this->rule->thresholdBounds([['lineup' => [1, 2], 'core' => [1, 2, 5, 8, 11]]], LeagueCategory::MEN))
        ->toBe(['strongest' => 5, 'weakest' => 8]);
});

/**
 * Le verdict ne porte jamais sur le candidat seul : la règle parle du *premier*
 * joueur de l'équipe, donc du plus fort de la composition une fois le candidat
 * ajouté. Un renfort faible ajouté à côté d'un joueur fort ne change rien.
 */
it('clears a candidate weaker than the threshold in every scenario', function (): void {
    $verdict = $this->rule->verdictFor(
        candidateIndex: 20,
        lineupIndices: [15, 18],
        bounds: ['strongest' => 5, 'weakest' => 14],
    );

    expect($verdict->state)->toBe(LineupLegality::ALLOWED)
        ->and($verdict->reason)->toBe(LineupLegalityReason::WEAKER_THAN_THRESHOLD);
});

it('refuses a candidate stronger than the threshold in every scenario', function (): void {
    $verdict = $this->rule->verdictFor(3, [15, 18], ['strongest' => 5, 'weakest' => 14]);

    expect($verdict->state)->toBe(LineupLegality::FORBIDDEN)
        ->and($verdict->reason)->toBe(LineupLegalityReason::STRONGER_THAN_THRESHOLD)
        ->and($verdict->isForbidden())->toBeTrue();
});

/**
 * Entre les deux bornes, la légalité dépend d'une composition que personne n'a
 * encore faite. Le motif doit envoyer le capitaine téléphoner, pas recalculer
 * une liste des forces.
 */
it('suspends judgement while the superior team has not composed', function (): void {
    $verdict = $this->rule->verdictFor(9, [15, 18], ['strongest' => 5, 'weakest' => 14]);

    expect($verdict->state)->toBe(LineupLegality::UNCERTAIN)
        ->and($verdict->reason)->toBe(LineupLegalityReason::SUPERIOR_LINEUP_UNKNOWN);
});

/**
 * Le verdict se lit sur le plus fort de la composition, jamais sur le candidat
 * seul : un renfort très faible n'absout pas un titulaire trop fort.
 */
it('judges the strongest of the lineup, not the candidate alone', function (): void {
    $verdict = $this->rule->verdictFor(30, [2, 25], ['strongest' => 5, 'weakest' => 5]);

    expect($verdict->state)->toBe(LineupLegality::FORBIDDEN);
});

/**
 * Décision 24 : un indice manquant est une anomalie de données, pas une
 * information sur la force du joueur. On ne le cache pas, on ne le valide pas —
 * et son motif se distingue de celui de la composition en attente.
 */
it('says it does not know when a reference index is missing', function (?int $candidate, array $lineup): void {
    $verdict = $this->rule->verdictFor($candidate, $lineup, ['strongest' => 5, 'weakest' => 14]);

    expect($verdict->state)->toBe(LineupLegality::UNCERTAIN)
        ->and($verdict->reason)->toBe(LineupLegalityReason::MISSING_REFERENCE_INDEX);
})->with([
    'le candidat n’a pas d’indice' => [null, [15, 18]],
    'un titulaire n’a pas d’indice' => [20, [15, null]],
]);

/**
 * Un `null` ne doit jamais glisser vers zéro : ce serait lire le joueur comme le
 * meilleur du club, et refuser une composition parfaitement légale.
 */
it('never reads a missing index as the strongest player in the club', function (): void {
    $verdict = $this->rule->verdictFor(null, [], ['strongest' => 5, 'weakest' => 14]);

    expect($verdict->isForbidden())->toBeFalse();
});

it('does not apply at all when no superior team constrains the lineup', function (): void {
    $verdict = $this->rule->verdictFor(1, [2], ['strongest' => null, 'weakest' => null]);

    expect($verdict->state)->toBe(LineupLegality::NOT_APPLICABLE)
        ->and($verdict->reason)->toBe(LineupLegalityReason::NO_SUPERIOR_TEAM)
        ->and($verdict->isWorthShowing())->toBeFalse();
});
