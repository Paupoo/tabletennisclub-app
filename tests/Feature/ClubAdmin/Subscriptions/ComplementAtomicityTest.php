<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| A complement is part of what calls for it
|--------------------------------------------------------------------------
|
| Approving a pack enrolled the member and raised the price, then created the
| complement. When that last step failed — in development, a column not yet
| migrated — the member kept the packs at the new price and nothing asked them
| for the difference: 211 € owed by nobody. Each of these flows is now one
| piece: the complement fails, nothing else changes.
|
*/

beforeEach(function (): void {
    Notification::fake();
    Club::factory()->ownClub()->create();
    $this->season = makeActiveSeason();
});

/** An affiliation already paid, as a member adding packs later has. */
function paidAffiliation(): Subscription
{
    $subscription = Subscription::factory()->for(test()->season, 'season')->for(User::factory(), 'user')
        ->create(['is_competitive' => false, 'status' => 'confirmed']);

    (new CalculatePriceAction)($subscription, 1);
    $subscription->refresh();
    $subscription->payments()->create([
        'reference' => (new GeneratePaymentReference)(),
        'amount_due' => $subscription->amount_due,
        'amount_paid' => $subscription->amount_due,
        'status' => 'paid',
    ]);
    $subscription->update(['status' => 'paid']);

    return $subscription->fresh();
}

function openPack(array $attributes = []): TrainingPack
{
    return TrainingPack::factory()->for(test()->season, 'season')
        ->create(array_merge(['max_participants' => 20, 'price' => 90, 'enrollments_open' => true], $attributes));
}

/** Make the next complement fail, the way a missing column did. */
function failTheComplement(): void
{
    Event::listen('eloquent.creating: ' . Payment::class, function (): never {
        throw new RuntimeException('the complement could not be created');
    });
}

describe('approving a pack request', function (): void {
    it('invoices each approval, twice in a row', function (): void {
        $subscription = paidAffiliation();
        $packs = collect(range(1, 5))->map(fn (): TrainingPack => openPack());
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $screen = Livewire::actingAs($admin)->test('pages::club-admin.users.registrations');

        $packs->take(3)->each(fn (TrainingPack $pack) => (new EnrollInTrainingPackAction)($subscription->fresh(), $pack));
        $screen->call('reviewTrainingRequest', $subscription->id)->call('approveTrainingRequest');

        $packs->slice(3)->each(fn (TrainingPack $pack) => (new EnrollInTrainingPackAction)($subscription->fresh(), $pack));
        $screen->call('reviewTrainingRequest', $subscription->id)->call('approveTrainingRequest');

        $claims = $subscription->payments()->where('status', 'pending')->orderBy('id')->get();

        expect($claims)->toHaveCount(2)
            ->and(collect($claims[0]->covers['training_packs'])->pluck('id')->sort()->values()->all())->toBe($packs->take(3)->pluck('id')->sort()->values()->all())
            ->and(collect($claims[1]->covers['training_packs'])->pluck('id')->sort()->values()->all())->toBe($packs->slice(3)->pluck('id')->sort()->values()->all());
    });

    it('changes nothing when the complement cannot be created', function (): void {
        $subscription = paidAffiliation();
        $before = (float) $subscription->amount_due;
        $pack = openPack();
        (new EnrollInTrainingPackAction)($subscription, $pack);
        failTheComplement();

        expect(fn () => Livewire::actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->test('pages::club-admin.users.registrations')
            ->call('reviewTrainingRequest', $subscription->id)
            ->call('approveTrainingRequest'))
            ->toThrow(RuntimeException::class);

        expect((float) $subscription->fresh()->amount_due)->toBe($before)
            ->and($subscription->trainingPacks()->first()->pivot->status)->toBe('pending');
    });
});

it('changes nothing when the club adds a pack and the complement fails', function (): void {
    $subscription = paidAffiliation();
    $before = (float) $subscription->amount_due;
    $pack = openPack();
    failTheComplement();

    expect(fn () => (new AddMemberToTrainingPackAction)($subscription, $pack))->toThrow(RuntimeException::class);

    expect((float) $subscription->fresh()->amount_due)->toBe($before)
        ->and($subscription->trainingPacks()->count())->toBe(0);
});

it('changes nothing when a member moves to a dearer pack and the complement fails', function (): void {
    $subscription = paidAffiliation();
    $from = openPack(['price' => 90]);
    $to = openPack(['price' => 200]);
    (new AddMemberToTrainingPackAction)($subscription, $from);
    $subscription = $subscription->fresh();
    $subscription->payments()->where('status', 'pending')->update(['status' => 'paid']);
    $before = (float) $subscription->amount_due;
    failTheComplement();

    expect(fn () => (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to))->toThrow(RuntimeException::class);

    expect((float) $subscription->fresh()->amount_due)->toBe($before)
        ->and($subscription->trainingPacks()->where('training_pack_id', $from->id)->first()->pivot->status)->toBe('enrolled')
        ->and($subscription->trainingPacks()->where('training_pack_id', $to->id)->exists())->toBeFalse();
});
