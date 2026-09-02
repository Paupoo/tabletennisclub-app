<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;

/*
 * Two controls that read as broken rather than as what they are: a federal
 * licence rendered `disabled`, which daisyUI strips of its box until only a
 * floating icon is left under a label, and a season picker on the public
 * results page that renders an active-looking <select> with nothing in it when
 * the club has no season yet.
 */

const LICENCE_FIELD = <<<'JS_WRAP'
(() => {
  const legend = [...document.querySelectorAll('legend')]
    .find(l => l.textContent.trim().startsWith('ID / Licence'));

  if (!legend) return JSON.stringify({found: false});

  const fieldset = legend.closest('fieldset');
  const field = fieldset.querySelector('input');
  // daisyUI draws the box on the wrapper, not on the <input> itself. Disabled,
  // it keeps the 1px rule but paints it the colour of its own fill, so the box
  // is there and invisible — the width says nothing, the contrast does.
  const box = fieldset.querySelector('.input');
  const style = getComputedStyle(box);

  return JSON.stringify({
    found: true,
    disabled: field.disabled,
    readOnly: field.readOnly,
    borderColor: style.borderTopColor,
    background: style.backgroundColor,
    height: Math.round(box.getBoundingClientRect().height),
  });
})()
JS_WRAP;

const EMPTY_SELECTS = <<<'JS_WRAP'
(() => {
  const empty = [...document.querySelectorAll('select')]
    .filter(s => s.options.length === 0)
    .map(s => s.id || s.name || '(anonymous)');

  return JSON.stringify({empty});
})()
JS_WRAP;

it('renders the federal licence as a field that can be read, not one that looks broken', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($admin);

    $field = json_decode(
        (string) visit(route('admin.club-info'))->resize(1440, 900)->script(LICENCE_FIELD),
        true
    );

    expect($field['found'])->toBeTrue('the licence field should be on the club identity section');
    expect($field['borderColor'])->not->toBe($field['background'], 'a box painted its own colour is no box at all');
    expect($field['height'])->toBeGreaterThanOrEqual(32, 'it keeps the height of the fields beside it');
    expect($field['disabled'])->toBeFalse('a value meant to be read is read-only, not disabled');
    expect($field['readOnly'])->toBeTrue('the federal licence is not edited here');
});

it('leaves out the public season picker when the club has no season', function (): void {
    Club::factory()->ownClub()->create();

    $selects = json_decode(
        (string) visit(route('results'))->resize(1440, 900)->script(EMPTY_SELECTS),
        true
    );

    expect($selects['empty'])->toBe([], 'a select with no option must not render as an active control');
});
