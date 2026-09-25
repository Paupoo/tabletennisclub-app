<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\Notification;

/*
| Une créance baissée sous ce qui est déjà rentré est soldée.
|
| Retirer ou changer d'entraînement fait baisser ce que le membre doit, et
| ReduceOutstandingInvoiceAction ne descend jamais une ligne sous l'argent
| reçu. Une ligne à qui il ne manquait qu'un euro tombe donc pile sur son
| solde — sans qu'aucun encaissement ne vienne la déclarer payée.
*/

beforeEach(fn () => Notification::fake());

/**
 * Une affiliation récréative inscrite à `$pack`, dont chaque ligne est payée
 * sauf le dernier euro de la plus récente.
 */
function affiliationOneEuroShort(TrainingPack $pack): Subscription
{
    $subscription = Subscription::factory()
        ->for($pack->season, 'season')
        ->for(User::factory(), 'user')
        ->create(['is_competitive' => false, 'status' => 'confirmed']);

    (new CalculatePriceAction)($subscription, 1);

    $subscription->payments()->create([
        'reference' => (new GeneratePaymentReference)(),
        'amount_due' => $subscription->fresh()->amount_due,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    (new AddMemberToTrainingPackAction)($subscription->fresh(), $pack);

    $lines = $subscription->payments()->where('status', 'pending')->orderBy('id')->get();

    $lines->each(function (Payment $line) use ($lines): void {
        $short = $line->is($lines->last()) ? 1.0 : 0.0;
        // Relue seule : chargée en lot, elle ne peut pas atteindre son payable.
        (new AllocateTransactionAction)->credit($line->fresh(), $line->amount_due - $short, 'cash');
    });

    return $subscription->fresh();
}

/** @return array{0: TrainingPack, 1: TrainingPack} Un pack commencé, et un jumeau bien moins cher. */
function packAndCheaperTwin(): array
{
    $pack = TrainingPack::factory()->started()->create(['max_participants' => 5, 'price' => 90]);
    $cheaper = TrainingPack::factory()->started()->for($pack->season, 'season')->create([
        'max_participants' => 5,
        'price' => 10,
    ]);

    return [$pack, $cheaper];
}

it('settles the line and the affiliation when leaving a pack wipes out what was left to pay', function (): void {
    [$pack] = packAndCheaperTwin();
    $subscription = affiliationOneEuroShort($pack);

    (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false);

    expect($subscription->payments()->where('status', 'pending')->exists())->toBeFalse()
        ->and($subscription->fresh()->getStatus())->toBe('paid');
})->group('payments', 'training');

it('settles the line and the affiliation when moving to a cheaper pack wipes out what was left to pay', function (): void {
    [$pack, $cheaper] = packAndCheaperTwin();
    $subscription = affiliationOneEuroShort($pack);

    (new MoveMemberBetweenTrainingPacksAction)($subscription, $pack, $cheaper);

    expect($subscription->payments()->where('status', 'pending')->exists())->toBeFalse()
        ->and($subscription->fresh()->getStatus())->toBe('paid');
})->group('payments', 'training');
