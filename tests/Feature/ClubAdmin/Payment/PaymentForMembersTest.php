<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Whose payments a query reads
|--------------------------------------------------------------------------
|
| A stage line holds no `user_id`: it names its member through the
| affiliation. A query that forgot it fails on MySQL and silently reads no
| row on SQLite, where an unknown quoted column is a string literal — so
| « My payments » answered 500 to every member behind a green suite. The rule
| lives in one scope, read here with every payable type in the table.
|
*/

const FOR_MEMBERS_TYPES = [
    Subscription::class,
    TournamentRegistration::class,
    MeetingUser::class,
    SubscriptionTrainingPack::class,
    ExpenseReport::class,
];

function forMembersPaymentOn(Model $payable): Payment
{
    return Payment::factory()->create([
        'payable_type' => $payable::class,
        'payable_id' => $payable->id,
    ]);
}

/**
 * One payment of every payable type, all for the same member.
 *
 * @return array<class-string, Payment>
 */
function forMembersPaymentsOf(User $user, Season $season): array
{
    $subscription = Subscription::factory()->for($user)->create(['season_id' => $season->id]);
    $line = SubscriptionTrainingPack::create([
        'subscription_id' => $subscription->id,
        'training_pack_id' => TrainingPack::factory()->camp()->create(['season_id' => $season->id])->id,
        'status' => 'enrolled',
        'invoiced_separately' => true,
    ]);

    return [
        Subscription::class => forMembersPaymentOn($subscription),
        TournamentRegistration::class => forMembersPaymentOn(TournamentRegistration::create([
            'user_id' => $user->id,
            'tournament_id' => Tournament::factory()->create()->id,
        ])),
        MeetingUser::class => forMembersPaymentOn(MeetingUser::create([
            'user_id' => $user->id,
            'meeting_id' => Meeting::factory()->create()->id,
        ])),
        SubscriptionTrainingPack::class => forMembersPaymentOn($line),
        ExpenseReport::class => forMembersPaymentOn(ExpenseReport::factory()->create(['user_id' => $user->id])),
    ];
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->member = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->ownPayments = forMembersPaymentsOf($this->member, $this->season);
    forMembersPaymentsOf($this->stranger, $this->season);
});

it('reads every payable type of the member, a stage line included, and nobody else', function (mixed $memberIds): void {
    $ids = Payment::query()->forMembers($memberIds, FOR_MEMBERS_TYPES)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($ids)->toBe(collect($this->ownPayments)->pluck('id')->sort()->values()->all());
})->with([
    // Bound datasets: read once beforeEach has made the member.
    'a single id' => fn (): int => $this->member->id,
    'a list' => fn (): array => [$this->member->id],
    'a collection' => fn () => collect([$this->member->id]),
]);

it('keeps to the payable types the caller asks for', function (): void {
    $ids = Payment::query()->forMembers($this->member->id, [SubscriptionTrainingPack::class])->pluck('id')->all();

    expect($ids)->toBe([$this->ownPayments[SubscriptionTrainingPack::class]->id]);
});

it('names the member of every payable type, a stage line through its affiliation', function (): void {
    foreach ($this->ownPayments as $type => $payment) {
        expect(Payment::find($payment->id)->memberId())->toBe($this->member->id, $type);
    }
});
