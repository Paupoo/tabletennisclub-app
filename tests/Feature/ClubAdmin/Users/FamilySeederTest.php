<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use Database\Seeders\FamilySeeder;

/**
 * Le club de démonstration n'avait aucun tuteur.
 *
 * `guardians = 0`, `guardian_user = 0` : les branches du barème qui
 * reconnaissent le parent payeur — l'IBAN de chaque tuteur, son nom sur le
 * tiers — n'ont jamais tourné sur une base de développement. Et le virement
 * d'un parent pour ses deux enfants, le cas le plus banal d'un club, était
 * impossible à mettre en scène.
 */
it('gives the club a family whose guardian pays for two affiliated children', function (): void {
    $season = makeActiveSeason();

    test()->seed(FamilySeeder::class);

    // `has('users', '>=', 2)` plutôt qu'un HAVING sur un sous-select : SQLite
    // refuse un HAVING sans agrégat dans la requête elle-même.
    $payingForTwo = Guardian::has('users', '>=', 2)->with('users')->get();

    expect($payingForTwo)->not->toBeEmpty();

    $guardian = $payingForTwo->first();

    // Sans IBAN, la moitié du barème reste muette sur ce tuteur.
    expect($guardian->iban)->not->toBeNull();

    foreach ($guardian->users as $ward) {
        $subscription = Subscription::where('user_id', $ward->id)->forSeason($season)->affiliated()->first();

        expect($subscription)->not->toBeNull("le pupille {$ward->id} n'a pas d'affiliation ouverte");

        // Une créance ouverte, sinon le virement du parent n'a rien à solder.
        expect($subscription->payments()->where('status', 'pending')->exists())
            ->toBeTrue("le pupille {$ward->id} n'a aucune créance ouverte");
    }
})->group('seeders', 'family');

/**
 * Le club a aussi des parents qui jouent — un ou deux, pas davantage.
 */
it('includes a guardian who is an affiliated member in their own right', function (): void {
    makeActiveSeason();

    test()->seed(FamilySeeder::class);

    $playing = Guardian::whereNotNull('user_id')->get();

    expect($playing)->toHaveCount(1)
        ->and($playing->first()->member)->not->toBeNull();
})->group('seeders', 'family');

/**
 * La plupart ne jouent pas : ils n'existent que comme contact et comme payeur.
 */
it('leaves most guardians outside the roster', function (): void {
    makeActiveSeason();

    test()->seed(FamilySeeder::class);

    expect(Guardian::whereNull('user_id')->count())->toBeGreaterThan(Guardian::whereNotNull('user_id')->count());
})->group('seeders', 'family');
