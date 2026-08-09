<?php

declare(strict_types=1);

use App\Actions\User\SyncFamilyGroupMembersAction;
use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'family');

const FAMILY_FORM_COMPONENT = 'pages::club-admin.users.form';

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);
});

describe('family management from the form', function (): void {
    it('links an existing member to the family and persists it on save', function (): void {
        $user = User::factory()->create();
        $sibling = User::factory()->create(['first_name' => 'Léo', 'last_name' => 'Martin']);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $sibling->id)
            ->assertSet('familyMemberIds', [$sibling->id])
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh()->familyMembers()->pluck('id')->all())->toBe([$sibling->id]);
    });

    it('detaches a linked family member', function (): void {
        $user = User::factory()->create();
        $sibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('detachFamilyMember', $sibling->id)
            ->set('password', '')
            ->call('save');

        expect($user->fresh()->familyMembers())->toBeEmpty();
    });

    it('loads existing family links on mount', function (): void {
        $user = User::factory()->create();
        $sibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->assertSet('familyMemberIds', [$sibling->id]);
    });

    it('joins the existing family when linking a member who already has one', function (): void {
        $user = User::factory()->create();
        $parent = User::factory()->create();
        $child = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($parent, [$child->id]);
        $groupId = $parent->fresh()->familyGroups()->first()->id;

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $parent->id)
            ->assertSet('familyMemberIds', [$parent->id, $child->id])
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh()->familyGroups()->first()->id)->toBe($groupId)
            ->and(FamilyGroup::count())->toBe(1)
            ->and($user->fresh()->familyMembers()->pluck('id')->sort()->values()->all())
            ->toBe(collect([$parent->id, $child->id])->sort()->values()->all());
    });

    it('refuses to link a member from another family when the user already has one', function (): void {
        $user = User::factory()->create();
        $sibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

        $stranger = User::factory()->create();
        $strangerSibling = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($stranger, [$strangerSibling->id]);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $stranger->id)
            ->assertSet('familyMemberIds', [$sibling->id]);
    });

    it('refuses to mix members from two different families on a user without one', function (): void {
        $user = User::factory()->create();

        $parentA = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($parentA, [User::factory()->create()->id]);

        $parentB = User::factory()->create();
        SyncFamilyGroupMembersAction::handle($parentB, [User::factory()->create()->id]);

        $component = Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->call('attachFamilyMember', $parentA->id)
            ->call('attachFamilyMember', $parentB->id);

        expect($component->get('familyMemberIds'))->not->toContain($parentB->id);
    });

    it('finds a club member by name in the family search', function (): void {
        $user = User::factory()->create();
        User::factory()->create(['first_name' => 'Isabelle', 'last_name' => 'Renard']);

        Livewire::test(FAMILY_FORM_COMPONENT, ['user' => $user])
            ->set('familySearch', 'Renard')
            ->assertSee('Isabelle');
    });

    it('does not exclude minors from the family search', function (): void {
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
