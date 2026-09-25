<?php

declare(strict_types=1);

namespace App\Support\Treasury;

/**
 * Ce qu'une génération de relevé produit.
 *
 * Le CSV et le manifeste sortent du même passage sur le même catalogue : ils ne
 * peuvent donc pas diverger, ce qui serait arrivé tôt ou tard avec deux
 * fonctions séparées.
 *
 * `skipped` n'est pas un détail d'implémentation. Un cas qu'on n'a pas pu
 * mettre en scène doit être **dit**, sinon le fichier perd silencieusement une
 * situation qu'on croyait tester.
 */
final readonly class BankStatementResult
{
    /**
     * @param  list<string>  $covered  Les cas produits, par leur clé.
     * @param  array<string, string>  $skipped  clé du cas => pourquoi il manque.
     */
    public function __construct(
        public string $csv,
        public string $manifest,
        public array $covered,
        public array $skipped,
        public int $rowCount,
    ) {}
}
