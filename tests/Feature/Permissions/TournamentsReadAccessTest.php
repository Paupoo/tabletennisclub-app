<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

pest()->group('tournaments', 'permissions');

/*
| Tournaments, read by the committee.
|
| The list opens at `tournaments.view`; the wizard, the live centre and the
| printouts are the organiser's working tools and stay with the délégation.
| A reader follows a running or finished tournament on its live page.
*/

beforeEach(function (): void {
    makeActiveSeason();
    $this->tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::TOURNAMENTS)->create();
});

it('opens the list and the live page to a reader', function (): void {
    $this->actingAs($this->reader)->get(route('admin.tournaments.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('admin.tournaments.live', $this->tournament))->assertOk();
});

it('keeps the organiser\'s tools with the délégation', function (string $routeName): void {
    $this->actingAs($this->reader)->get(route($routeName, $this->tournament))->assertForbidden();
})->with([
    'admin.tournaments.wizard.edit',
    'admin.tournaments.live-center',
    'admin.tournaments.print.pools',
    'admin.tournaments.print.matches',
]);

it('lists the tournaments for a reader, leading to the live page and to no tool', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.tournaments.index')
        ->assertSee($this->tournament->name)
        ->assertSee(route('admin.tournaments.live', $this->tournament))
        ->assertDontSee(route('admin.tournaments.wizard.edit', [$this->tournament, 'step' => 1]))
        ->assertDontSee(route('admin.tournaments.live-center', $this->tournament->id))
        ->assertDontSee(route('admin.tournaments.wizard'));
});

it('refuses a reader the bulk cancellation', function (string $method): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.tournaments.index')
        ->set('selected', [$this->tournament->id])
        ->call($method)
        ->assertForbidden();

    expect($this->tournament->fresh()->status)->toBe(TournamentStatusEnum::PENDING);
})->with(['confirmBulkCancel', 'bulkCancel']);
