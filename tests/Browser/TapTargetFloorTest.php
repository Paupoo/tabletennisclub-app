<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * WCAG 2.2 puts the floor for a pointer target at 24 by 24 CSS pixels. Two
 * buttons sat below it because they were only as tall as their own text: the
 * "Details" toggle on an audit entry and the Markdown help of the article
 * editor.
 *
 * A radio inside a padded <label> is not a small target — the label is what
 * takes the tap, and it is the whole row. The sweep flagged those too; they
 * are left alone on purpose.
 */

const SMALL_TARGETS = <<<'JS'
(() => {
  const small = [...document.querySelectorAll('a[href], button, summary, input, select, textarea')]
    .filter(el => el.closest('.drawer-side, dialog') === null)
    .filter(el => {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) return false;
      // Un contrôle enveloppé dans un <label> rembourré : c'est le label qui
      // reçoit le doigt, pas le contrôle.
      const target = el.closest('label') ?? el;
      const t = target.getBoundingClientRect();
      return Math.min(t.width, t.height) < 24;
    })
    .map(el => el.tagName.toLowerCase() + '[' + (typeof el.className === 'string' ? el.className.trim().slice(0, 60) : '') + ']');

  return JSON.stringify(small);
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('keeps every target of the audit log at the tap floor', function (): void {
    // Une entrée modifiée : le bouton « Détails » n'existe que sur celles-là.
    $member = User::factory()->create();
    $member->update(['first_name' => 'Modifié']);

    $small = json_decode((string) visit(route('admin.audit.index'))->resize(375, 812)->script(SMALL_TARGETS), true);

    expect($small)->toBe([], 'a pointer target belongs at 24px or more');
});

it('keeps every target of the meeting form at the tap floor', function (): void {
    $small = json_decode((string) visit(route('admin.meetings.create'))->resize(375, 812)->script(SMALL_TARGETS), true);

    expect($small)->toBe([], 'a pointer target belongs at 24px or more');
});
