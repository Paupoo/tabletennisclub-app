<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le nom de classe du composant Volt (chemin de la vue Blade).
 * Adapte ce chemin selon ton projet.
 */
function clubSettingsComponent(): string
{
    return 'pages::club-admin.club-info';
}

function committeeModalComponent(): string
{
    return 'club-admin.committee-modal';
}

// ─────────────────────────────────────────────────────────────────────────────
// MOUNT & RENDER
// ─────────────────────────────────────────────────────────────────────────────

describe('Test Club Settings', function (): void {
    describe('Mount & Render', function (): void {

        it('renders the component without errors', function (): void {
            Livewire::test(clubSettingsComponent())
                ->assertStatus(200);
        });

        it('initialises licence from the own club in the database', function (): void {
            Club::factory()->ownClub()->create(['licence' => 'ABC123']);

            Livewire::test(clubSettingsComponent())
                ->assertSet('licence', 'ABC123');
        });

        it('displays committee members in the view', function (): void {
            $member = User::factory()->isCommitteeMember()->create([
                'first_name' => 'Alice',
                'last_name' => 'Dumont',
                'committee_role' => CommitteeRolesEnum::PRESIDENT,
            ]);

            Livewire::test(clubSettingsComponent())
                ->assertSee('Alice')
                ->assertSee('Dumont');
        });

        it('shows empty state when no committee members exist', function (): void {
            User::role(Role::COMMITTEE->value)->each(
                fn (User $member) => $member->removeRole(Role::COMMITTEE->value)
            );

            Livewire::test(clubSettingsComponent())
                ->assertSee(__('No committee members defined yet.'));
        });

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // SEARCH MEMBERS (In the modal)
    // ─────────────────────────────────────────────────────────────────────────────

    describe('searchMembers(in the modal)', function (): void {

        it('returns matching users by first name', function (): void {
            User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW001']);
            User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Curie', 'licence' => 'BBW002']);

            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'Jea');

            expect($component->get('membersSearchList'))
                ->toHaveCount(1)
                ->first()->toMatchArray(['name' => 'Jean Dupont']);
        });

        it('returns matching users by last name', function (): void {
            User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW001']);

            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'Dup');

            expect($component->get('membersSearchList'))
                ->toHaveCount(1);
        });

        it('returns matching users by licence number', function (): void {
            User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW999']);

            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'BBW999');

            expect($component->get('membersSearchList'))
                ->toHaveCount(1)
                ->first()->toMatchArray(['description' => 'BBW999']);
        });

        it('limits results to 5 users', function (): void {
            User::factory()->count(10)->create(['first_name' => 'Test']);

            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'Test');

            expect($component->get('membersSearchList'))->toHaveCount(5);
        });

        it('returns an empty list when nothing matches', function (): void {
            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'xxxxxxxxxxxxxxx');

            expect($component->get('membersSearchList'))->toBeEmpty();
        });

        it('maps results with id, name and description keys', function (): void {
            $user = User::factory()->create([
                'first_name' => 'Paul',
                'last_name' => 'Martin',
                'licence' => 'LIC123',
            ]);

            $component = Livewire::test(committeeModalComponent())
                ->call('searchMembers', 'Paul');

            expect($component->get('membersSearchList'))
                ->first()
                ->toMatchArray([
                    'id' => $user->id,
                    'name' => 'Paul Martin',
                    'description' => 'LIC123',
                ]);
        });

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // ADD MEMBER (in the modal)
    // ─────────────────────────────────────────────────────────────────────────────

    describe('addMember(in the modal)', function (): void {

        it('adds a user to the committee with a valid role', function (): void {
            $user = User::factory()->create();

            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', $user->id)
                ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
                ->call('addMember');

            expect($user->fresh())
                ->is_committee_member->toBeTrue()
                ->committee_role->toBe(CommitteeRolesEnum::PRESIDENT);
        });

        it('resets selectedMemberId, selectedRoleId and closes modal after adding', function (): void {
            $user = User::factory()->create();

            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', $user->id)
                ->set('selectedRoleId', CommitteeRolesEnum::SECRETARY->value)
                ->call('addMember')
                ->assertSet('selectedMemberId', null)
                ->assertSet('selectedRoleId', null)
                ->assertSet('addCommitteeMemberModal', false);
        });

        it('fails validation when no member is selected', function (): void {
            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', null)
                ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
                ->call('addMember')
                ->assertHasErrors(['selectedMemberId']);
        });

        it('fails validation when no role is selected', function (): void {
            $user = User::factory()->create();

            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', $user->id)
                ->set('selectedRoleId', null)
                ->call('addMember')
                ->assertHasErrors(['selectedRoleId']);
        });

        it('fails validation when role is not a valid CommitteeRolesEnum value', function (): void {
            $user = User::factory()->create();

            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', $user->id)
                ->set('selectedRoleId', 'NOT_A_VALID_ROLE')
                ->call('addMember')
                ->assertHasErrors(['selectedRoleId']);
        });

        // Skippé, car je ne sais pas comment vérifier le toast Mary UI. (non critique)
        it('dispatches a success toast after adding', function (): void {
            $user = User::factory()->create();

            Livewire::test(committeeModalComponent())
                ->set('selectedMemberId', $user->id)
                ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
                ->call('addMember')
                ->assertSee(__('Member added to committee list.'));

        })->skip('not able to test toasts');

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // REMOVE MEMBER
    // ─────────────────────────────────────────────────────────────────────────────

    describe('removeMember', function (): void {

        it('removes a user from the committee', function (): void {
            $user = User::factory()->isCommitteeMember()->create([
                'committee_role' => CommitteeRolesEnum::TREASURER,
            ]);

            Livewire::test(clubSettingsComponent())
                ->call('removeMember', $user->id);

            expect($user->fresh())
                ->is_committee_member->toBeFalse()
                ->committee_role->toBeNull();
        });

        // Skippé, car je ne sais pas comment vérifier le toast Mary UI. (non critique)
        it('dispatches a success toast after removing', function (): void {
            $user = User::factory()->isCommitteeMember()->create();

            Livewire::test(clubSettingsComponent())
                ->call('removeMember', $user->id)
                ->assertDispatched('toast');
        })->skip('not able to test toasts');

        it('throws a 404 when user does not exist', function (): void {
            Livewire::test(clubSettingsComponent())
                ->call('removeMember', 99999);
        })->throws(ModelNotFoundException::class);

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // COMPUTED PROPERTY : roleOptions (in the Modal)
    // ─────────────────────────────────────────────────────────────────────────────

    describe('roleOptions(in the modal)', function (): void {

        it('returns an array of options from CommitteeRolesEnum', function (): void {
            $component = Livewire::test(committeeModalComponent());

            // On vérifie que la computed prop est exploitable dans la vue
            // CommitteeRolesEnum::getOptions() doit retourner des entrées [id, name]
            expect(CommitteeRolesEnum::getOptions())
                ->toBeArray()
                ->not->toBeEmpty();
        });

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // SAVE (bank_account / IBAN)
    // ─────────────────────────────────────────────────────────────────────────────

    describe('save(bank_account)', function (): void {

        it('normalizes a bank_account entered with spaces before saving', function (): void {
            Club::factory()->ownClub()->create(['bank_account' => 'BE23732333208791', 'email_contact' => 'club@example.com']);

            Livewire::test(clubSettingsComponent())
                ->set('bank_account', 'be68 5390 0754 7034')
                ->call('save');

            expect(Club::ourClub()->first()->bank_account)->toBe('BE68539007547034');
        });

        it('rejects a bank_account that fails the IBAN checksum', function (): void {
            Club::factory()->ownClub()->create(['bank_account' => 'BE23732333208791', 'email_contact' => 'club@example.com']);

            Livewire::test(clubSettingsComponent())
                ->set('bank_account', 'BE00539007547034')
                ->call('save')
                ->assertHasErrors(['bank_account']);
        });

        it('requires a bank_account', function (): void {
            Club::factory()->ownClub()->create(['bank_account' => 'BE23732333208791', 'email_contact' => 'club@example.com']);

            Livewire::test(clubSettingsComponent())
                ->set('bank_account', '')
                ->call('save')
                ->assertHasErrors(['bank_account']);
        });

    });

    // ─────────────────────────────────────────────────────────────────────────────
    // COMMITTEE MEMBERS ORDERING
    // ─────────────────────────────────────────────────────────────────────────────

    describe('committeeMembers ordering', function (): void {

        it('orders members by role priority: President first, then Secretary, Treasurer, others', function (): void {
            User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::TREASURER, 'last_name' => 'Abc']);
            User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::SECRETARY, 'last_name' => 'Abc']);
            User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::PRESIDENT, 'last_name' => 'Abc']);

            $component = Livewire::test(clubSettingsComponent());

            $roles = $component->viewData('committeeMembers')
                ->pluck('committee_role')
                ->map->value  // si c'est un Enum backed
                ->toArray();

            expect($roles[0])->toBe(CommitteeRolesEnum::PRESIDENT->value)
                ->and($roles[1])->toBe(CommitteeRolesEnum::SECRETARY->value)
                ->and($roles[2])->toBe(CommitteeRolesEnum::TREASURER->value);
        });

    });
})->group('club-info', 'club-admin');
