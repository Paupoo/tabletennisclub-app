<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\AdjustTrainingCampLineAction;
use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionWithRefundAction;
use App\Actions\ClubAdmin\Subscriptions\CancelTrainingCampEnrolmentAction;
use App\Actions\ClubAdmin\Subscriptions\DecideTrainingCampRequestAction;
use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingCampEnrolledNotification;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();
});

/**
 * An affiliation paid in full, the way the attestation wants to find it.
 */
function campPaidAffiliation(Season $season, float $amount = 125.0): Subscription
{
    $subscription = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'amount_due' => $amount,
    ]);

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'amount_due' => $amount,
        'amount_paid' => $amount,
        'status' => 'paid',
    ]);

    return $subscription->refresh();
}

function campLineOf(Subscription $subscription, TrainingPack $camp): ?SubscriptionTrainingPack
{
    return (new TrainingCampBilling)->line($subscription, $camp);
}

/**
 * Money received on a stage line, as a bank reconciliation would credit it.
 */
function campPayLine(SubscriptionTrainingPack $line): void
{
    $claim = $line->payments()->where('status', 'pending')->latest('id')->first();
    (new AllocateTransactionAction)->credit($claim, (float) $claim->amount_due, 'cash');
}

describe('a stage is invoiced on its own line', function (): void {
    it('enrols the member directly and bills the line, never the affiliation', function (): void {
        $season = makeActiveSeason();
        $subscription = campPaidAffiliation($season);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80, 'max_participants' => 10]);

        $status = (new EnrollInTrainingCampAction)($subscription, $camp);

        $line = campLineOf($subscription, $camp);

        expect($status)->toBe('enrolled')
            ->and((bool) $line->invoiced_separately)->toBeTrue()
            ->and($line->payments()->count())->toBe(1)
            ->and((float) $line->payments()->first()->amount_due)->toBe(80.0)
            ->and($subscription->refresh()->amount_due)->toBe(125.0)
            ->and($subscription->payments()->count())->toBe(1);

        Mail::assertQueued(PaymentInvitationEmail::class);
        Notification::assertSentTo($subscription->user, TrainingCampEnrolledNotification::class);
    });

    it('does not block the attestation while unpaid, and stays out of the certified amount once paid', function (): void {
        $season = makeActiveSeason();
        $subscription = campPaidAffiliation($season);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp);

        expect(app(AttestationEligibility::class)->for($subscription->user, $season)->allowed)->toBeTrue();

        campPayLine(campLineOf($subscription, $camp));

        expect($subscription->refresh()->netAmountPaid())->toBe(125.0)
            ->and($subscription->isFullyPaid())->toBeTrue();
    });

    it('neither receives nor triggers the multi-pack discount', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id, 'is_competitive' => false]);
        $pack = TrainingPack::factory()->create(['season_id' => $season->id, 'price' => 100, 'allow_discount' => true]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80, 'allow_discount' => true]);

        (new AddMemberToTrainingPackAction)($subscription, $pack);
        $before = $subscription->refresh()->amount_due;

        (new AddMemberToTrainingPackAction)($subscription, $camp);
        (new CalculatePriceAction)($subscription->refresh());

        expect($subscription->refresh()->amount_due)->toBe($before)
            ->and((float) campLineOf($subscription, $camp)->payments()->first()->amount_due)->toBe(80.0);
    });

    it('refuses an affiliation of another season', function (): void {
        $season = makeActiveSeason();
        $other = Season::factory()->create();
        $subscription = Subscription::factory()->create(['season_id' => $other->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id]);

        expect(fn () => (new EnrollInTrainingCampAction)($subscription, $camp))->toThrow(DomainException::class);
    });

    it('accepts an affiliation still awaiting validation', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->pending()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id]);

        expect((new EnrollInTrainingCampAction)($subscription, $camp))->toBe('enrolled');
    });

    it('closes the door on members but not on the club', function (): void {
        $season = makeActiveSeason();
        $camp = TrainingPack::factory()->camp()->enrolmentsClosed()->create(['season_id' => $season->id]);
        $member = Subscription::factory()->create(['season_id' => $season->id]);
        $other = Subscription::factory()->create(['season_id' => $season->id]);

        expect(fn () => (new EnrollInTrainingPackAction)($member, $camp))->toThrow(DomainException::class)
            ->and((new EnrollInTrainingCampAction)($other, $camp, byClub: true))->toBe('enrolled');
    });

    it('lets the club set the member price at enrolment, with a reason', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        expect(fn () => (new EnrollInTrainingCampAction)($subscription, $camp, byClub: true, overrideAmount: 20))
            ->toThrow(DomainException::class);

        (new EnrollInTrainingCampAction)($subscription, $camp, byClub: true, overrideAmount: 20, overrideReason: 'Un seul jour');

        expect((float) campLineOf($subscription, $camp)->payments()->first()->amount_due)->toBe(20.0);
    });

    it('puts the member on the waiting list once the stage is full', function (): void {
        $season = makeActiveSeason();
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'max_participants' => 1]);
        $first = Subscription::factory()->create(['season_id' => $season->id]);
        $second = Subscription::factory()->create(['season_id' => $season->id]);

        (new EnrollInTrainingCampAction)($first, $camp);

        expect((new EnrollInTrainingCampAction)($second, $camp))->toBe('waiting')
            ->and(campLineOf($second, $camp)->payments()->count())->toBe(0);
    });

    it('cannot be moved to or from a season pack', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id]);
        $pack = TrainingPack::factory()->create(['season_id' => $season->id]);

        (new EnrollInTrainingCampAction)($subscription, $camp, byClub: true);

        expect(fn () => (new MoveMemberBetweenTrainingPacksAction)($subscription, $camp, $pack))
            ->toThrow(DomainException::class);
    });
});

