<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

/*
 * <x-char-counter> reads the bound property through `$wire`, which a deferred
 * wire:model updates on every keystroke without a request. This types into the
 * cancellation note of a meeting and reads the counter back, because only a
 * browser proves the count follows the field.
 */
it('counts the characters of a deferred field as they are typed', function (): void {
    $meeting = Meeting::factory()->confirmed()->create();
    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());

    $page = visit(route('admin.meetings.show', $meeting));

    $page->script(<<<'JS'
        (async () => {
          const component = Livewire.all().find((c) => c.ephemeral && 'showCancelModal' in c.ephemeral);
          await component.$wire.$set('showCancelModal', true);
        })()
    JS);
    $page->wait(1);

    $result = $page->script(<<<'JS'
        (() => {
          const field = document.querySelector('textarea[wire\\:model="cancellationNote"]');
          field.value = 'Salle indisponible';
          field.dispatchEvent(new Event('input', { bubbles: true }));
          return new Promise((resolve) => setTimeout(() => resolve({
            maxlength: field.getAttribute('maxlength'),
            text: [...document.querySelectorAll('div[x-data]')].map((d) => d.textContent.replace(/\s+/g, ' ').trim()).find((t) => t.endsWith('/ 1000')),
          }), 200));
        })()
    JS);

    $r = $result[0] ?? $result;

    expect($r['maxlength'])->toBe('1000');
    expect($r['text'])->toBe('18 / 1000');
    $page->assertNoJavaScriptErrors();
});
