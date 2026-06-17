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
