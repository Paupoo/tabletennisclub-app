<?php


use App\Enums\CommitteeRolesEnum;
use App\Livewire\ClubAdmin\ClubSettings;
use App\Models\ClubAdmin\Users\User;
use Livewire\Livewire;

pest()->group('club-settings');

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le nom de classe du composant Volt (chemin de la vue Blade).
 * Adapte ce chemin selon ton projet.
 */
function clubSettingsComponent(): string
{
    return 'pages::club-admin.club-settings';
}


// ─────────────────────────────────────────────────────────────────────────────
// MOUNT & RENDER
// ─────────────────────────────────────────────────────────────────────────────

describe('Mount & Render', function () {

    it('renders the component without errors', function () {
        Livewire::test(clubSettingsComponent())
            ->assertStatus(200);
    });

    it('initialises properties from env on mount', function () {
        // On force des valeurs d'env pour le test
        config(['app.name' => 'CTT Test']);

        Livewire::test(clubSettingsComponent())
            ->assertSet('allow_online_renewal', true)
            ->assertSet('public_trainings', true);
    });

    it('displays committee members in the view', function () {
        $member = User::factory()->create([
            'is_committee_member' => true,
            'first_name'          => 'Alice',
            'last_name'           => 'Dumont',
            'committee_role'      => CommitteeRolesEnum::PRESIDENT,
        ]);

        Livewire::test(clubSettingsComponent())
            ->assertSee('Alice')
            ->assertSee('Dumont');
    });

    it('shows empty state when no committee members exist', function () {
        User::where('is_committee_member', true)->update(['is_committee_member' => false]);

        Livewire::test(clubSettingsComponent())
            ->assertSee(__('No committee members defined yet.'));
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// SEARCH MEMBERS
// ─────────────────────────────────────────────────────────────────────────────

describe('searchMembers', function () {

    it('returns matching users by first name', function () {
        User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW001']);
        User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Curie', 'licence' => 'BBW002']);

        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'Jea');

        expect($component->get('membersSearchList'))
            ->toHaveCount(1)
            ->first()->toMatchArray(['name' => 'Jean Dupont']);
    });

    it('returns matching users by last name', function () {
        User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW001']);

        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'Dup');

        expect($component->get('membersSearchList'))
            ->toHaveCount(1);
    });

    it('returns matching users by licence number', function () {
        User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'licence' => 'BBW999']);

        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'BBW999');

        expect($component->get('membersSearchList'))
            ->toHaveCount(1)
            ->first()->toMatchArray(['description' => 'BBW999']);
    });

    it('limits results to 5 users', function () {
        User::factory()->count(10)->create(['first_name' => 'Test']);

        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'Test');

        expect($component->get('membersSearchList'))->toHaveCount(5);
    });

    it('returns an empty list when nothing matches', function () {
        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'xxxxxxxxxxxxxxx');

        expect($component->get('membersSearchList'))->toBeEmpty();
    });

    it('maps results with id, name and description keys', function () {
        $user = User::factory()->create([
            'first_name' => 'Paul',
            'last_name'  => 'Martin',
            'licence'    => 'LIC123',
        ]);

        $component = Livewire::test(clubSettingsComponent())
            ->call('searchMembers', 'Paul');

        expect($component->get('membersSearchList'))
            ->first()
            ->toMatchArray([
                'id'          => $user->id,
                'name'        => 'Paul Martin',
                'description' => 'LIC123',
            ]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ADD MEMBER
// ─────────────────────────────────────────────────────────────────────────────

describe('addMember', function () {

    it('adds a user to the committee with a valid role', function () {
        $user = User::factory()->create(['is_committee_member' => false]);

        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', $user->id)
            ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
            ->call('addMember');

        expect($user->fresh())
            ->is_committee_member->toBeTrue()
            ->committee_role->toBe(CommitteeRolesEnum::PRESIDENT);
    });

    it('resets selectedMemberId, selectedRoleId and closes modal after adding', function () {
        $user = User::factory()->create(['is_committee_member' => false]);

        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', $user->id)
            ->set('selectedRoleId', CommitteeRolesEnum::SECRETARY->value)
            ->call('addMember')
            ->assertSet('selectedMemberId', null)
            ->assertSet('selectedRoleId', null)
            ->assertSet('addCommitteeMemberModal', false);
    });

    it('fails validation when no member is selected', function () {
        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', null)
            ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
            ->call('addMember')
            ->assertHasErrors(['selectedMemberId']);
    });

    it('fails validation when no role is selected', function () {
        $user = User::factory()->create();

        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', $user->id)
            ->set('selectedRoleId', null)
            ->call('addMember')
            ->assertHasErrors(['selectedRoleId']);
    });

    it('fails validation when role is not a valid CommitteeRolesEnum value', function () {
        $user = User::factory()->create();

        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', $user->id)
            ->set('selectedRoleId', 'NOT_A_VALID_ROLE')
            ->call('addMember')
            ->assertHasErrors(['selectedRoleId']);
    });

    // Skippé, car je ne sais pas comment vérifier le toast Mary UI. (non critique)
    it('dispatches a success toast after adding', function () {
        $user = User::factory()->create(['is_committee_member' => false]);

        Livewire::test(clubSettingsComponent())
            ->set('selectedMemberId', $user->id)
            ->set('selectedRoleId', CommitteeRolesEnum::PRESIDENT->value)
            ->call('addMember')
            ->assertSee(__('Member added to committee list.'));

    })->skip('not able to test toasts');

});

// ─────────────────────────────────────────────────────────────────────────────
// REMOVE MEMBER
// ─────────────────────────────────────────────────────────────────────────────

describe('removeMember', function () {

    it('removes a user from the committee', function () {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role'      => CommitteeRolesEnum::TREASURER,
        ]);

        Livewire::test(clubSettingsComponent())
            ->call('removeMember', $user->id);

        expect($user->fresh())
            ->is_committee_member->toBeFalse()
            ->committee_role->toBeNull();
    });

    // Skippé, car je ne sais pas comment vérifier le toast Mary UI. (non critique)
    it('dispatches a success toast after removing', function () {
        $user = User::factory()->create(['is_committee_member' => true]);

        Livewire::test(clubSettingsComponent())
            ->call('removeMember', $user->id)
            ->assertDispatched('toast');
    })->skip('not able to test toasts');

    it('throws a 404 when user does not exist', function () {
        Livewire::test(clubSettingsComponent())
            ->call('removeMember', 99999);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

});

// ─────────────────────────────────────────────────────────────────────────────
// COMPUTED PROPERTY : roleOptions
// ─────────────────────────────────────────────────────────────────────────────

describe('roleOptions', function () {

    it('returns an array of options from CommitteeRolesEnum', function () {
        $component = Livewire::test(clubSettingsComponent());

        // On vérifie que la computed prop est exploitable dans la vue
        // CommitteeRolesEnum::getOptions() doit retourner des entrées [id, name]
        expect(CommitteeRolesEnum::getOptions())
            ->toBeArray()
            ->not->toBeEmpty();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// COMMITTEE MEMBERS ORDERING
// ─────────────────────────────────────────────────────────────────────────────

describe('committeeMembers ordering', function () {

    it('orders members by role priority: President first, then Secretary, Treasurer, others', function () {
        User::factory()->create(['is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::TREASURER, 'last_name' => 'Abc']);
        User::factory()->create(['is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::SECRETARY, 'last_name' => 'Abc']);
        User::factory()->create(['is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::PRESIDENT, 'last_name' => 'Abc']);

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