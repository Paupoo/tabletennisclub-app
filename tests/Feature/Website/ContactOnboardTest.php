<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\Role;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
    Mail::fake();
});

test('admin can onboard a JOIN_US contact as a user', function (): void {
    $contact = Contact::factory()->create([
        'first_name' => 'Sophie',
        'last_name' => 'Bernard',
        'email' => 'sophie.bernard@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    $user = User::where('email', 'sophie.bernard@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Sophie')
        ->and($user->last_name)->toBe('Bernard');
});

test('onboarding marks contact as processed', function (): void {
    $contact = Contact::factory()->create([
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    expect($contact->fresh()->status)->toBe('processed');
});

test('onboarding sends invitation email to new user', function (): void {
    $contact = Contact::factory()->create([
        'email' => 'test.onboard@club.com',
        'interest' => ContactReasonEnum::JOIN_US,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo('test.onboard@club.com'));
});

test('a managing committee member (secretary) can onboard a contact', function (): void {
    $secretary = User::factory()->isCommitteeMember()->withRole(Role::CONTACTS)->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);
    $contact = Contact::factory()->create([
        'interest' => ContactReasonEnum::TRIAL,
        'status' => 'new',
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    expect(User::where('email', $contact->email)->exists())->toBeTrue();
});

test('a non-managing committee member cannot onboard a contact', function (): void {
    // No contacts delegation on purpose — that is the whole point of the test.
    $committee = $this->createFakeCommitteeMemberWithoutDelegation();
    $contact = Contact::factory()->create([
        'interest' => ContactReasonEnum::TRIAL,
        'status' => 'new',
    ]);

    Livewire::actingAs($committee)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id)
        ->assertForbidden();

    expect(User::where('email', $contact->email)->exists())->toBeFalse();
});

test('onboarding a contact whose email matches an existing user links it instead of duplicating', function (): void {
    $existingUser = User::factory()->create(['email' => 'existing.member@example.com']);

    $contact = Contact::factory()->create([
        'email' => 'existing.member@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id)
        ->call('linkToExistingUser');

    expect(User::where('email', 'existing.member@example.com')->count())->toBe(1);

    $contact->refresh();
    expect($contact->status)->toBe('processed')
        ->and($contact->user_id)->toBe($existingUser->id);

    Mail::assertNothingQueued();
});

test('cancelling the link confirmation leaves the contact unprocessed', function (): void {
    $existingUser = User::factory()->create(['email' => 'shared.family@example.com']);

    $contact = Contact::factory()->create([
        'email' => 'shared.family@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id)
        ->call('cancelLink');

    $contact->refresh();
    expect($contact->status)->toBe('new')
        ->and($contact->user_id)->toBeNull();

    expect(User::where('email', 'shared.family@example.com')->count())->toBe(1)
        ->and($existingUser->id)->not->toBeNull();
});

test('email matching for linking is case-insensitive', function (): void {
    $existingUser = User::factory()->create(['email' => 'Marie.Dupont@example.com']);

    $contact = Contact::factory()->create([
        'email' => 'marie.dupont@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id)
        ->call('linkToExistingUser');

    $contact->refresh();
    expect($contact->status)->toBe('processed')
        ->and($contact->user_id)->toBe($existingUser->id);

    expect(User::count())->toBe(2);
});

test('onboarding a contact matching a former (soft-deleted) member is blocked, not auto-linked', function (): void {
    $formerMember = User::factory()->create(['email' => 'former.member@example.com']);
    $formerMember->delete();

    $contact = Contact::factory()->create([
        'email' => 'former.member@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    $contact->refresh();
    expect($contact->status)->toBe('new')
        ->and($contact->user_id)->toBeNull();

    expect(User::withTrashed()->where('email', 'former.member@example.com')->count())->toBe(1);
});

test('contacts list flags a non-processed contact whose email already matches a member', function (): void {
    User::factory()->create(['email' => 'already.member@example.com']);

    Contact::factory()->create([
        'first_name' => 'Already',
        'last_name' => 'Member',
        'email' => 'already.member@example.com',
        'interest' => ContactReasonEnum::JOIN_US,
        'status' => 'new',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::website.contacts.index')
        ->assertSee(__('Already a member'));
});
