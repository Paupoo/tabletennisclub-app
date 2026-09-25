<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Database\Seeders\PartiallySettledClaimSeeder;

/**
 * Une créance déjà réglée pour partie, dans le club de démonstration.
 *
 * Sans elle, le cas le plus fréquent d'un vrai club — le membre qui paie en
 * septembre puis en novembre — n'existe sur une base fraîche qu'après avoir
 * importé deux relevés à la main.
 */
function claimAwaitingCompletion(): Subscription
{
    $season = makeActiveSeason();

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'season_id' => $season->id,
        'status' => 'confirmed',
        'amount_due' => 120,
        'amount_paid' => 0,
    ]);

    $subscription->payments()->create([
        'reference' => '077/0926/00001',
        'amount_due' => 120,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    return $subscription;
}

it('leaves one claim partly settled, with the transfer that settled it', function (): void {
    claimAwaitingCompletion();

    test()->seed(PartiallySettledClaimSeeder::class);

    $partial = Payment::where('status', 'pending')->where('amount_paid', '>', 0)->first();

    expect($partial)->not->toBeNull()
        ->and($partial->balance())->toBeGreaterThan(0.0)
        // L'historique doit être vrai : le versement vient d'une transaction.
        ->and($partial->credits)->toHaveCount(1)
        ->and($partial->credits->first()->transaction)->not->toBeNull();
})->group('seeders', 'payments');

/**
 * Relancé, il ne recommence pas : deux passages ne doivent pas créditer deux
 * fois la même créance.
 */
it('does nothing on a second run', function (): void {
    claimAwaitingCompletion();

    test()->seed(PartiallySettledClaimSeeder::class);
    test()->seed(PartiallySettledClaimSeeder::class);

    expect(Payment::where('status', 'pending')->where('amount_paid', '>', 0)->count())->toBe(1);
})->group('seeders', 'payments');
