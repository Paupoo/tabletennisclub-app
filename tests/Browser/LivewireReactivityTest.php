<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

/*
 * Dans ce plugin, `waitForText()` est un alias de `assertSee()` : il photographie
 * le DOM et ne réessaie pas. Aucune assertion de cette API n'attend. Tout ce qui
 * suit une frappe dans un champ `wire:model.live.debounce.300ms`, ou l'ouverture
 * d'un tiroir, doit donc passer par `wait()` — le seul primitif d'attente réel.
 * Constaté quand la version « liste des membres » de ce motif a lâché en CI.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'first_name' => 'Admin',
        'last_name' => 'Test',
    ]);
    makeActiveSeason();
});

it('filter drawer opens on meetings list', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->click('Filtres')
        ->wait(1)
        ->assertSee('Effacer les filtres');
});

it('search filters the meetings list in real time', function (): void {
    Meeting::factory()->create(['title' => 'Réunion Alpha Unique 4Def']);
    Meeting::factory()->create(['title' => 'Réunion Beta Unique 5Ghi']);

    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->assertSee('Alpha Unique 4Def')
        ->assertSee('Beta Unique 5Ghi')
        ->type('input[id$="search"]', 'Alpha')
        ->wait(2)
        ->assertSee('Alpha Unique 4Def')
        ->assertDontSee('Beta Unique 5Ghi');
});

it('empty state message shown when search matches nothing', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->type('input[id$="search"]', 'xqzxqzxqznoMatchExpected999')
        ->wait(2)
        ->assertSee('Aucune réunion ne correspond');
});

it('users index search updates list reactively', function (): void {
    User::factory()->create(['first_name' => 'ReactTest', 'last_name' => 'Livewire']);

    $this->actingAs($this->admin);

    visit(route('admin.users.index'))
        ->assertSee('ReactTest')
        ->type('input[id$="search"]', 'ReactTest')
        ->wait(2)
        ->assertSee('ReactTest')
        ->assertDontSee('Admin Test');
});
