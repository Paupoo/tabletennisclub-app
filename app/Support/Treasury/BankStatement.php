<?php

declare(strict_types=1);

namespace App\Support\Treasury;

/**
 * Un relevé lu, ramené aux champs que la trésorerie connaît.
 *
 * Les valeurs restent **brutes** — des chaînes telles que la banque les a
 * écrites, rognées, `null` quand elles sont vides. Les empreintes anti-doublon
 * se calculent sur elles : les convertir ici changerait l'empreinte de chaque
 * ligne déjà importée, et le fichier suivant entrerait en double.
 */
final readonly class BankStatement
{
    /**
     * @param  list<array{line: int, account: ?string, date: ?string, amount: ?string, description: ?string, counterparty_account: ?string, counterparty_name: ?string, structured_reference: ?string, free_reference: ?string}>  $rows
     * @param  list<string>  $columns  Les en-têtes tels que le fichier les écrit.
     */
    public function __construct(
        public array $rows,
        public array $columns,
    ) {}
}
