<?php

declare(strict_types=1);

use App\Actions\User\SyncFamilyGroupMembersAction;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'family');

const FAMILY_FORM_COMPONENT = 'pages::club-admin.users.form';

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'is_coach' => false]);
    actingAs($this->admin);
});

describe('family management from the form', function () {
    it('links an existing member to the family and persists it on save', function () {
        $user = User::factory()->create();
        $sibling = User::factory()->create(['first_name' => 'Léo', 'last_name' => 'Martin']);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $sibling->id)
            ->assertSet('familyMemberIds', [$sibling->id])
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh()->familyMembers()->pluck('id')->all())->toBe([$sibling->id]);
    });

    it('detaches a linked family member', function () {
        $user = User::factory()->create();
        $sibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('detachFamilyMember', $sibling->id)
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save');

        expect($user->fresh()->familyMembers())->toBeEmpty();
    });

    it('loads existing family links on mount', function () {
        $user = User::factory()->create();
        $sibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->assertSet('familyMemberIds', [$sibling->id]);
    });

    it('refuses to link a member already in another family and does not mutate the list', function () {
        $user = User::factory()->create();
        $alreadyLinked = User::factory()->create();
        $otherMember = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($otherMember, [$alreadyLinked->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $alreadyLinked->id)
            ->assertSet('familyMemberIds', []);
    });

    it('finds a club member by name in the family search', function () {
        $user = User::factory()->create();
        User::factory()->create(['first_name' => 'Isabelle', 'last_name' => 'Renard']);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->set('familySearch', 'Renard')
            ->assertSee('Isabelle');
    });

    it('does not exclude minors from the family search', function () {
        $user = User::factory()->create();
        User::factory()->create([
            'first_name' => 'Timéo',
            'last_name' => 'Junior',
            'birthdate' => now()->subYears(10),
        ]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->set('familySearch', 'Junior')
            ->assertSee('Timéo');
    });
});
