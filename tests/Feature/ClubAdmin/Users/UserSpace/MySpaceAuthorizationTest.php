<?php

declare(strict_types=1);

use App\Actions\User\SyncFamilyGroupMembersAction;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/*
|--------------------------------------------------------------------------
| My-space authorization
|--------------------------------------------------------------------------
|
| The my-space pages are strictly self-only: even admins and committee
| members must use the admin forms (admin.users.edit) to act on other
| members. Any cross-user access must be rejected with a 403.
|
*/

$mySpaceRoutes = [
    'admin.user.profile',
    'admin.user.settings',
    'admin.user.teams',
    'admin.user.calendar',
    'admin.user.event-subscription',
    'admin.user.registration-management',
    'admin.user.reglement',
    'admin.user.charter',
    'admin.user.directory',
    'admin.user.payments',
];

// An affiliated member: the directory is the one my-space page that also
// demands membership, not merely a login (see DirectoryTest).
test('a member can access their own my-space pages', function (string $routeName): void {
    $user = activeMember(makeActiveSeason());

    $this->actingAs($user)
        ->get(route($routeName, $user))
        ->assertSuccessful();
})->with($mySpaceRoutes);

test('a member cannot access another members my-space pages', function (string $routeName): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName, $other))
        ->assertForbidden();
})->with($mySpaceRoutes);

test('an admin cannot access another members my-space pages', function (string $routeName): void {
    $admin = $this->createFakeAdmin();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->get(route($routeName, $other))
        ->assertForbidden();
})->with($mySpaceRoutes);

test('a member cannot change another members password via settings', function (): void {
    $attacker = User::factory()->create();
    $victim = User::factory()->create(['password' => 'victim-secret-1']);

    Livewire::actingAs($attacker)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $victim])
        ->assertForbidden();

    expect(Hash::check('victim-secret-1', $victim->fresh()->password))->toBeTrue();
});

test('a member with no linked family member sees a message to contact the committee', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__('To add a family member, please contact the committee.'));
});

test('a member with a linked family member sees their tab and no contact message', function (): void {
    $user = User::factory()->create();
    $sibling = User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Petit']);
    SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee('Camille')
        ->assertDontSee(__('To add a family member, please contact the committee.'));
});

test('a member can change their own password via settings', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $user])
        ->set('password', 'CttBlocry#2026!vK9')
        ->set('password_confirmation', 'CttBlocry#2026!vK9')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('CttBlocry#2026!vK9', $user->fresh()->password))->toBeTrue();
});
