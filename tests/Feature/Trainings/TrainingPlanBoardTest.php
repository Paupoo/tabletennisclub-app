<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Http\Testing\File;
use Livewire\Livewire;

const BOARD = 'pages::club-admin.planning.board';

beforeEach(function (): void {
    $this->season = makeActiveSeason();

    $this->manager = User::factory()->isCommitteeMember()->withRole(Role::TRAININGS)->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    $this->viewer = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::TREASURER,
    ]);
});

describe('plan management', function (): void {
    it('lists the plans of the active season', function (): void {
        TrainingPlan::factory()->create([
            'season_id' => $this->season->id,
            'name' => 'Scenario Alpha',
        ]);

        $otherSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2)->startOfYear(),
            'end_at' => now()->subYears(2)->endOfYear(),
        ]);
        TrainingPlan::factory()->create([
            'season_id' => $otherSeason->id,
            'name' => 'Scenario Beta',
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->assertSee('Scenario Alpha')
            ->assertDontSee('Scenario Beta');
    });

    it('creates a plan by seeding from the season', function (): void {
        $member = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $this->season->id,
            'status' => 'paid',
            'wants_directed_training' => true,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->set('newPlanName', 'Fresh Plan')
            ->call('createPlan')
            ->assertHasNoErrors()
            ->assertSee('Fresh Plan');

        $plan = TrainingPlan::query()->where('name', 'Fresh Plan')->first();
        expect($plan)->not->toBeNull()
            ->and($plan->season_id)->toBe($this->season->id)
            ->and($plan->status)->toBe(TrainingPlanStatusEnum::DRAFT)
            ->and($plan->created_by)->toBe($this->manager->id);

        // The seed pushed the season member into the pool.
        expect($plan->assignments()->where('user_id', $member->id)->exists())->toBeTrue();
    });

    it('archives a plan', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->call('archivePlan', $plan->id)
            ->assertHasNoErrors();

        expect($plan->fresh()->status)->toBe(TrainingPlanStatusEnum::ARCHIVED);
    });

    it('deletes a plan', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->call('deletePlan', $plan->id)
            ->assertHasNoErrors();

        expect(TrainingPlan::query()->whereKey($plan->id)->exists())->toBeFalse();
    });

    it('opens a confirm modal and archives via the stored id (no native confirm)', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->call('confirmArchivePlan', $plan->id)
            ->assertSet('confirmArchiveModal', true)
            ->assertSet('pendingPlanId', $plan->id)
            ->call('archivePlan') // no argument: uses the stored id
            ->assertSet('confirmArchiveModal', false)
            ->assertSet('pendingPlanId', null);

        expect($plan->fresh()->status)->toBe(TrainingPlanStatusEnum::ARCHIVED);
    });

    it('opens a confirm modal and deletes via the stored id', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD)
            ->call('confirmDeletePlan', $plan->id)
            ->assertSet('confirmDeleteModal', true)
            ->assertSet('pendingPlanId', $plan->id)
            ->call('deletePlan')
            ->assertSet('confirmDeleteModal', false);

        expect(TrainingPlan::query()->whereKey($plan->id)->exists())->toBeFalse();
    });
});

