<?php

declare(strict_types=1);

use App\Actions\User\CancelMemberDepartureAction;
use App\Actions\User\DeclareMemberDepartureAction;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Services\AudienceBuilder;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Data\MemberDepartureOutcome;
use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Shared\Enums\MembershipStatus;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Services\TrainingAttendanceService;

pest()->group('club-admin', 'users');

/*
| A member who tells the club they are leaving. The departure is not a
| cancellation: the affiliation and the money stay as they are. What it does is
| hand back what the member was holding for the season — a place on a team
| sheet, a captaincy, a seat in the training sessions still to come — and take
| them out of the club-wide mailings.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $this->office = User::factory()->isAdmin()->create();

    $this->member = User::factory()->create(['emails_notifications' => true]);
    $this->subscription = Subscription::factory()->for($this->member)->for($this->season)->create([
        'status' => 'paid',
        'is_competitive' => true,
    ]);

    // A second member who stays, so that no assertion passes on an empty roster.
    $this->stays = User::factory()->create(['emails_notifications' => true]);
    Subscription::factory()->for($this->stays)->for($this->season)->create(['status' => 'paid', 'is_competitive' => true]);

    $this->declare = fn (User $member, DepartureReason $reason = DepartureReason::Moving, ?string $note = null): MemberDepartureOutcome => DeclareMemberDepartureAction::handle(
        $member,
        now()->subDays(3),
        $reason,
        $note,
        $this->office,
    );
});

describe('declaring a departure', function (): void {
    it('records it for the running season, with who recorded it', function (): void {
        ($this->declare)($this->member, DepartureReason::Transfer, 'Joue à Wavre');

        $departure = MemberDeparture::sole();

        expect($departure->user_id)->toBe($this->member->id)
            ->and($departure->season_id)->toBe($this->season->id)
            ->and($departure->left_on->toDateString())->toBe(now()->subDays(3)->toDateString())
            ->and($departure->reason)->toBe(DepartureReason::Transfer)
            ->and($departure->note)->toBe('Joue à Wavre')
            ->and($departure->recorded_by)->toBe($this->office->id)
            ->and(User::findOrFail($this->member->id)->membershipStatus())->toBe(MembershipStatus::Left);
    });

    it('leaves the affiliation and its money alone', function (): void {
        ($this->declare)($this->member);

        expect($this->subscription->fresh()->status)->toBe('paid')
            ->and(User::findOrFail($this->member->id)->isAffiliatedForCurrentSeason())->toBeTrue();
    });

    it('corrects the departure already declared this season rather than adding one', function (): void {
        ($this->declare)($this->member, DepartureReason::Moving);
        ($this->declare)($this->member, DepartureReason::Cost);

        expect(MemberDeparture::sole()->reason)->toBe(DepartureReason::Cost);
    });

    it('refuses when no season is running', function (): void {
        $this->season->update(['is_active' => false]);
        cache()->forget('season.current');

        ($this->declare)($this->member);
    })->throws(DomainException::class);
});

describe('what the departure hands back', function (): void {
    beforeEach(function (): void {
        $ownClub = Club::factory()->ownClub()->create();
        $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
        $previousLeague = League::factory()->create(['season_id' => $this->previous->id, 'category' => 'MEN']);

        $this->team = Team::factory()->create([
            'season_id' => $this->season->id,
            'club_id' => $ownClub->id,
            'league_id' => $league->id,
            'name' => 'C',
            'captain_id' => $this->member->id,
        ]);
        $this->team->users()->attach([$this->member->id, $this->stays->id]);

        $this->lastYearsTeam = Team::factory()->create([
            'season_id' => $this->previous->id,
            'club_id' => $ownClub->id,
            'league_id' => $previousLeague->id,
            'name' => 'B',
            'captain_id' => $this->member->id,
        ]);
        $this->lastYearsTeam->users()->attach($this->member->id);
    });

    it('takes the member off this season’s team sheets and names the teams left without a captain', function (): void {
        $outcome = ($this->declare)($this->member);

        expect($outcome->teamsWithoutCaptain)->toBe(['C'])
            ->and($this->team->fresh()->captain_id)->toBeNull()
            ->and($this->team->users()->pluck('users.id')->all())->toBe([$this->stays->id]);
    });

    it('leaves the teams of past seasons as they were', function (): void {
        ($this->declare)($this->member);

        expect($this->lastYearsTeam->fresh()->captain_id)->toBe($this->member->id)
            ->and($this->lastYearsTeam->users()->pluck('users.id')->all())->toBe([$this->member->id]);
    });

    it('takes the member out of this season’s training plans, not the past ones', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pastPlan = TrainingPlan::factory()->create(['season_id' => $this->previous->id]);
        TrainingPlanAssignment::factory()->inPool()->create(['training_plan_id' => $plan->id, 'user_id' => $this->member->id]);
        TrainingPlanAssignment::factory()->inPool()->create(['training_plan_id' => $plan->id, 'user_id' => $this->stays->id]);
        TrainingPlanAssignment::factory()->inPool()->create(['training_plan_id' => $pastPlan->id, 'user_id' => $this->member->id]);

        ($this->declare)($this->member);

        expect(TrainingPlanAssignment::query()->where('training_plan_id', $plan->id)->pluck('user_id')->all())->toBe([$this->stays->id])
            ->and(TrainingPlanAssignment::query()->where('training_plan_id', $pastPlan->id)->pluck('user_id')->all())->toBe([$this->member->id]);
    });
});

describe('the training packs', function (): void {
    beforeEach(function (): void {
        $this->pack = makeTrainingPack($this->season);
        $this->subscription->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);
        Subscription::query()->where('user_id', $this->stays->id)->sole()
            ->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);

        $this->past = Training::factory()->create([
            'training_pack_id' => $this->pack->id,
            'season_id' => $this->season->id,
            'start' => now()->subWeeks(2),
            'end' => now()->subWeeks(2)->addHours(2),
        ]);
        app(TrainingAttendanceService::class)->record($this->past, $this->member, 'present');
    });

    it('no longer counts the member among the trainees of the sessions to come', function (): void {
        ($this->declare)($this->member);

        expect($this->pack->trainees()->pluck('users.id')->all())->toBe([$this->stays->id]);
    });

    it('keeps the attendance already taken and the enrolment the member is billed for', function (): void {
        $amountDue = $this->subscription->fresh()->amount_due;

        ($this->declare)($this->member);

        expect($this->past->trainees()->pluck('users.id')->all())->toBe([$this->member->id])
            ->and($this->subscription->trainingPacks()->sole()->pivot->status)->toBe('enrolled')
            ->and($this->subscription->fresh()->amount_due)->toBe($amountDue);
    });
});

describe('the club-wide mailings', function (): void {
    it('no longer write to a member who left', function (): void {
        ($this->declare)($this->member);

        $audience = app(AudienceBuilder::class)->build(new AudienceCriteria(base: AudienceBase::Active));

        expect($audience->members->pluck('id')->all())->toBe([$this->stays->id]);
    });

    it('no longer ask a member of last season who said they would not come back', function (): void {
        $lastSeason = User::factory()->create();
        Subscription::factory()->for($lastSeason)->for($this->previous)->create(['status' => 'paid']);
        $quiet = User::factory()->create();
        Subscription::factory()->for($quiet)->for($this->previous)->create(['status' => 'paid']);

        ($this->declare)($lastSeason, DepartureReason::Health);

        $audience = app(AudienceBuilder::class)->build(new AudienceCriteria(base: AudienceBase::FormerMembers));

        expect($audience->members->pluck('id')->all())->toBe([$quiet->id]);
    });
});

describe('cancelling a departure', function (): void {
    it('deletes the record and gives the member their status back', function (): void {
        ($this->declare)($this->member);

        CancelMemberDepartureAction::handle($this->member);

        expect(MemberDeparture::count())->toBe(0)
            ->and(User::findOrFail($this->member->id)->membershipStatus())->toBe(MembershipStatus::New);
    });

    it('leaves the departure of an earlier season alone', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->previous)->create();

        CancelMemberDepartureAction::handle($this->member);

        expect(MemberDeparture::count())->toBe(1);
    });
});
