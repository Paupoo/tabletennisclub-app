<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;

/*
 * The details of an agenda point are written in a compact <x-markdown-editor>.
 * The draft is an array keyed by position: removing a point shifts the ones
 * after it, and each editor, wire:ignore'd, must follow the point that now
 * sits at its index rather than keep the text of the one that left.
 */
it('keeps each point\'s details with its point when an earlier one is removed', function (): void {
    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());
    $meeting = Meeting::factory()->confirmed()->create();
    MeetingAgendaItem::factory()->for($meeting)->create(['title' => 'Budget', 'description' => 'Premier point', 'sort_order' => 0]);
    MeetingAgendaItem::factory()->for($meeting)->create(['title' => 'Tournoi', 'description' => 'Second point', 'sort_order' => 1]);

    $page = visit(route('admin.meetings.show', $meeting))->wait(1);
    $page->script(<<<'JS'
        (async () => {
          await Livewire.all().find((c) => c.ephemeral && 'agendaDraft' in c.ephemeral).$wire.editAgenda();
        })()
    JS);
    $page->wait(1.5);

    $page->script(<<<'JS'
        (() => {
          const second = document.querySelectorAll('.markdown-editor-surface')[1];
          second.focus();
          const end = document.createRange();
          end.selectNodeContents(second);
          end.collapse(false);
          getSelection().removeAllRanges();
          getSelection().addRange(end);
        })()
    JS);
    $page->keys('.markdown-editor-surface >> nth=1', [' ', 'o', 'k']);

    $page->script(<<<'JS'
        (async () => {
          await Livewire.all().find((c) => c.ephemeral && 'agendaDraft' in c.ephemeral).$wire.removeAgendaDraftItem(0);
        })()
    JS);
    $page->wait(1);

    $surfaces = $page->script("[...document.querySelectorAll('.markdown-editor-surface')].map((s) => s.textContent.trim())");
    $surfaces = is_array($surfaces[0] ?? null) ? $surfaces[0] : $surfaces;

    expect($surfaces)->toBe(['Second point ok']);

    $page->press('Enregistrer')->wait(1);

    expect($meeting->agendaItems()->pluck('description')->all())->toBe(['Second point ok']);
    $page->assertNoJavaScriptErrors();
});
