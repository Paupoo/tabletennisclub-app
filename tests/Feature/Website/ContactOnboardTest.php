<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ContactReasonEnum;
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

test('committee member can also onboard a contact', function (): void {
    $committee = $this->createFakeCommitteeMember();
    $contact = Contact::factory()->create([
        'interest' => ContactReasonEnum::TRIAL,
        'status' => 'new',
    ]);

    Livewire::actingAs($committee)
        ->test('pages::website.contacts.index')
        ->call('onboardContact', $contact->id);

    expect(User::where('email', $contact->email)->exists())->toBeTrue();
});
