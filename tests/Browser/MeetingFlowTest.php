<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

it('meetings list page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Réunions');
});

it('existing meeting appears in the meetings list', function (): void {
    Meeting::factory()->create([
        'title' => 'Réunion de test unique 7XkZ',
        'type' => MeetingTypeEnum::COMMITTEE,
        'status' => MeetingStatusEnum::CONFIRMED,
    ]);
    $this->actingAs($this->admin);

    visit(route('admin.meetings.index'))
        ->assertSee('Réunion de test unique 7XkZ');
});

it('meeting create form is accessible and shows steps', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.meetings.create'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Nouvelle réunion');
});

it('meeting show page loads for a confirmed meeting', function (): void {
    $meeting = Meeting::factory()->confirmed()->create(['title' => 'AG Test 8Pqr']);
    $this->actingAs($this->admin);

    visit(route('admin.meetings.show', $meeting))
        ->assertNoJavaScriptErrors()
        ->assertSee('AG Test 8Pqr');
});
