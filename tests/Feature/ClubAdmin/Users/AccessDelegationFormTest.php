<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const MEMBER_FORM = 'pages::club-admin.users.form';

/*
| `membres` used to command the member's data *and* their rights, so the club
| could not hand out enrolments without handing out the keys to the application.
| The two are now separate rights, and the member's file has four states.
|
| The forged-payload block is the reason this file exists: a Livewire property
| stays writable from the client even when the markup never renders it, so
| "the section is not displayed" is a statement about the screen, never about
| what a save can write.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->dataDelegate = User::factory()->withRole(Role::MEMBERS)->create();
    $this->accessManager = User::factory()->withRole(Role::ACCESS)->create();
    $this->plainMember = User::factory()->create();

    $this->target = User::factory()->create([
        'first_name' => 'Origine',
        'licence' => '123456',
    ]);
});

describe('reaching the member file — the four states', function (): void {
    it('opens for both rights, for either one alone, and for neither', function (string $actor, int $status): void {
        $this->actingAs($this->{$actor})
            ->get(route('admin.users.edit', $this->target))
            ->assertStatus($status);
    })->with([
        'state 1 — data and rights' => ['admin', 200],
        'state 2 — data only' => ['dataDelegate', 200],
        'state 3 — rights only' => ['accessManager', 200],
        'state 4 — neither' => ['plainMember', 403],
    ]);

    it('keeps the create form on the right to create members', function (): void {
        $this->actingAs($this->accessManager)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($this->dataDelegate)->get(route('admin.users.create'))->assertOk();
    });

    it('opens the delegations overview to either right', function (): void {
        $this->actingAs($this->accessManager)->get(route('admin.users.delegations'))->assertOk();
        $this->actingAs($this->dataDelegate)->get(route('admin.users.delegations'))->assertOk();
        $this->actingAs($this->plainMember)->get(route('admin.users.delegations'))->assertForbidden();
    });

    it('shows the access manager the menu entry that leads there', function (): void {
        $this->actingAs($this->accessManager)
            ->get(route('admin.users.index'))
            ->assertSee(route('admin.users.delegations'));
    });
});

describe('state 1 — both rights', function (): void {
    it('writes the data and the rights in one save', function (): void {
        Livewire::actingAs($this->admin)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('first_name', 'Modifie')
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::SECRETARY->value)
            ->set('delegations', [Role::BAR->value])
            ->call('save');

        expect($this->target->fresh())
            ->first_name->toBe('Modifie')
            ->committee_role->toBe(CommitteeRolesEnum::SECRETARY)
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::BAR->value)->toBeTrue();
    });

    it('creates a member with their rights in one go', function (): void {
        Livewire::actingAs($this->admin)
            ->test(MEMBER_FORM)
            ->set('first_name', 'Neuf')
            ->set('last_name', 'Membre')
            ->set('email', 'neuf@example.test')
            ->set('street', 'Rue 1')
            ->set('city_code', '1340')
            ->set('city_name', 'Ottignies')
            ->set('phone_number', '+32470123456')
            ->set('password', 'Sup3r-Str0ng-P4ss!')
            ->set('password_confirmation', 'Sup3r-Str0ng-P4ss!')
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::PRESIDENT->value)
            ->set('delegations', [Role::BAR->value])
            ->call('save')
            ->assertHasNoErrors();

        expect(User::where('email', 'neuf@example.test')->first())
            ->not->toBeNull()
            ->committee_role->toBe(CommitteeRolesEnum::PRESIDENT)
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::BAR->value)->toBeTrue();
    });
});

describe('state 2 — data only, the members delegate', function (): void {
    it('saves the data it is there for', function (): void {
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('first_name', 'Modifie')
            ->call('save');

        expect($this->target->fresh()->first_name)->toBe('Modifie');
    });

    it('folds the rights layer into a read-only summary', function (): void {
        $held = User::factory()->withRole(Role::BAR)->create();

        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $held])
            ->assertSee(Role::BAR->label())
            ->assertDontSee(__('Operational duties. Anyone can hold them, and they stack.'));
    });

    it('summarises what the member holds, not what the payload claims', function (): void {
        $held = User::factory()->withRole(Role::BAR)->create();

        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $held])
            ->set('delegations', [Role::TREASURY->value])
            ->set('is_admin', true)
            ->assertSee(Role::BAR->label())
            ->assertDontSee(Role::TREASURY->label());
    });

    it('says nothing about the rights of a member who does not exist yet', function (): void {
        // The summary states what someone holds. On the create form there is no
        // one to hold anything, so "holds no management right" would be a fact
        // asserted about nobody.
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM)
            ->assertDontSee(__('Held rights, read-only. Handing them out is its own delegation.'))
            ->assertDontSee(__('No delegation: this member holds no management right.'));
    });

    it('keeps the empty state for a member holding nothing', function (): void {
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->assertSee(__('No delegation: this member holds no management right.'));
    });

    /*
     | The bug this whole batch closes: promoteAdmin existed, was tested, and was
     | never called. The checkbox rendered unconditionally and the value went
     | straight to the action.
     */
    it('refuses every forged right in the payload', function (): void {
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('is_admin', true)
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::PRESIDENT->value)
            ->set('delegations', [Role::TREASURY->value, Role::ACCESS->value])
            ->call('save');

        expect($this->target->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeFalse()
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->hasRole(Role::TREASURY->value)->toBeFalse()
            ->hasRole(Role::ACCESS->value)->toBeFalse()
            ->committee_role->toBeNull();
    });

    it('cannot make itself an administrator', function (): void {
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $this->dataDelegate])
            ->set('is_admin', true)
            ->call('save');

        expect($this->dataDelegate->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeFalse();
    });

    /*
     | The statutory title is required as soon as the committee box is ticked.
     | Folding the rights layer into a read-only summary took the title select
     | away from this actor — so the rule would demand a field they can no
     | longer reach, and a committee member who never had a title would become
     | unsaveable by the very person whose job is to keep their address current.
     */
    it('saves a titleless committee member it cannot give a title to', function (): void {
        $seatedWithoutTitle = User::factory()->isCommitteeMember()->create([
            'committee_role' => null,
            'first_name' => 'Origine',
        ]);

        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $seatedWithoutTitle])
            ->set('first_name', 'Modifie')
            ->call('save')
            ->assertHasNoErrors();

        expect($seatedWithoutTitle->fresh())
            ->first_name->toBe('Modifie')
            ->hasRole(Role::COMMITTEE->value)->toBeTrue();
    });

    it('still deletes the photo, which is data and therefore its business', function (): void {
        $withPhoto = User::factory()->create(['photo' => '/storage/users/portrait.jpg']);

        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $withPhoto])
            ->call('deletePhoto');

        expect($withPhoto->fresh()->photo)->toBeNull();
    });

    it('does not let a title pre-check duties it may not hand out', function (): void {
        Livewire::actingAs($this->dataDelegate)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('committee_role', CommitteeRolesEnum::TREASURER->value)
            ->assertSet('delegations', []);
    });
});

