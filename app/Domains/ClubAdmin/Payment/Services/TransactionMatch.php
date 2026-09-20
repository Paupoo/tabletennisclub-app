<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Services;

use App\Domains\Shared\Enums\MatchStrength;

/**
 * Le verdict d'un rapprochement, et ce qui l'a produit.
 *
 * Les raisons ne sont pas décoratives : le barème étant heuristique, le
 * trésorier doit pouvoir voir *pourquoi* une ligne est proposée pour décider
 * de ne pas la croire.
 */
final readonly class TransactionMatch
{
    /** @param  list<string>  $reasons */
    public function __construct(
        public MatchStrength $strength,
        public array $reasons = [],
    ) {}
}
