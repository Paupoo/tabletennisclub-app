<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
| Transparency (decided 2026-10-05): every committee seat reads the club record —
| identity, bank account, financial year, interclub schedule — and only whoever
| may update the club changes it.
*/

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['name' => 'CTT Ottignies-Blocry', 'email_contact' => 'info@cttob.be']);
});

it('opens the club record to the committee and to whoever updates it, and nobody else', function (): void {
    $this->actingAs(User::factory()->isCommitteeMember()->create())
        ->get(route('admin.club-info'))
        ->assertOk();

    $this->actingAs(User::factory()->withRole(Role::SUPERVISION)->create())
        ->get(route('admin.club-info'))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.club-info'))
        ->assertForbidden();
});

it('refuses a committee member who sends a save by hand, and changes nothing', function (): void {
    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::club-admin.club-info')
        ->set('name', 'Renamed')
        ->call('save')
        ->assertForbidden();

    expect(Club::own()->fresh()->name)->toBe('CTT Ottignies-Blocry');
});

it('shows the committee the fields locked and no save button', function (): void {
    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::club-admin.club-info')
        ->assertSet('name', 'CTT Ottignies-Blocry')
        ->assertSeeHtml('<fieldset disabled')
        ->assertDontSee(__('Save Changes'));
});

it('leaves the form open to whoever updates the club', function (): void {
    Livewire::actingAs(User::factory()->withRole(Role::SUPERVISION)->create())
        ->test('pages::club-admin.club-info')
        ->assertDontSeeHtml('<fieldset disabled')
        ->assertSee(__('Save Changes'))
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect(Club::own()->fresh()->name)->toBe('Renamed');
});