describe('state 3 — rights only, the access manager', function (): void {
    it('hands out a délégation and a committee seat', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::TREASURER->value)
            ->set('delegations', [Role::TREASURY->value])
            ->call('save');

        expect($this->target->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::TREASURY->value)->toBeTrue()
            ->committee_role->toBe(CommitteeRolesEnum::TREASURER);
    });

    it('renders the identity in read-only and none of the data sections', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->assertSee('Origine')
            ->assertSee('123456')
            ->assertSee(__('Delegations'))
            ->assertDontSee(__('Secure your account'))
            ->assertDontSee(__('Personal information'))
            ->assertDontSee(__('Legal guardians'));
    });

    it('refuses every forged data field in the payload', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('first_name', 'Falsifie')
            ->set('licence', '999999')
            ->set('iban', 'BE68539007547034')
            ->set('has_key', true)
            ->set('delegations', [Role::BAR->value])
            ->call('save');

        expect($this->target->fresh())
            ->first_name->toBe('Origine')
            ->licence->toBe('123456')
            ->iban->toBeNull()
            ->has_key->toBeFalse()
            ->hasRole(Role::BAR->value)->toBeTrue();
    });

    /*
     | Decision 9, made testable: the rights branch never instantiates
     | UpdateUserData, so it never runs the data rules either. A managed account
     | has no address of its own, and a save that demanded one would leave the
     | access manager unable to hand that member anything.
     */
    it('saves the rights of a member whose data would not validate', function (): void {
        $managed = User::factory()->create(['email' => null]);

        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $managed])
            ->set('delegations', [Role::COACH->value])
            ->call('save')
            ->assertHasNoErrors();

        expect($managed->fresh()->hasRole(Role::COACH->value))->toBeTrue();
    });

    it('cannot hand out the délégation that hands out délégations', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('delegations', [Role::ACCESS->value, Role::BAR->value])
            ->call('save');

        expect($this->target->fresh())
            ->hasRole(Role::ACCESS->value)->toBeFalse()
            ->hasRole(Role::BAR->value)->toBeTrue();
    });

    it('renders that délégation locked rather than hiding it', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->assertSee(Role::ACCESS->label())
            ->assertSee(__('Reserved to administrators'));
    });

    /*
     | Widening mount() widened the whole component, not just its markup: every
     | public method on it became callable by an actor holding only
     | `access.manage`, whether or not the Blade renders a trigger. deletePhoto
     | rendered none and was gated by nothing.
     */
    it('cannot delete the photo through a method the markup never renders', function (): void {
        $withPhoto = User::factory()->create(['photo' => '/storage/users/portrait.jpg']);

        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $withPhoto])
            ->call('deletePhoto')
            ->assertForbidden();

        expect($withPhoto->fresh()->photo)->toBe('/storage/users/portrait.jpg');
    });

    it('cannot promote anyone to administrator', function (): void {
        Livewire::actingAs($this->accessManager)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->set('is_admin', true)
            ->call('save');

        expect($this->target->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeFalse();
    });
});

