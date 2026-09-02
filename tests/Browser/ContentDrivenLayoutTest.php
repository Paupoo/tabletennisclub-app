<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Three back-office screens whose layout broke under the content it was given
 * rather than under a viewport: a two-column stat grid too narrow for its own
 * labels, a body centred in a gutter its header does not share, and a stat
 * tile that shows a sentence where its neighbours show a figure.
 */

const PAYMENTS_STAT_ROW = <<<'JS_WRAP'
(() => {
  const cards = [...document.querySelectorAll('[data-stat-card]')]
    .map(c => Math.round(c.getBoundingClientRect().width));

  const clipped = [...document.querySelectorAll('[data-stat-hint]')]
    .filter(h => h.scrollWidth > h.clientWidth + 1)
    .map(h => h.textContent.trim());

  const list = document.querySelector('[role=tablist]');

  return JSON.stringify({
    widths: [...new Set(cards)],
    cards: cards.length,
    clipped,
    tabOverflow: list.scrollWidth - list.clientWidth,
  });
})()
JS_WRAP;

const BOARD_GUTTERS = <<<'JS_WRAP'
(() => {
  const header = document.querySelector('[data-board-header]');
  const body = document.querySelector('[data-board-body]');

  return JSON.stringify({
    header: Math.round(header.getBoundingClientRect().left),
    body: Math.round(body.getBoundingClientRect().left),
    headerRight: Math.round(header.getBoundingClientRect().right),
    bodyRight: Math.round(body.getBoundingClientRect().right),
  });
})()
JS_WRAP;

const QUEUE_TILE_VALUES = <<<'JS_WRAP'
(() => {
  const values = [...document.querySelectorAll('[data-stat-value]')]
    .map(v => v.textContent.trim());

  return JSON.stringify({values});
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('gives every payment stat the same width and its hint in full on a phone', function (): void {
    $row = json_decode(
        (string) visit(route('admin.treasury.payments'))->resize(390, 1400)->script(PAYMENTS_STAT_ROW),
        true
    );

    expect($row['cards'])->toBe(3);
    expect($row['widths'])->toHaveCount(1, 'the three stats share one row width, none is left half-size');
    expect($row['clipped'])->toBe([], 'a hint cut mid-word says nothing');
    expect($row['tabOverflow'])->toBeLessThanOrEqual(0);
});

it('starts the planning board body on the same line as its title', function (): void {
    $gutters = json_decode(
        (string) visit(route('admin.planning.board'))->resize(1440, 900)->script(BOARD_GUTTERS),
        true
    );

    expect($gutters['body'])->toBe($gutters['header']);
    expect($gutters['bodyRight'])->toBe($gutters['headerRight']);
});

it('shows a figure on every queue health tile', function (): void {
    $tiles = json_decode(
        (string) visit(route('admin.queue.index'))->resize(1440, 900)->script(QUEUE_TILE_VALUES),
        true
    );

    expect($tiles['values'])->toHaveCount(3);

    foreach ($tiles['values'] as $value) {
        expect($value)->toMatch('/\d/', 'a health tile reads at a glance only if it carries a figure');
    }
});
