<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

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
        ->waitForText('Effacer les filtres')
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
        ->waitForText('Alpha Unique 4Def')
        ->assertDontSee('Beta Unique 5Ghi');
});

it('empty state message shown when search matches nothing', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->type('input[id$="search"]', 'xqzxqzxqznoMatchExpected999')
        ->waitForText('No meeting matches');
});

it('users index search updates list reactively', function (): void {
    User::factory()->create(['first_name' => 'ReactTest', 'last_name' => 'Livewire']);

    $this->actingAs($this->admin);

    visit(route('admin.users.index'))
        ->assertSee('ReactTest')
        ->type('input[id$="search"]', 'ReactTest')
        ->waitForText('ReactTest')
        ->assertDontSee('Admin Test');
});