describe('board rendering', function (): void {
    it('renders the pack columns and the pool with member cards', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create([
            'name' => 'Tuesday Advanced',
            'max_participants' => 6,
        ]);

        $assigned = User::factory()->create([
            'first_name' => 'Assigned',
            'last_name' => 'Member',
            'ranking' => 'B0',
            'birthdate' => now()->subYears(30),
        ]);
        Subscription::factory()->create([
            'user_id' => $assigned->id,
            'season_id' => $this->season->id,
            'status' => 'paid',
            'is_competitive' => true,
            'can_drive' => true,
        ]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $assigned->id,
        ]);

        $pooled = User::factory()->create([
            'first_name' => 'Pooled',
            'last_name' => 'Person',
        ]);
        Subscription::factory()->create([
            'user_id' => $pooled->id,
            'season_id' => $this->season->id,
            'status' => 'paid',
        ]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->inPool()->create([
            'user_id' => $pooled->id,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->assertSee('Tuesday Advanced')
            ->assertSee('Pool')
            ->assertSee('Assigned Member')
            ->assertSee('Pooled Person')
            ->assertSee('B0');
    });

    it('renders without crashing when an assigned member has been archived', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create(['name' => 'Tuesday Advanced']);

        $archived = User::factory()->create(['first_name' => 'Gone', 'last_name' => 'Member']);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $archived->id,
        ]);
        $archived->delete();

        $stillHere = User::factory()->create(['first_name' => 'Still', 'last_name' => 'Here']);
        Subscription::factory()->create([
            'user_id' => $stillHere->id,
            'season_id' => $this->season->id,
            'status' => 'paid',
        ]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->inPool()->create([
            'user_id' => $stillHere->id,
        ]);

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->assertOk()
            ->assertDontSee('Gone Member')
            ->assertSee('Still Here');

        $packColumn = collect($component->viewData('columns'))->firstWhere('id', 'pack-' . $pack->id);
        expect($packColumn['cards'])->toHaveCount(0)
            ->and($packColumn['current_count'])->toBe(0);
    });
});

describe('pool ranking series derivation', function (): void {
    it('maps a ranking to its series letter or NC when unranked', function (string $ranking, string $expected): void {
        $component = Livewire::actingAs($this->manager)->test(BOARD);

        expect($component->instance()->rankingSeries($ranking))->toBe($expected);
    })->with([
        'B series' => ['B2', 'B'],
        'D series' => ['D0', 'D'],
        'explicit NC' => ['NC', 'NC'],
        'NA is unranked' => ['NA', 'NC'],
        'empty is unranked' => ['', 'NC'],
        'lowercase e is normalised' => ['e6', 'E'],
        'uppercase E' => ['E6', 'E'],
        'A series' => ['A1', 'A'],
        'C series' => ['C4', 'C'],
    ]);
});