describe('nobody edits their own rights', function (): void {
    it('stops an access manager on their own file', function (): void {
        $this->actingAs($this->accessManager)
            ->get(route('admin.users.edit', $this->accessManager))
            ->assertForbidden();
    });

    it('leaves an administrator their data and takes away their rights', function (): void {
        Livewire::actingAs($this->admin)
            ->test(MEMBER_FORM, ['user' => $this->admin])
            ->set('first_name', 'Renomme')
            ->set('delegations', [Role::BAR->value])
            ->call('save');

        expect($this->admin->fresh())
            ->first_name->toBe('Renomme')
            ->hasRole(Role::BAR->value)->toBeFalse()
            ->hasRole(Role::ADMINISTRATOR->value)->toBeTrue();
    });

    it('never lets an administrator strip their own administrator role', function (): void {
        // A second administrator exists, so the "last administrator" rule is not
        // what stops this: the refusal has to come from the self-edit lock.
        User::factory()->isAdmin()->create();

        Livewire::actingAs($this->admin)
            ->test(MEMBER_FORM, ['user' => $this->admin])
            ->set('is_admin', false)
            ->call('save');

        expect($this->admin->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeTrue();
    });
});

describe('state 4 — neither right', function (): void {
    /*
     | mount() is the gate, so a plain member never gets a component to call
     | save() on: the refusal is asserted over HTTP in the matrix above. What
     | save() adds is that it asks for the two rights separately rather than
     | trusting the mount that let the actor in — which is what the two
     | forged-payload scenarios above actually measure.
     */
    it('refuses the component itself, not just the route', function (): void {
        Livewire::actingAs($this->plainMember)
            ->test(MEMBER_FORM, ['user' => $this->target])
            ->assertForbidden();
    });
});
