<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\MembershipStatus;

pest()->group('club-admin', 'users');

/**
 * Three seasons side by side, dated by hand: `makeActiveSeason()` takes the
 * current calendar year, so the two before it must not be left to the factory,
 * whose random year could overlap it.
 */
beforeEach(function (): void {
    $this->current = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'name' => 'previous-season',
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);
    $this->older = Season::factory()->create([
        'start_at' => now()->subYears(2)->startOfYear(),
        'end_at' => now()->subYears(2)->endOfYear(),
    ]);

    $this->affiliate = function (User $user, Season $season, string $status = 'confirmed', bool $competitive = false): void {
        Subscription::factory()->for($user)->for($season)->create([
            'status' => $status,
            'is_competitive' => $competitive,
        ]);
    };

    /*
     * One member per status, plus the edge each rule has to get right: a
     * cancelled or refunded affiliation is no affiliation at all.
     */
    $this->members = [];

    $new = User::factory()->create();
    ($this->affiliate)($new, $this->current, 'pending');
    ($this->affiliate)($new, $this->previous, 'cancelled');
    $this->members['new'] = $new;

    $renewed = User::factory()->create();
    ($this->affiliate)($renewed, $this->current, 'paid', competitive: true);
    ($this->affiliate)($renewed, $this->previous, 'confirmed');
    $this->members['renewed'] = $renewed;

    $returning = User::factory()->create();
    ($this->affiliate)($returning, $this->current, 'confirmed');
    ($this->affiliate)($returning, $this->older, 'paid');
    $this->members['returning'] = $returning;

    $toFollowUp = User::factory()->create();
    ($this->affiliate)($toFollowUp, $this->previous, 'paid');
    ($this->affiliate)($toFollowUp, $this->current, 'cancelled');
    $this->members['to_follow_up'] = $toFollowUp;

    $former = User::factory()->create();
    ($this->affiliate)($former, $this->older, 'confirmed');
    ($this->affiliate)($former, $this->previous, 'refunded');
    $this->members['former'] = $former;

    $never = User::factory()->create();
    ($this->affiliate)($never, $this->current, 'cancelled');
    $this->members['never'] = $never;
});

it('reads every status the same way in SQL and on the row', function (): void {
    $rows = User::query()->withMembershipFacts()->get()->keyBy('id');

    foreach ($this->members as $expected => $member) {
        expect($rows[$member->id]->membershipStatus())->toBe(MembershipStatus::from($expected), "row of {$expected}")
            ->and(User::inMembershipStatus(MembershipStatus::from($expected))->pluck('id')->all())
            ->toBe([$member->id], "scope of {$expected}");
    }
});

it('works the status out on its own when the list did not load the facts', function (): void {
    foreach ($this->members as $expected => $member) {
        expect(User::findOrFail($member->id)->membershipStatus())->toBe(MembershipStatus::from($expected));
    }
});

it('combines several statuses in one filter', function (): void {
    $ids = User::inMembershipStatus(MembershipStatus::New, MembershipStatus::ToFollowUp)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$this->members['new']->id, $this->members['to_follow_up']->id]);
});

it('keeps the status filter inside its own parentheses', function (): void {
    // A bare orWhere would let the second status escape the condition next to it.
    $ids = User::inMembershipStatus(MembershipStatus::New, MembershipStatus::ToFollowUp)
        ->whereKey($this->members['new']->id)
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$this->members['new']->id]);
});

it('tells the licence only for a member affiliated this season', function (): void {
    $rows = User::query()->withMembershipFacts()->get()->keyBy('id');

    expect($rows[$this->members['renewed']->id]->holdsCompetitiveLicence())->toBeTrue()
        ->and($rows[$this->members['new']->id]->holdsCompetitiveLicence())->toBeFalse()
        ->and($rows[$this->members['to_follow_up']->id]->holdsCompetitiveLicence())->toBeFalse();
});

it('reads nobody as affiliated when no season is running', function (): void {
    $this->current->update(['is_active' => false]);
    cache()->forget('season.current');

    expect(User::inMembershipStatus(...MembershipStatus::currentMembers())->count())->toBe(0)
        ->and(User::findOrFail($this->members['renewed']->id)->membershipStatus())->toBe(MembershipStatus::Former);
});

describe('responsible adults', function (): void {
    beforeEach(function (): void {
        $this->parent = User::factory()->create();
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $guardian->users()->attach(User::factory()->minor()->create());

        // A guardian sheet with nobody on it answers for no one.
        $this->lapsed = User::factory()->create();
        Guardian::factory()->create(['user_id' => $this->lapsed->id]);
    });

    it('finds the members who answer for somebody', function (): void {
        expect(User::responsibleAdults()->pluck('id')->all())->toBe([$this->parent->id]);
    });

    it('flags them on the row without asking again', function (): void {
        $rows = User::query()->withMembershipFacts()->get()->keyBy('id');

        expect($rows[$this->parent->id]->isResponsibleAdult())->toBeTrue()
            ->and($rows[$this->lapsed->id]->isResponsibleAdult())->toBeFalse()
            ->and(User::findOrFail($this->parent->id)->isResponsibleAdult())->toBeTrue();
    });
});
