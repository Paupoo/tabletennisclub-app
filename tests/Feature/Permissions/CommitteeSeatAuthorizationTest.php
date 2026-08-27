<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| The club-info screen hands out and takes away committee seats, and neither of
| the two methods that do it asked anything of the caller. The screen sits behind
| `club.update` — the supervision délégation — so managing the venue settings also
| meant deciding who sits on the committee.
|
| Since the seat and its statutory title are rights (decision 18 of the access
| refactor), both are now manageAccess territory, like every other right.
*/

const CLUB_INFO = 'pages::club-admin.club-info';
const COMMITTEE_MODAL = 'club-admin.committee-modal';

beforeEach(function (): void {
    $this->supervisor = User::factory()->withRole(Role::SUPERVISION)->create();
    $this->accessManager = User::factory()->withRole(Role::ACCESS, Role::SUPERVISION)->create();
    $this->seated = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);
    $this->outsider = User::factory()->create();
});

describe('taking a seat away', function (): void {
    it('refuses the supervision delegate, who only manages the venue', function (): void {
        Livewire::actingAs($this->supervisor)
            ->test(CLUB_INFO)
            ->call('removeMember', $this->seated->id)
            ->assertForbidden();

        expect($this->seated->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->committee_role->toBe(CommitteeRolesEnum::SECRETARY);
    });

    it('allows the access manager', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(CLUB_INFO)
            ->call('removeMember', $this->seated->id);

        expect($this->seated->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->committee_role->toBeNull();
    });

    it('stops an access manager taking away their own seat', function (): void {
        $self = User::factory()
            ->isCommitteeMember()
            ->withRole(Role::ACCESS, Role::SUPERVISION)
            ->create(['committee_role' => CommitteeRolesEnum::PRESIDENT]);

        Livewire::actingAs($self)
            ->test(CLUB_INFO)
            ->call('removeMember', $self->id)
            ->assertForbidden();

        expect($self->fresh()->hasRole(Role::COMMITTEE->value))->toBeTrue();
    });

    it('hides the button from someone who may not press it', function (): void {
        Livewire::actingAs($this->supervisor)
            ->test(CLUB_INFO)
            ->assertDontSee('removeMember');

        Livewire::actingAs($this->accessManager)
            ->test(CLUB_INFO)
            ->assertSee('removeMember');
    });
});

describe('handing a seat out', function (): void {
    it('refuses the supervision delegate', function (): void {
        Livewire::actingAs($this->supervisor)
            ->test(COMMITTEE_MODAL)
            ->set('selectedMemberId', $this->outsider->id)
            ->set('selectedRoleId', CommitteeRolesEnum::TREASURER->value)
            ->call('addMember')
            ->assertForbidden();

        expect($this->outsider->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->committee_role->toBeNull();
    });

    it('allows the access manager', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(COMMITTEE_MODAL)
            ->set('selectedMemberId', $this->outsider->id)
            ->set('selectedRoleId', CommitteeRolesEnum::TREASURER->value)
            ->call('addMember');

        expect($this->outsider->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->committee_role->toBe(CommitteeRolesEnum::TREASURER);
    });

    it('stops an access manager seating themselves', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(COMMITTEE_MODAL)
            ->set('selectedMemberId', $this->accessManager->id)
            ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
            ->call('addMember')
            ->assertForbidden();

        expect($this->accessManager->fresh()->hasRole(Role::COMMITTEE->value))->toBeFalse();
    });
});
