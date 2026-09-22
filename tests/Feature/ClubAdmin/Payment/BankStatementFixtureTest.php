<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Support\Treasury\BankStatementFixture;
use Database\Seeders\FamilySeeder;

/**
 * Un club de démonstration réduit, mais complet : des affiliations avec une
 * créance ouverte, des remboursements engagés, et des familles.
 */
function seededClubForStatement(): void
{
    $season = makeActiveSeason();

    test()->seed(FamilySeeder::class);

    // Des membres sans famille, pour les cas qui n'en demandent pas.
    for ($i = 0; $i < 20; $i++) {
        $member = User::factory()->create([
            'iban' => sprintf('BE68%012d', 539007547000 + $i),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
            'amount_paid' => 0,
        ]);

        $subscription->payments()->create([
            'reference' => sprintf('021/0926/%05d', 1000 + $i),
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // Trois remboursements engagés : un pour l'appariement simple, deux pour
        // le virement sortant groupé.
        if ($i < 3) {
            $subscription->payments()->create([
                'reference' => sprintf('021/0926/%05d', 9000 + $i),
                'amount_due' => 40,
                'amount_paid' => 0,
                'status' => 'to_refund',
                'payment_method' => 'refund',
            ]);
        }
    }
}

it('covers every case of the catalogue on a seeded club', function (): void {
    seededClubForStatement();

    $result = (new BankStatementFixture)->build();

    expect($result->skipped)->toBe([])
        ->and($result->covered)->toHaveCount(count(BankStatementFixture::CASES))
        ->and($result->rowCount)->toBeGreaterThan(20);
})->group('payments', 'fixture');

/**
 * Le manifeste nomme chaque cas : c'est lui qui transforme quarante lignes de
 * relevé en quelque chose qu'on peut éprouver à la main.
 */
it('writes a manifest that names every case it produced', function (): void {
    seededClubForStatement();

    $result = (new BankStatementFixture)->build();

    foreach ($result->covered as $case) {
        expect($result->manifest)->toContain(BankStatementFixture::CASES[$case]);
    }
})->group('payments', 'fixture');

/**
 * Les dates glissent avec le calendrier. Un relevé figé en mai tombe hors de
 * toute plage qu'on pense à regarder, et on cherche le bug ailleurs.
 */
it('dates the statement on the days leading up to today', function (): void {
    seededClubForStatement();

    $result = (new BankStatementFixture)->build();

    expect($result->csv)->toContain(now()->format('d/m/Y'))
        ->and($result->csv)->not->toContain('/05/2026');
})->group('payments', 'fixture');

/**
 * Le cas 6 vit à l'intérieur du fichier : deux lignes strictement identiques,
 * pour que le dédoublonnage par empreinte se teste sans dépendre d'un import
 * antérieur.
 */
it('carries a duplicate of one of its own rows', function (): void {
    seededClubForStatement();

    $lines = array_filter(explode("\r\n", (new BankStatementFixture)->build()->csv));
    $body = array_slice($lines, 1);

    // Les six colonnes sur lesquelles l'import calcule son empreinte : date,
    // montant, IBAN du tiers, communication structurée, communication libre,
    // libellé. Le solde n'en fait pas partie, donc deux lignes peuvent être
    // des doublons sans être identiques caractère pour caractère.
    $fingerprints = array_map(function (string $line): string {
        $c = explode(';', $line);

        return implode('|', [$c[5], $c[8], $c[12], $c[16], $c[17], $c[6]]);
    }, $body);

    expect(count($fingerprints))->toBeGreaterThan(count(array_unique($fingerprints)));
})->group('payments', 'fixture');

/**
 * Le membre qui paie de son propre compte sans mettre la communication.
 *
 * Seul son IBAN le désigne. C'est la branche « IBAN du membre » du barème —
 * aussi peu exercée que l'était la branche tuteur, parce que huit membres sur
 * deux cent soixante-neuf portaient un IBAN en base.
 */
it('produces a transfer recognisable by the member IBAN alone', function (): void {
    seededClubForStatement();

    $result = (new BankStatementFixture)->build();

    expect($result->covered)->toContain('member_iban_only');
})->group('payments', 'fixture');

/**
 * Sur une base pauvre, les cas distinctifs passent avant le nombre.
 *
 * Douze lignes « référence et montant exacts » sont la partie la moins
 * instructive du fichier : elles se ressemblent toutes. Les cas qui ne
 * consomment qu'une créance — partiel, référence répétée, arrondi, IBAN seul —
 * sont ceux pour lesquels le relevé existe. Servir les premiers d'abord a
 * coûté le quatorzième cas sur la vraie base, à une créance près.
 */
it('serves the distinctive cases before filling up on perfect matches', function (): void {
    $season = makeActiveSeason();

    test()->seed(FamilySeeder::class);

    // Six créances seulement, plus celles des familles : de quoi servir chaque
    // cas distinctif, pas de quoi remplir une douzaine de lignes parfaites.
    for ($i = 0; $i < 6; $i++) {
        $member = User::factory()->create(['iban' => sprintf('BE68%012d', 539007548000 + $i)]);

        $subscription = Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
            'amount_paid' => 0,
        ]);

        $subscription->payments()->create([
            'reference' => sprintf('033/0926/%05d', 2000 + $i),
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
    }

    $result = (new BankStatementFixture)->build();

    foreach (['partial', 'same_reference_twice', 'rounded_up', 'member_iban_only'] as $case) {
        expect($result->covered)->toContain($case);
    }
})->group('payments', 'fixture');
