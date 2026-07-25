<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('club-admin', 'users', 'permissions');

/*
| The member screens are readable at the committee baseline (`users.view`,
| `subscriptions.view`) but every mutation belongs to the `membres` délégation.
| The buttons used to be rendered unconditionally, so a read-only viewer was
| offered actions that answered with a 403. What the page offers must match what
| the viewer may actually do.
*/

beforeEach(function (): void {
    makeActiveSeason();

    // Unverified so the "Resend invitation" button is rendered at all.
    $this->target = User::factory()->unverified()->create();

    $this->readOnly = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::MEMBERS)->create();
});

describe('the members list', function (): void {
    it('offers neither edit nor invitation to a read-only viewer', function (): void {
        Livewire::actingAs($this->readOnly)
            ->test('pages::club-admin.users.index')
            // Present in the list, just not actionable.
            ->assertSee($this->target->last_name)
            // Mobile row action, desktop row action and the desktop name link all
            // point at this URL — asserting on it covers the three of them.
            ->assertDontSee(route('admin.users.edit', $this->target))
            ->assertDontSee('sendInvitation(' . $this->target->id . ')')
            ->assertDontSee(route('admin.users.create'))
            ->assertDontSee('quickInvite');
    });

    it('still offers both to the members delegate', function (): void {
        Livewire::actingAs($this->delegate)
            ->test('pages::club-admin.users.index')
            ->assertSee(route('admin.users.edit', $this->target))
            ->assertSee('sendInvitation(' . $this->target->id . ')')
            ->assertSee(route('admin.users.create'))
            ->assertSee('quickInvite');
    });
});

describe('the registrations screen', function (): void {
    it('offers no mutation to a read-only viewer', function (): void {
        Livewire::actingAs($this->readOnly)
            ->test('pages::club-admin.users.registrations')
            ->assertDontSee('toggleRegistrations')
            ->assertDontSee('wire:click="approve"')
            ->assertDontSee('wire:click="reject"');
    });

    it('still offers them to the members delegate', function (): void {
        Livewire::actingAs($this->delegate)
            ->test('pages::club-admin.users.registrations')
            ->assertSee('toggleRegistrations');
    });
});

describe('the server refuses what the buttons no longer offer', function (): void {
    it('refuses to send an invitation without the invite right', function (): void {
        Livewire::actingAs($this->readOnly)
            ->test('pages::club-admin.users.index')
            ->call('sendInvitation', $this->target->id)
            ->assertForbidden();
    });

    it('refuses a quick invite without the create right', function (): void {
        Livewire::actingAs($this->readOnly)
            ->test('pages::club-admin.users.index')
            ->set('inviteFirstName', 'Marie')
            ->set('inviteLastName', 'Dupont')
            ->set('inviteEmail', 'marie.dupont@example.test')
            ->call('quickInvite')
            ->assertForbidden();
    });

    it('lets the members delegate send an invitation', function (): void {
        Livewire::actingAs($this->delegate)
            ->test('pages::club-admin.users.index')
            ->call('sendInvitation', $this->target->id)
            ->assertOk();
    });
});