describe('a stage that sorts its requests', function (): void {
    it('records a request and invoices nothing until the committee accepts', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'requires_approval' => true, 'price' => 60]);

        expect((new EnrollInTrainingCampAction)($subscription, $camp))->toBe('pending');

        $line = campLineOf($subscription, $camp);
        expect($line->payments()->count())->toBe(0);

        (new DecideTrainingCampRequestAction)->approve($line);

        expect($line->refresh()->status)->toBe('enrolled')
            ->and((float) $line->payments()->first()->amount_due)->toBe(60.0);
    });

    it('drops a refused request and tells the member', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'requires_approval' => true]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        (new DecideTrainingCampRequestAction)->reject(campLineOf($subscription, $camp), 'Réservé aux jeunes');

        expect(campLineOf($subscription, $camp))->toBeNull();
        Notification::assertSentTo($subscription->user, TrainingPackRejectedNotification::class);
    });

    it('is neither accepted nor dropped when the affiliation is approved', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->pending()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'requires_approval' => true]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        (new ApproveTrainingPacksAction)($subscription, []);

        expect(campLineOf($subscription, $camp)->status)->toBe('pending');
    });

    it('does not put a validated affiliation back in the decision queue', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id, 'status' => 'confirmed']);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'requires_approval' => true]);

        (new EnrollInTrainingCampAction)($subscription, $camp);

        expect(Subscription::query()->awaitingDecision()->whereKey($subscription->id)->exists())->toBeFalse();
    });
});