describe('pool filtering', function (): void {
    /**
     * Helper: create a pooled member with the given ranking/birthdate.
     */
    $makePooled = function (TrainingPlan $plan, Season $season, string $first, ?string $ranking, $birthdate): void {
        $user = User::factory()->create([
            'first_name' => $first,
            'last_name' => 'Pool',
            'ranking' => $ranking,
            'birthdate' => $birthdate,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'paid',
        ]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->inPool()->create([
            'user_id' => $user->id,
        ]);
    };

    it('shows the whole pool when no filter is active', function () use ($makePooled): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $makePooled($plan, $this->season, 'Childish', 'B2', now()->subYears(10));
        $makePooled($plan, $this->season, 'Grownup', 'D0', now()->subYears(40));

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id]);

        $pool = collect($component->viewData('columns'))->firstWhere('is_pool', true);

        expect($pool['cards'])->toHaveCount(2);
    });

    it('filters the pool by age category without touching the groups', function () use ($makePooled): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create(['name' => 'Group A']);

        // A group member (adult) — must stay regardless of the pool filter.
        $grouped = User::factory()->create(['first_name' => 'Grouped', 'birthdate' => now()->subYears(35)]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $grouped->id,
        ]);

        $makePooled($plan, $this->season, 'Kiddo', 'B2', now()->subYears(10));
        $makePooled($plan, $this->season, 'Adultish', 'B2', now()->subYears(40));

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('poolAgeFilter', AgeCategoryEnum::CHILD->value);

        $columns = collect($component->viewData('columns'));
        $pool = $columns->firstWhere('is_pool', true);
        $group = $columns->firstWhere('id', 'pack-' . $pack->id);

        expect($pool['cards'])->toHaveCount(1)
            ->and($pool['cards'][0]['name'])->toBe('Kiddo Pool')
            ->and($group['cards'])->toHaveCount(1);
    });

    it('filters the pool by ranking series', function () use ($makePooled): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $makePooled($plan, $this->season, 'BeeOne', 'B2', now()->subYears(30));
        $makePooled($plan, $this->season, 'DeeOne', 'D0', now()->subYears(30));
        $makePooled($plan, $this->season, 'NoRank', '', now()->subYears(30));

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('poolSeriesFilter', 'D');

        $pool = collect($component->viewData('columns'))->firstWhere('is_pool', true);

        expect($pool['cards'])->toHaveCount(1)
            ->and($pool['cards'][0]['name'])->toBe('Deeone Pool');
    });

    it('filters the pool to unranked members with the NC series', function () use ($makePooled): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $makePooled($plan, $this->season, 'BeeOne', 'B2', now()->subYears(30));
        $makePooled($plan, $this->season, 'NoRank', '', now()->subYears(30));

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('poolSeriesFilter', 'NC');

        $pool = collect($component->viewData('columns'))->firstWhere('is_pool', true);

        expect($pool['cards'])->toHaveCount(1)
            ->and($pool['cards'][0]['name'])->toBe('Norank Pool');
    });

    it('combines age and series filters on the pool', function () use ($makePooled): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $makePooled($plan, $this->season, 'ChildB', 'B2', now()->subYears(10));
        $makePooled($plan, $this->season, 'AdultB', 'B2', now()->subYears(40));
        $makePooled($plan, $this->season, 'ChildD', 'D0', now()->subYears(10));

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('poolAgeFilter', AgeCategoryEnum::CHILD->value)
            ->set('poolSeriesFilter', 'B');

        $pool = collect($component->viewData('columns'))->firstWhere('is_pool', true);

        expect($pool['cards'])->toHaveCount(1)
            ->and($pool['cards'][0]['name'])->toBe('Childb Pool');
    });
});

describe('drag and drop', function (): void {
    it('moves an assignment to another pack and persists the pack id and position', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $packA = TrainingPlanPack::factory()->for($plan, 'plan')->create(['position' => 0]);
        $packB = TrainingPlanPack::factory()->for($plan, 'plan')->create(['position' => 1]);

        $member = User::factory()->create();
        $assignment = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($packA, 'pack')->create([
            'user_id' => $member->id,
            'position' => 0,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('moveAssignment', $assignment->id, 0, 'pack-' . $packB->id)
            ->assertHasNoErrors();

        $assignment->refresh();
        expect($assignment->training_plan_pack_id)->toBe($packB->id)
            ->and($assignment->position)->toBe(0);
    });

    it('moves an assignment to the pool (null pack)', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();

        $member = User::factory()->create();
        $assignment = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $member->id,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('moveAssignment', $assignment->id, 0, 'pool')
            ->assertHasNoErrors();

        expect($assignment->fresh()->training_plan_pack_id)->toBeNull();
    });

    it('reorders within the same column', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();

        $first = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => User::factory()->create()->id,
            'position' => 0,
        ]);
        $second = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => User::factory()->create()->id,
            'position' => 1,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('moveAssignment', $second->id, 0, 'pack-' . $pack->id)
            ->assertHasNoErrors();

        expect($second->fresh()->position)->toBe(0)
            ->and($second->fresh()->training_plan_pack_id)->toBe($pack->id);
    });
});

describe('capacity tensions', function (): void {
    it('flags a pack that is over capacity', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create([
            'name' => 'Crowded Pack',
            'max_participants' => 1,
        ]);

        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create(['user_id' => User::factory()]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create(['user_id' => User::factory()]);

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id]);

        $columns = $component->viewData('columns');
        $packColumn = collect($columns)->firstWhere('id', 'pack-' . $pack->id);

        expect($packColumn['over_capacity'])->toBeTrue()
            ->and($packColumn['current_count'])->toBe(2)
            ->and($packColumn['max_participants'])->toBe(1);

        $component->assertSet('overCapacityCount', 1);
    });
});

