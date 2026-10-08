<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Support\PaymentCovers;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    $this->season = makeActiveSeason();
});

/**
 * An affiliation invoiced and paid before the stage was added to it, the way
 * production holds them today.
 */
function splitInvoicedAffiliation(Season $season): Subscription
{
    $subscription = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
    ]);

    (new CalculatePriceAction)($subscription);

    $payment = $subscription->payments()->create([
        'reference' => '+++000/0000/00097+++',
        'amount_due' => $subscription->refresh()->amount_due,
        'amount_paid' => 0,
        'status' => 'pending',
        'covers' => PaymentCovers::affiliation($subscription),
    ]);

    (new AllocateTransactionAction)->credit($payment, (float) $payment->amount_due, 'cash');

    return $subscription->refresh();
}

function splitLegacyStage(Season $season, array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'season_id' => $season->id,
        'price' => 80,
        'allow_discount' => false,
        'pack_start_date' => today()->addMonth()->startOfMonth()->toDateString(),
        'pack_end_date' => today()->addMonth()->startOfMonth()->addDays(4)->toDateString(),
    ], $overrides));
}

it('moves the stage complement onto the line, without touching a euro', function (): void {
    $subscription = splitInvoicedAffiliation($this->season);
    $feeOnly = (float) $subscription->amount_due;
    $stage = splitLegacyStage($this->season);

    $complement = (new AddMemberToTrainingPackAction)($subscription, $stage);
    (new AllocateTransactionAction)->credit($complement, 80.0, 'cash');

    $this->artisan('trainings:split-camp', ['pack' => $stage->id])->assertSuccessful();

    $line = SubscriptionTrainingPack::where('training_pack_id', $stage->id)->firstOrFail();

    expect($stage->refresh()->is_camp)->toBeTrue()
        ->and((bool) $line->invoiced_separately)->toBeTrue()
        ->and($complement->refresh()->payable_type)->toBe(SubscriptionTrainingPack::class)
        ->and($complement->payable_id)->toBe($line->id)
        ->and((float) $subscription->refresh()->amount_due)->toBe($feeOnly)
        ->and($subscription->netAmountPaid())->toBe($feeOnly)
        ->and($subscription->status)->toBe('paid');
});

it('settles the affiliation when only the stage was left to pay', function (): void {
    $subscription = splitInvoicedAffiliation($this->season);
    $stage = splitLegacyStage($this->season);

    (new AddMemberToTrainingPackAction)($subscription, $stage);

    expect($subscription->refresh()->isFullyPaid())->toBeFalse();

    $this->artisan('trainings:split-camp', ['pack' => $stage->id])->assertSuccessful();

    expect($subscription->refresh()->isFullyPaid())->toBeTrue()
        ->and(Payment::where('payable_type', SubscriptionTrainingPack::class)->where('status', 'pending')->count())->toBe(1);
});

it('leaves a stage folded into the first invoice to the treasurer', function (): void {
    $subscription = Subscription::factory()->for(User::factory())->create(['season_id' => $this->season->id]);
    $stage = splitLegacyStage($this->season);

    (new AddMemberToTrainingPackAction)($subscription, $stage);
    $before = (float) $subscription->refresh()->amount_due;

    $this->artisan('trainings:split-camp', ['pack' => $stage->id])
        ->expectsOutputToContain('one payment covers the fee and the stage')
        ->assertSuccessful();

    expect((bool) SubscriptionTrainingPack::where('training_pack_id', $stage->id)->value('invoiced_separately'))->toBeFalse()
        ->and((float) $subscription->refresh()->amount_due)->toBe($before);
});

it('leaves a stage that triggered the multi-pack discount to the treasurer', function (): void {
    $subscription = splitInvoicedAffiliation($this->season);
    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 100, 'allow_discount' => true]);
    $stage = splitLegacyStage($this->season, ['allow_discount' => true]);

    (new AddMemberToTrainingPackAction)($subscription, $pack);
    (new AddMemberToTrainingPackAction)($subscription, $stage);
    $before = (float) $subscription->refresh()->amount_due;

    $this->artisan('trainings:split-camp', ['pack' => $stage->id])
        ->expectsOutputToContain('multi-pack discount')
        ->assertSuccessful();

    expect((bool) SubscriptionTrainingPack::where('training_pack_id', $stage->id)->value('invoiced_separately'))->toBeFalse()
        ->and((float) $subscription->refresh()->amount_due)->toBe($before);
});

it('writes nothing on a dry run', function (): void {
    $subscription = splitInvoicedAffiliation($this->season);
    $stage = splitLegacyStage($this->season);
    $complement = (new AddMemberToTrainingPackAction)($subscription, $stage);

    $this->artisan('trainings:split-camp', ['pack' => $stage->id, '--dry-run' => true])
        ->expectsOutputToContain('its payment now belongs to the stage')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect($stage->refresh()->is_camp)->toBeFalse()
        ->and($complement->refresh()->payable_type)->toBe(Subscription::class);
});