describe('changing a stage line', function (): void {
    it('lowers the unpaid request when the price is forced down', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        $refunded = (new AdjustTrainingCampLineAction)($subscription, $camp, 40, 'Présent la moitié');

        $line = campLineOf($subscription, $camp);

        expect($refunded)->toBe(0.0)
            ->and($line->payments()->count())->toBe(1)
            ->and((float) $line->payments()->first()->amount_due)->toBe(40.0);
    });

    it('refunds what was already paid beyond the forced price', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        campPayLine(campLineOf($subscription, $camp));

        $refunded = (new AdjustTrainingCampLineAction)($subscription, $camp, 30, 'Un jour');

        $refund = campLineOf($subscription, $camp)->payments()->where('payment_method', 'refund')->first();

        expect($refunded)->toBe(50.0)
            ->and($refund->status)->toBe('to_refund')
            ->and((float) $refund->amount_due)->toBe(50.0)
            ->and($subscription->refresh()->payments()->count())->toBe(0);
    });

    it('issues a complement when the price goes up after payment', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp, byClub: true, overrideAmount: 20, overrideReason: 'Un jour');
        campPayLine(campLineOf($subscription, $camp));

        (new AdjustTrainingCampLineAction)($subscription, $camp, null);

        $pending = campLineOf($subscription, $camp)->payments()->where('status', 'pending')->first();

        expect((float) $pending->amount_due)->toBe(60.0);
    });

    it('keeps a departure owed in full', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        (new LeaveTrainingPackAction)($subscription, $camp, notifyUser: false);

        $line = campLineOf($subscription, $camp);

        expect($line->status)->toBe('left')
            ->and((float) $line->payments()->where('status', 'pending')->sum('amount_due'))->toBe(8000.0);
    });

    it('cancels the request of an encoding error and refunds what came in', function (bool $paid): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);

        (new EnrollInTrainingCampAction)($subscription, $camp);

        if ($paid) {
            campPayLine(campLineOf($subscription, $camp));
        }

        $refunded = (new CancelTrainingCampEnrolmentAction)($subscription, $camp);
        $line = campLineOf($subscription, $camp);

        expect($line->status)->toBe('cancelled')
            ->and($refunded)->toBe($paid ? 80.0 : 0.0)
            ->and($line->payments()->where('status', 'pending')->count())->toBe(0);
    })->with(['unpaid' => false, 'paid' => true]);
});

describe('what ends a stage', function (): void {
    it('cancels the stage lines with the affiliation, refunding what was paid', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $paidCamp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);
        $unpaidCamp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 50]);

        (new EnrollInTrainingCampAction)($subscription, $paidCamp);
        (new EnrollInTrainingCampAction)($subscription, $unpaidCamp);
        campPayLine(campLineOf($subscription, $paidCamp));

        (new CancelSubscriptionWithRefundAction)($subscription);

        expect(campLineOf($subscription, $paidCamp)->status)->toBe('cancelled')
            ->and(campLineOf($subscription, $paidCamp)->payments()->where('status', 'to_refund')->sum('amount_due'))->toBe(8000)
            ->and(campLineOf($subscription, $unpaidCamp)->payments()->where('status', 'pending')->count())->toBe(0);
    });

    it('takes the stage payments along when the affiliation is deleted', function (): void {
        $season = makeActiveSeason();
        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id]);

        (new EnrollInTrainingCampAction)($subscription, $camp);
        $subscription->delete();

        expect(Payment::where('payable_type', SubscriptionTrainingPack::class)->count())->toBe(0);
    });

    it('refunds every member in full when the club calls the stage off', function (): void {
        $season = makeActiveSeason();
        $camp = TrainingPack::factory()->camp()->create(['season_id' => $season->id, 'price' => 80]);
        $paid = Subscription::factory()->create(['season_id' => $season->id]);
        $unpaid = Subscription::factory()->create(['season_id' => $season->id]);

        (new EnrollInTrainingCampAction)($paid, $camp);
        (new EnrollInTrainingCampAction)($unpaid, $camp);
        campPayLine(campLineOf($paid, $camp));

        $result = (new DiscontinueTrainingPackAction)($camp, 'Trop peu d’inscrits');

        expect($result['refunded'])->toBe(80.0)
            ->and($result['members'])->toBe(2)
            ->and(campLineOf($paid, $camp)->status)->toBe('cancelled')
            ->and(campLineOf($unpaid, $camp)->payments()->where('status', 'pending')->count())->toBe(0);
    });
});
