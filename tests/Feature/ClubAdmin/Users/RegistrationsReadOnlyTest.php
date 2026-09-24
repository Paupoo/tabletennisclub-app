<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('club-admin', 'registrations', 'permissions');

/*
| The affiliations screen, read by the committee.
|
| A committee member reads every request, but accepts none: the screen must not
| address them as the one who has to act — no "Review" call to action, no field
| they could fill in for an acceptance they cannot give.
*/

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    $this->request = Subscription::factory()->pending()->create([
        'user_id' => User::factory()->create()->id,
        'season_id' => $season->id,
    ]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::MEMBERS)->create();
});

it('invites a reader to consult a pending request, not to review it', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.users.registrations')
        ->assertSee(__('Consult'))
        ->assertDontSee(__('Review'));
});

it('still asks the members delegate to review it', function (): void {
    Livewire::actingAs($this->delegate)
        ->test('pages::club-admin.users.registrations')
        ->assertSee(__('Review'));
});

it('shows the request to a reader with its federation details locked', function (): void {
    $html = Livewire::actingAs($this->reader)
        ->test('pages::club-admin.users.registrations')
        ->call('review', $this->request->id)
        ->html();

    expect($html)
        ->toMatch('/<input[^>]*wire:model\.live\.debounce="reviewLicence"[^>]*disabled/s')
        ->toMatch('/<select[^>]*wire:model\.live="reviewRanking"[^>]*disabled/s')
        ->not->toContain(e(__('A licence number and a ranking are required to accept an affiliation. An unranked player is NC, not N/A.')))
        ->not->toContain(e(__('Add a rejection reason')));
});

it('leaves the federation details editable for the members delegate', function (): void {
    $html = Livewire::actingAs($this->delegate)
        ->test('pages::club-admin.users.registrations')
        ->call('review', $this->request->id)
        ->html();

    expect($html)
        ->not->toMatch('/<input[^>]*wire:model\.live\.debounce="reviewLicence"[^>]*disabled/s')
        ->toContain(e(__('Add a rejection reason')));
});
