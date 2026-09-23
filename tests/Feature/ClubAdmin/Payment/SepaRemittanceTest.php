<?php

declare(strict_types=1);

use App\Support\Treasury\SepaRemittance;

/**
 * Ce que le payeur lira sur son extrait.
 *
 * Deux contraintes, et la seconde surprend : la communication libre d'un
 * virement SEPA tient en **140 caractères**, et n'admet qu'un jeu latin
 * restreint — ni accent, ni tiret cadratin. « trop-perçu » arriverait
 * mutilé, ou serait refusé.
 */
it('strips what a bank cannot carry', function (): void {
    $label = SepaRemittance::forOverpayment(
        club: 'CTT Ottignies-Blocry',
        event: 'Cotisation 2026-2027',
        member: 'Aurélien Paulus',
    );

    expect($label)->toBe('CTT Ottignies-Blocry - trop-percu Cotisation 2026-2027 - Aurelien Paulus')
        ->and($label)->toMatch("#^[A-Za-z0-9/\\-?:().,'+ ]+$#");
})->group('payments', 'sepa');

/**
 * Quand ça déborde, on sacrifie l'événement : le payeur doit savoir qui le
 * rembourse et pour qui, le reste est du contexte.
 */
it('keeps the club and the member whole when it has to cut', function (): void {
    $label = SepaRemittance::forOverpayment(
        club: 'CTT Ottignies-Blocry',
        event: str_repeat('Championnat interclubs vétérans ', 8),
        member: 'Marie-Charlotte Vanderheyden-Dupont',
    );

    expect(mb_strlen($label))->toBeLessThanOrEqual(140)
        ->and($label)->toStartWith('CTT Ottignies-Blocry')
        ->and($label)->toEndWith('Marie-Charlotte Vanderheyden-Dupont');
})->group('payments', 'sepa');

/**
 * Un libellé qui tient ne doit pas être coupé pour autant.
 */
it('leaves a short label alone', function (): void {
    $label = SepaRemittance::forOverpayment(
        club: 'CTT Ottignies-Blocry',
        event: 'Tournoi de Noel',
        member: 'Jade Delfosse',
    );

    expect($label)->toBe('CTT Ottignies-Blocry - trop-percu Tournoi de Noel - Jade Delfosse');
})->group('payments', 'sepa');