describe('pack management', function (): void {
    it('closes the add/edit pack modal and clears the form', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('openAddPack')
            ->assertSet('showPackModal', true)
            ->set('packName', 'Half typed')
            ->call('closePackModal')
            ->assertSet('showPackModal', false)
            ->assertSet('packName', '')
            ->assertHasNoErrors();
    });

    it('adds a hypothetical pack to the selected plan with incremental position', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        TrainingPlanPack::factory()->for($plan, 'plan')->create(['position' => 0]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('packName', 'Saturday Beginners')
            ->set('packLevel', 'C')
            ->set('packDayOfWeek', 6)
            ->set('packMaxParticipants', 8)
            ->call('addPack')
            ->assertHasNoErrors();

        $pack = TrainingPlanPack::query()
            ->where('training_plan_id', $plan->id)
            ->where('name', 'Saturday Beginners')
            ->first();

        expect($pack)->not->toBeNull()
            ->and($pack->source_training_pack_id)->toBeNull()
            ->and($pack->level)->toBe('C')
            ->and($pack->day_of_week)->toBe(6)
            ->and($pack->max_participants)->toBe(8)
            ->and($pack->position)->toBe(1);
    });

    it('requires a name when adding a pack', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('packName', '')
            ->call('addPack')
            ->assertHasErrors(['packName' => 'required']);
    });

    it('edits a pack snapshot without touching the real training pack', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $realPack = TrainingPack::factory()->create([
            'season_id' => $this->season->id,
            'name' => 'Real Untouched',
            'max_participants' => 10,
        ]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->fromPack($realPack)->create([
            'name' => 'Snapshot Name',
            'level' => 'B',
            'day_of_week' => 2,
            'max_participants' => 6,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('editPack', $pack->id)
            ->assertSet('packName', 'Snapshot Name')
            ->assertSet('packLevel', 'B')
            ->set('packName', 'Edited Snapshot')
            ->set('packMaxParticipants', 12)
            ->call('savePack')
            ->assertHasNoErrors();

        $pack->refresh();
        expect($pack->name)->toBe('Edited Snapshot')
            ->and($pack->max_participants)->toBe(12)
            ->and($pack->source_training_pack_id)->toBe($realPack->id);

        // The real training pack is untouched.
        expect($realPack->fresh()->name)->toBe('Real Untouched')
            ->and($realPack->fresh()->max_participants)->toBe(10);
    });

    it('removes a pack and reassigns its members to the pool', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();

        $a = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => User::factory()->create()->id,
        ]);
        $b = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('removePack', $pack->id)
            ->assertHasNoErrors();

        // Pack is gone.
        expect(TrainingPlanPack::query()->whereKey($pack->id)->exists())->toBeFalse();

        // Both members survive, now in the pool (null pack).
        expect($plan->assignments()->count())->toBe(2)
            ->and($a->fresh()->training_plan_pack_id)->toBeNull()
            ->and($b->fresh()->training_plan_pack_id)->toBeNull();
    });

    it('opens a confirm modal and removes the pack via the stored id', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('confirmRemovePack', $pack->id)
            ->assertSet('confirmRemovePackModal', true)
            ->assertSet('pendingPackId', $pack->id)
            ->call('removePack') // no argument: uses the stored id
            ->assertSet('confirmRemovePackModal', false)
            ->assertSet('pendingPackId', null);

        expect(TrainingPlanPack::query()->whereKey($pack->id)->exists())->toBeFalse();
    });

    it('reflects an edited capacity in the tension flag', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 5]);

        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create(['user_id' => User::factory()]);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create(['user_id' => User::factory()]);

        $component = Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id]);

        // Not over capacity at max 5 with 2 members.
        $column = collect($component->viewData('columns'))->firstWhere('id', 'pack-' . $pack->id);
        expect($column['over_capacity'])->toBeFalse();

        // Reduce capacity below the headcount.
        $component->call('editPack', $pack->id)
            ->set('packMaxParticipants', 1)
            ->call('savePack')
            ->assertHasNoErrors();

        $column = collect($component->viewData('columns'))->firstWhere('id', 'pack-' . $pack->id);
        expect($column['over_capacity'])->toBeTrue()
            ->and($column['current_count'])->toBe(2)
            ->and($column['max_participants'])->toBe(1);
    });
});

