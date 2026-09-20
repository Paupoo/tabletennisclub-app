<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/**
 * La licence d'un compétiteur est unique en base, et la fabrique cherchait un
 * numéro libre en interrogeant la table. Or `->count(N)->create()` exécute les
 * N closures d'état *avant* la moindre insertion : les membres d'une même
 * fournée ne se voient pas, et deux d'entre eux pouvaient tirer le même numéro.
 *
 * Cinq tests d'interclub créent 30 compétiteurs d'un coup, soit 435 paires par
 * lot — la suite tombait sur une violation de contrainte de temps à autre, dans
 * un fichier différent à chaque fois.
 */
it('never draws the same licence twice inside one batch', function (): void {
    // `make()` plutôt que `create()` : la collision naît dans la closure d'état,
    // avant toute écriture, et n'a pas besoin d'une base pour se produire.
    $licences = User::factory()->isCompetitor()->count(1000)->make()->pluck('licence');

    expect($licences)->toHaveCount(1000)
        ->and($licences->unique())->toHaveCount(1000);
});
