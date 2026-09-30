<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

/*
 * The minutes' notes are written in <x-markdown-editor>. The screen autosaves
 * on Livewire's `updated`, which the editor only reaches when the author
 * leaves the field (commit-on-blur); and while someone else holds the pen,
 * the editor is read-only — a disabled fieldset does not stop contenteditable.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->actingAs($this->admin);
});

it('saves the notes when the author leaves the editor, and takes the pen', function (): void {
    $meeting = Meeting::factory()->confirmed()->create();

    $page = visit(route('admin.meetings.minutes', $meeting))->wait(1);
    $page->script("document.querySelector('.markdown-editor-surface').focus()");
    $page->keys('.markdown-editor-surface', ['P', 'r', 'o', 'c', 'h', 'a', 'i', 'n', 'e']);
    $page->script("document.querySelector('.markdown-editor-surface').blur()");
    $page->wait(1.5);

    $meeting->refresh();

    expect($meeting->minutes?->notes)->toBe('Prochaine')
        ->and($meeting->minutes_editor_id)->toBe($this->admin->id);
    $page->assertNoJavaScriptErrors();
});

it('shows the notes read-only while another member holds the pen', function (): void {
    $holder = User::factory()->isAdmin()->isCommitteeMember()->create();
    $meeting = Meeting::factory()->confirmed()->create();
    $meeting->minutes()->create(['notes' => 'Notes de **Julie**']);
    $meeting->acquireMinutesLock($holder);

    visit(route('admin.meetings.minutes', $meeting))->wait(1)
        ->assertPresent('.markdown-editor-surface[contenteditable="false"]')
        ->assertSeeIn('.markdown-editor-surface', 'Julie')
        ->assertNoJavaScriptErrors();
});