describe('export and import actions', function (): void {
    it('lets the committee export the plan as a download', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create(['name' => 'Export Pack']);
        $member = User::factory()->create(['licence' => 'EXP01']);
        TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $member->id,
        ]);

        // A read-only committee viewer can export.
        $response = Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('export', 'csv');

        $response->assertFileDownloaded();
    });

    it('imports a CSV and reports the summary as a manager', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $packA = TrainingPlanPack::factory()->for($plan, 'plan')->create(['name' => 'Pack A']);
        $packB = TrainingPlanPack::factory()->for($plan, 'plan')->create(['name' => 'Pack B']);

        $member = User::factory()->create(['licence' => 'IMP01']);
        Subscription::factory()->create(['user_id' => $member->id, 'season_id' => $this->season->id, 'status' => 'paid']);
        $assignment = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($packA, 'pack')->create([
            'user_id' => $member->id,
        ]);

        $header = implode(',', [
            __('Pack'), __('Licence'), __('Last name'), __('First name'),
            __('Ranking'), __('Age category'), __('Competitive'),
            __('Can drive'), __('Captain'), __('Volunteer'),
        ]);
        $csv = $header . "\nPack B,IMP01,,,,,,,,";

        $file = File::createWithContent('plan.csv', $csv);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors();

        expect($assignment->fresh()->training_plan_pack_id)->toBe($packB->id);
    });

    it('forbids a viewer from importing', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('openImport')
            ->assertForbidden();

        $file = File::createWithContent('plan.csv', 'x');

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('importFile', $file)
            ->call('import')
            ->assertForbidden();
    });
});

describe('optimize action', function (): void {
    it('lets a manager suggest a layout, assigning the pool members', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 5]);

        $member = User::factory()->create(['ranking' => 'C0', 'birthdate' => now()->subYears(30)]);
        Subscription::factory()->create(['user_id' => $member->id, 'season_id' => $this->season->id, 'status' => 'paid']);
        $assignment = TrainingPlanAssignment::factory()->for($plan, 'plan')->inPool()->create([
            'user_id' => $member->id,
        ]);

        Livewire::actingAs($this->manager)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('optimize')
            ->assertHasNoErrors();

        expect($assignment->fresh()->training_plan_pack_id)->not->toBeNull();
    });

    it('forbids a viewer from optimizing', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('optimize')
            ->assertForbidden();
    });
});

describe('authorization', function (): void {
    it('lets a committee viewer see the board but not mutate', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();
        $member = User::factory()->create();
        $assignment = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
            'user_id' => $member->id,
        ]);

        // Viewer can render.
        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->assertOk()
            ->assertSet('canManage', false);

        // Mutations are forbidden.
        Livewire::actingAs($this->viewer)
            ->test(BOARD)
            ->set('newPlanName', 'Nope')
            ->call('createPlan')
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test(BOARD)
            ->call('archivePlan', $plan->id)
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('moveAssignment', $assignment->id, 0, 'pool')
            ->assertForbidden();

        // Pack mutations are forbidden too.
        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('packName', 'Nope')
            ->call('addPack')
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->set('packName', 'Nope')
            ->call('savePack')
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('removePack', $pack->id)
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test(BOARD, ['selectedPlanId' => $plan->id])
            ->call('optimize')
            ->assertForbidden();
    });
});
