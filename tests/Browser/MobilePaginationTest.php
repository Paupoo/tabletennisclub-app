<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;

/*
 * Every list draws twice: a table under `hidden lg:block`, and cards under
 * `lg:hidden`. The paginator used to sit inside the table only, so a phone
 * showed the first page and offered nothing to reach the second — the rows
 * simply stopped, with no sign that more existed.
 *
 * The paginator has to be *visible*, not merely present: it was in the DOM all
 * along, inside a container the phone never displays.
 */

const VISIBLE_PAGER = <<<'JS'
(() => {
  const pagers = [...document.querySelectorAll('nav')]
    .filter(n => /Suivant|Précédent|Affichage/.test(n.textContent || ''));
  const visible = pagers.filter(n => {
    const r = n.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  });
  return {
    inDom: pagers.length,
    visible: visible.length,
    text: visible.map(n => (n.textContent || '').replace(/\s+/g, ' ').trim()).join(' '),
  };
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('lets a phone reach the second page of the member list', function (): void {
    $this->actingAs($this->admin);

    User::factory()->count(40)->create();

    $probe = visit(route('admin.users.index'))->resize(375, 812)->script(VISIBLE_PAGER);
    $result = $probe[0] ?? $probe;

    expect($result['visible'])->toBeGreaterThan(0, 'the member list must be pageable from a phone');
});

it('lets a phone reach the second page of the tournament list', function (): void {
    $this->actingAs($this->admin);

    Tournament::factory()->count(25)->create();

    $probe = visit(route('admin.tournaments.index'))->resize(375, 812)->script(VISIBLE_PAGER);
    $result = $probe[0] ?? $probe;

    expect($result['visible'])->toBeGreaterThan(0, 'the tournament list must be pageable from a phone');
});

it('lets a phone reach the second page of the meeting list', function (): void {
    $this->actingAs($this->admin);

    Meeting::factory()->count(25)->create();

    $probe = visit(route('admin.meetings.index'))->resize(375, 812)->script(VISIBLE_PAGER);
    $result = $probe[0] ?? $probe;

    expect($result['visible'])->toBeGreaterThan(0, 'the meeting list must be pageable from a phone');
});

it('writes the pagination summary in French', function (): void {
    $this->actingAs($this->admin);

    User::factory()->count(40)->create();

    $probe = visit(route('admin.users.index'))->resize(1440, 900)->script(VISIBLE_PAGER);
    $result = $probe[0] ?? $probe;

    expect($result['text'])
        ->toContain('Affichage de')
        ->toContain('résultats')
        ->toContain('Précédent')
        ->toContain('Suivant')
        ->not->toContain('Showing')
        ->not->toContain('Previous');
});
