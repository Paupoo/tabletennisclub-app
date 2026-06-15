<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;

/*
|--------------------------------------------------------------------------
| Critical pages browser smoke test
|--------------------------------------------------------------------------
|
| Loads the most JS/Alpine-heavy pages in a real browser and asserts there are
| no JavaScript errors or console logs. Complements the HTTP smoke tests, which
| only catch server-side (500) failures.
|
| One representative page per interactive category:
|   - score entry        (tournament.table.score)
|   - tournament wizard   (admin.tournaments.wizard)
|   - tournament live     (admin.tournaments.live-center)
|   - admin index/table   (admin.users.index)
|   - creation form       (admin.meetings.create)
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()
        ->isAdmin()
        ->isCommitteeMember()
        ->create([]);

    makeActiveSeason();
});

it('loads the admin users index without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.index'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the meeting creation form without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.create'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the contacts inbox without JS errors', function (): void {
    $this->actingAs($this->admin);

    Contact::factory()->withProfile()->create();

    visit(route('admin.website.contacts.index'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the contact email templates manager without JS errors', function (): void {
    $this->actingAs($this->admin);

    EmailTemplate::factory()->create();

    visit(route('admin.website.contacts.email-templates'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the season roster without JS errors', function (): void {
    $this->actingAs($this->admin);

    $member = User::factory()->create(['birthdate' => now()->subYears(12)]);
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => Season::current()?->id,
        'status' => 'paid',
        'can_drive' => true,
        'seats_available' => 4,
    ]);

    visit(route('admin.subscriptions.roster'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the planning board without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.planning.board'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the tournament wizard without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.tournaments.wizard'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the tournament live-center without JS errors', function (): void {
    $this->actingAs($this->admin);
    $tournament = Tournament::factory()->create();

    visit(route('admin.tournaments.live-center', $tournament))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the tournament table score entry without JS errors', function (): void {
    $this->actingAs($this->admin);
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create();

    visit(route('tournament.table.score', [$tournament, $table]))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
