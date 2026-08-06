<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * An empty list means one of two things and the reader cannot tell them apart:
 * nothing exists yet, or the filters exclude everything. Several screens gave
 * the same answer to both — "Try adjusting your search or filters" — which is
 * absurd advice for a club opening its first season, and offered no way out
 * either way.
 *
 * <x-admin.shared.list-empty-state> carries the action that matches the cause:
 * clear the filters, or create the first record.
 */

const EMPTY_STATE_READING = <<<'JS'
(() => {
  const main = document.querySelector('main') || document.body;
  const text = el => (el.textContent || '').replace(/\s+/g, ' ').trim();
  const shown = el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };

  // L'état vide est le bloc centré que rend <x-empty-state>.
  // La signature de <x-empty-state> : centré, py-16, text-center.
  const block = [...main.querySelectorAll('div.py-16.text-center')]
    .filter(shown)
    .pop();

  if (!block) return JSON.stringify({ present: false });

  return JSON.stringify({
    present: true,
    texte: text(block),
    actions: [...block.querySelectorAll('a[href], button')].map(text).filter(Boolean),
  });
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
    $this->actingAs($this->admin);
});

it('offers a way to create the first record when nothing exists', function (): void {
    $reading = json_decode((string) visit(route('admin.meetings.index'))->resize(1440, 900)->script(EMPTY_STATE_READING), true);

    expect($reading['present'])->toBeTrue()
        ->and($reading['texte'])->toContain(__('No meetings yet'))
        ->and($reading['actions'])->toContain(__('New meeting'));
});

it('offers to clear the filters when they are what hides everything', function (): void {
    $probe = visit(route('admin.meetings.index'))->resize(1440, 900);

    // Un filtre qui ne laisse rien passer, posé comme le ferait le tiroir.
    $probe->script('window.Livewire.all().find(c => c.name.includes("meetings")).$wire.set("type", "general_assembly")');
    usleep(700_000);

    $reading = json_decode((string) $probe->script(EMPTY_STATE_READING), true);

    expect($reading['present'])->toBeTrue()
        ->and($reading['actions'])->toContain(__('Clear filters'))
        ->and($reading['texte'])->not->toContain(__('No meetings yet'));
});

it('never tells a reader to adjust filters that are not set', function (string $route): void {
    $reading = json_decode((string) visit(route($route))->resize(1440, 900)->script(EMPTY_STATE_READING), true);

    if (! $reading['present']) {
        expect(true)->toBeTrue();

        return;
    }

    expect($reading['texte'])->not->toContain(__('No record matches the current filters.'));
})->with([
    'admin.tournaments.index',
    'admin.website.contacts.index',
    'admin.website.spams.index',
    'admin.subscriptions.roster',
    'admin.users.registrations',
    'admin.treasury.fines',
]);
