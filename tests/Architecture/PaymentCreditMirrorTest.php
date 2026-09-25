<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * `payments.amount_paid` est le miroir des lignes de crédit.
 *
 * Une colonne-miroir ne vaut que si un seul endroit l'écrit. Dès qu'un second
 * écrivain apparaît, les deux sources divergent en silence — et c'est arrivé :
 * une base fraîchement semée portait cinquante-sept paiements crédités sans
 * aucune ligne de crédit, donc des transactions qui se disaient non
 * rapprochées alors que leur argent était compté ailleurs.
 *
 * Ce test est une frontière, pas une interdiction : si vous devez écrire ce
 * montant, venez vous inscrire ici et dites pourquoi.
 */
it('writes amount_paid in one place only', function (): void {
    $root = dirname(__DIR__, 2);

    /**
     * Ce qui a le droit d'écrire, et à quel titre.
     *
     * @var array<string, string>
     */
    $allowed = [
        'app/Actions/ClubAdmin/Payments/AllocateTransactionAction.php' => 'le seul écrivain légitime : il recalcule le miroir depuis les crédits',
        'database/migrations/2026_09_21_102700_backfill_payment_credits.php' => 'la reprise, qui reconstruit les deux colonnes ensemble',
        'database/seeders/TreasurySeeder.php' => 'données de démonstration, réparées par la reprise en fin de semis',
        'database/seeders/TrainingPackSeeder.php' => 'idem',
        'app/Domains/ClubAdmin/Payment/Models/Payment.php' => 'déclare la colonne, son cast et son mutateur — il ne l\'écrit pas',
    ];

    $offenders = [];

    foreach ([$root . '/app', $root . '/database', $root . '/resources/views'] as $directory) {
        foreach ((new Finder)->files()->in($directory)->name('*.php') as $file) {
            $relative = str_replace($root . '/', '', $file->getPathname());

            if (array_key_exists($relative, $allowed)) {
                continue;
            }

            $lines = explode("\n", (string) file_get_contents($file->getPathname()));

            foreach ($lines as $number => $line) {
                // La valeur est capturée, pas devinée : un `\s*` devant une
                // négation peut revenir en arrière, et la négation devient
                // alors toujours vraie — le test accusait tout le monde.
                if (preg_match("/'amount_paid'\s*=>\s*([^,\]]+)/", $line, $m) !== 1) {
                    continue;
                }

                // Créer une ligne à zéro ne dit rien qu'un crédit puisse
                // contredire : ce n'est pas une écriture du miroir.
                if (trim($m[1]) === '0') {
                    continue;
                }

                // Une écriture, pas une projection. Les écrans recopient ce
                // montant dans des objets d'affichage, et un tableau litéral
                // ressemble à un `create()` quand on ne regarde qu'une ligne.
                $context = implode("\n", array_slice($lines, max(0, $number - 6), 7));

                if (preg_match('/->(create|update|forceFill|fill|insert)\s*\(/', $context) !== 1) {
                    continue;
                }

                $offenders[] = $relative . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        'amount_paid est le miroir des lignes de crédit : passez par AllocateTransactionAction, '
        . "ou inscrivez-vous dans la liste de ce test en expliquant pourquoi.\n%s",
        implode("\n", $offenders),
    ));
})->group('architecture', 'payments');
