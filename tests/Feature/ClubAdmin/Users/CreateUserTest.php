<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->password = Hash::make('password');
    $this->existingUser = User::factory()->create([
        'email' => 'aurelien.paulus@gmail.com',
        'licence' => '999888',
    ]);
    Club::factory()->ownClub()->create();
});

test('create method returning expected view and data', function (): void {
    $admin = $this->createFakeAdmin();

    $response = $this->actingAs($admin)
        ->get(route('admin.users.create'));

    $response->assertOk();
});

test('new nember created is automatically linked to the club', function (): void {
    $user = User::factory()->create();
    $club = Club::own();
    expect($user->club_id)->toEqual($club->id);
});

test('quick invite creates user with minimal data and sends invitation', function (): void {
    Mail::fake();

    $admin = $this->createFakeAdmin();

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->set('inviteFirstName', 'Sophie')
        ->set('inviteLastName', 'Bernard')
        ->set('inviteEmail', 'sophie.bernard@example.com')
        ->call('quickInvite');

    expect(User::where('email', 'sophie.bernard@example.com')->exists())->toBeTrue();
    Mail::assertQueued(InviteNewUserMail::class);
});

test('quick invite fails when email already taken', function (): void {
    Mail::fake();

    $admin = $this->createFakeAdmin();

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->set('inviteFirstName', 'Jean')
        ->set('inviteLastName', 'Dupont')
        ->set('inviteEmail', 'aurelien.paulus@gmail.com') // already exists
        ->call('quickInvite')
        ->assertHasErrors(['inviteEmail']);

    Mail::assertNotQueued(InviteNewUserMail::class);
});

test('full form save fails with a validation error when the email is already taken', function (): void {
    $admin = $this->createFakeAdmin();

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.form')
        ->set('first_name', 'Hugo')
        ->set('last_name', 'Van Oudenhove')
        ->set('email', 'aurelien.paulus@gmail.com') // already used by $this->existingUser
        ->set('gender', 'MEN')
        ->set('phone_number', '0485610204')
        ->set('street', 'Du Bauloy')
        ->set('city_code', '1348')
        ->set('city_name', 'Ottignies')
        ->set('licence_type', 'recreative')
        ->set('password', 'Password1!')
        ->set('password_confirmation', 'Password1!')
        ->call('save')
        ->assertHasErrors(['email']);

    // No second user with that email — the DB unique constraint was never reached.
    expect(User::where('email', 'aurelien.paulus@gmail.com')->count())->toBe(1);
});

test('editing a user keeps its own email without a uniqueness error', function (): void {
    $admin = $this->createFakeAdmin();

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.form', ['user' => $this->existingUser])
        ->set('password', '')
        ->call('save')
        ->assertHasNoErrors(['email']);
});
