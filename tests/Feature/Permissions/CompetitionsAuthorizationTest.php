<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Tournaments and trainings answered to committee membership; the live centre
| carried its own inline copy of the rule, and the planning board leaned on the
| manage-season gate. Leading a session and building the season's offer are two
| duties, so a coach no longer inherits the planning screens.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->committeeOnly = User::factory()->isCommitteeMember()->create();
    $this->member = User::factory()->create();
});

describe('tournaments', function (): void {
    it('opens to the tournaments delegate', function (): void {
        $this->actingAs(User::factory()->withRole(Role::TOURNAMENTS)->create())
            ->get(route('admin.tournaments.index'))
            ->assertOk();
    });

    it('no longer opens on committee membership alone', function (): void {
        $this->actingAs($this->committeeOnly)->get(route('admin.tournaments.index'))->assertForbidden();
        $this->actingAs($this->member)->get(route('admin.tournaments.index'))->assertForbidden();
    });

    it('reserves running a tournament to the delegate', function (): void {
        $tournament = Tournament::factory()->create();
        $delegate = User::factory()->withRole(Role::TOURNAMENTS)->create();

        expect($delegate->can('update', $tournament))->toBeTrue()
            ->and($this->committeeOnly->can('update', $tournament))->toBeFalse();
    });

    it('keeps the list visible to members, who register from it', function (): void {
        expect($this->member->can('viewAny', Tournament::class))->toBeTrue();
    });
});

describe('trainings', function (): void {
    it('separates leading a session from building the offer', function (): void {
        $coach = User::factory()->withRole(Role::COACH)->create();
        $builder = User::factory()->withRole(Role::TRAININGS)->create();

        // The coach runs sessions…
        $this->actingAs($coach)->get(route('coach.trainings'))->assertOk();
        // …but does not shape the season's offer.
        $this->actingAs($coach)->get(route('admin.trainings.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.planning.board'))->assertForbidden();

        // And the other way round.
        $this->actingAs($builder)->get(route('admin.trainings.index'))->assertOk();
        $this->actingAs($builder)->get(route('admin.planning.board'))->assertOk();
        $this->actingAs($builder)->get(route('coach.trainings'))->assertForbidden();
    });

    it('no longer opens the training screens on committee membership alone', function (): void {
        $this->actingAs($this->committeeOnly)->get(route('admin.trainings.index'))->assertForbidden();
        $this->actingAs($this->committeeOnly)->get(route('admin.planning.board'))->assertForbidden();
    });

    it('lets the trainer of a pack read it without holding the duty', function (): void {
        $trainer = User::factory()->withRole(Role::COACH)->create();
        $pack = TrainingPack::factory()->create(['trainer_id' => $trainer->id, 'season_id' => $this->season->id]);
        $otherPack = TrainingPack::factory()->create(['season_id' => $this->season->id]);

        expect($trainer)
            ->can('view', $pack)->toBeTrue()
            ->can('update', $pack)->toBeFalse()
            ->and($this->member->can('view', $otherPack))->toBeFalse();
    });
});
