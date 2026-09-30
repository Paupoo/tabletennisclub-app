<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

/*
 * Minutes are taken live, point by point: a decision is a line and Enter, and
 * ticking a point off opens the next one. Only a browser proves the keyboard
 * path and the page moving along with the meeting.
 */
it('records a decision on Enter and moves on to the next point once one is ticked off', function (): void {
    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());
    $meeting = Meeting::factory()->committee()->completed()->create();
    $budget = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget de la saison']);
    $tournament = $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'Tournoi de printemps']);

    $page = visit(route('admin.meetings.minutes', $meeting))->resize(1440, 1000)->wait(1);

    $page->assertVisible("#point-{$budget->id} input[aria-label=\"Nouvelle décision\"]")
        ->assertMissing("#point-{$tournament->id} input[aria-label=\"Nouvelle décision\"]")
        ->type("#point-{$budget->id} input[aria-label=\"Nouvelle décision\"]", 'Budget approuvé')
        ->keys("#point-{$budget->id} input[aria-label=\"Nouvelle décision\"]", ['Enter'])
        ->wait(1)
        ->assertSeeIn("#point-{$budget->id}", 'D1')
        ->assertSeeIn("#point-{$budget->id}", 'Budget approuvé');

    expect($meeting->decisions()->first()?->agenda_item_id)->toBe($budget->id);

    // Clicked by script: every point carries the same label, and an ambiguous
    // click leaves the browser suite hanging instead of failing.
    $page->script("document.querySelector('#point-{$budget->id} header > button:last-of-type').click()");
    $page->wait(1.5)
        ->assertVisible("#point-{$tournament->id} input[aria-label=\"Nouvelle décision\"]")
        ->assertMissing("#point-{$budget->id} input[aria-label=\"Nouvelle décision\"]")
        ->assertNoJavaScriptErrors();

    expect($budget->fresh()->discussed_at)->not->toBeNull();
});
