<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('meetings', 'permissions');

/*
| Meetings, read by the committee.
|
| The whole group answered to `meetings.view`, the create form included — and
| its save checked nothing, so any committee member could convene a meeting.
| Reading stays open; convening is the meetings délégation.
*/

beforeEach(function (): void {
    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::MEETINGS)->create();
});

it('keeps the create form with the meetings délégation', function (): void {
    $this->actingAs($this->reader)->get(route('admin.meetings.create'))->assertForbidden();
    $this->actingAs($this->delegate)->get(route('admin.meetings.create'))->assertOk();
});

it('refuses to save a meeting for a reader', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.meetings.create')
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});

it('does not let a reader publish a meeting on the website', function (): void {
    $meeting = Meeting::factory()->committee()->confirmed()->physical()->create();

    Livewire::actingAs($this->reader)
        ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
        ->assertDontSeeLivewire('admin.shared.event-post-button');
});
