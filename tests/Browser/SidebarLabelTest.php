<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * A navigation label cut short hides a destination, and the sidebar is on all
 * 58 admin screens. Two clipped: "Configuration de la saison", nested two
 * levels deep with 156px left for 172px of text, and "Monitoring de la file
 * d'attente", over by a single pixel — enough for the browser to drop four
 * characters and an ellipsis.
 *
 * The signed-in member's e-mail is deliberately left out. It is the one string
 * in the sidebar that cannot be shortened — it belongs to the member, not to
 * the interface — and its `truncate` is the intended behaviour. DS-B's 12px
 * readability floor raised it from 10px and put it 2px over; the floor wins.
 */

const SIDEBAR_LABELS = <<<'JS_WRAP'
(() => {
  const email = document.querySelector('.drawer-side [data-user-email]');

  const clipped = [...document.querySelectorAll('.drawer-side *')]
    .filter(el => el !== email)
    .filter(el => getComputedStyle(el).textOverflow === 'ellipsis')
    .filter(el => el.getClientRects().length > 0)
    .filter(el => el.scrollWidth > el.clientWidth)
    .map(el => ({
      text: el.textContent.trim().slice(0, 60),
      over: el.scrollWidth - el.clientWidth,
    }));

  return JSON.stringify({clipped});
})()
JS_WRAP;

it('never clips a label in the admin sidebar', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'email' => 'jean-christophe.vandenbroucke@example.org',
    ]);
    makeActiveSeason();
    $this->actingAs($admin);

    $page = visit(route('admin.queue.index'))->resize(1440, 1300);

    // The nested entries only exist once their section is open.
    $page->script('document.querySelectorAll(".drawer-side details").forEach(d => d.open = true)');

    $sidebar = json_decode((string) $page->script(SIDEBAR_LABELS), true);

    expect($sidebar['clipped'])->toBe([], 'a navigation label cut short hides where it leads');
});
