<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
    $this->committeeMember = $this->createFakeCommitteeMember();
});

test('admin can anonymize a user via modal confirmation', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@example.com',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('openAnonymizeModal', $user->id)
        ->set('anonymizeConfirmText', 'ANONYMIZE')
        ->call('confirmAnonymize');

    $user->refresh();

    expect($user->first_name)->toBe('Anonymized')
        ->and($user->email)->toBe("deleted-{$user->id}@anonymous.local")
        ->and($user->deleted_at)->not->toBeNull();
});

test('anonymize requires typing ANONYMIZE to confirm', function (): void {
    $user = User::factory()->create(['first_name' => 'Jean']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('openAnonymizeModal', $user->id)
        ->set('anonymizeConfirmText', 'wrong')
        ->call('confirmAnonymize');

    expect($user->fresh()->first_name)->not->toBe('Anonymized');
});

test('committee member cannot open anonymize modal', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($this->committeeMember)
        ->test('pages::club-admin.users.index')
        ->call('openAnonymizeModal', $user->id);

    expect($user->fresh()->first_name)->not->toBe('Anonymized');
});

test('anonymize is not available as bulk action', function (): void {
    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index');

    expect(method_exists($component->instance(), 'bulkAnonymize'))->toBeFalse();
});

test('bulk paid and bulk unpaid methods no longer exist', function (): void {
    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index');

    expect(method_exists($component->instance(), 'bulkPaid'))->toBeFalse()
        ->and(method_exists($component->instance(), 'bulkUnpaid'))->toBeFalse();
});
