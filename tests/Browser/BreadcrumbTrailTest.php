<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * The trail's first entry carries the home icon. Mary renders the icon and the
 * label as two siblings inside the <a>, with nothing but Blade whitespace
 * between them — and the tap-target hook makes that <a> an inline-flex box,
 * which drops the whitespace. The two glyphs then touch: "⌂Panneau
 * d'administration". The gap belongs on the hook, next to the rule that
 * caused it, because every one of the 58 admin screens renders the trail.
 */

const BREADCRUMB_ICON_GAP = <<<'JS_WRAP'
(() => {
  const link = [...document.querySelectorAll('.breadcrumb-trail a')]
    .find(a => a.querySelector('svg') && a.querySelector('svg + span'));

  if (!link) return JSON.stringify({found: false});

  const icon = link.querySelector('svg').getBoundingClientRect();
  const label = link.querySelector('svg + span').getBoundingClientRect();

  return JSON.stringify({found: true, gap: label.left - icon.right});
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('keeps the home icon off the breadcrumb label', function (): void {
    $measure = json_decode(
        (string) visit(route('admin.queue.index'))->resize(1440, 900)->script(BREADCRUMB_ICON_GAP),
        true
    );

    expect($measure['found'])->toBeTrue('the trail should render an icon followed by its label');
    expect($measure['gap'])->toBeGreaterThanOrEqual(4.0);
});
