<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Icon-only controls carry no name: a screen reader announces "button" and
 * stops. The row actions were named in b42fdef7; the toolbar that sits above
 * every list on a phone — search, filters, more — was missed, and repeated the
 * silence on more than fifteen screens.
 *
 * The sweep that found it looks for visible controls whose accessible name is
 * empty. Mary wraps its fields in a <label>, which names them; it also renders
 * some labels as a <legend>, which names the group and not the field — those
 * carry an explicit aria-label instead.
 */

const UNNAMED_CONTROLS = <<<'JS'
(() => {
  const text = el => (el.textContent || '').replace(/\s+/g, ' ').trim();
  const shown = el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };

  // The Mary layout wraps the whole page in a .drawer; only .drawer-side is
  // the off-canvas panel. Closed dialogs do not count either.
  const onScreen = el => el.closest('.drawer-side, dialog') === null;

  const unnamed = [...document.querySelectorAll('a[href], button, summary, input, select, textarea')]
    .filter(onScreen)
    .filter(shown)
    .filter(el => {
      const own = el.getAttribute('aria-label') || el.getAttribute('title') || text(el) || el.getAttribute('placeholder');
      if (own && own.trim()) return false;
      const by = el.getAttribute('aria-labelledby');
      if (by && document.getElementById(by)) return false;
      if (el.id && document.querySelector('label[for="' + el.id + '"]')) return false;
      if (el.closest('label')) return false;
      return true;
    })
    .map(el => el.tagName.toLowerCase() + '[' + (typeof el.className === 'string' ? el.className.trim() : '') + ']');

  return JSON.stringify(unnamed);
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('names every control of the phone toolbar', function (string $route): void {
    $unnamed = json_decode((string) visit(route($route))->resize(375, 812)->script(UNNAMED_CONTROLS), true);

    expect($unnamed)->toBe([], 'every visible control needs a name a screen reader can read');
})->with([
    'admin.users.index',
    'admin.tournaments.index',
    'admin.meetings.index',
    'admin.website.contacts.index',
    'admin.interclubs.teams',
]);

it('names the slider that sizes a team core', function (): void {
    $unnamed = json_decode((string) visit(route('admin.interclubs.teams.builder'))->resize(375, 812)->script(UNNAMED_CONTROLS), true);

    expect($unnamed)->toBe([]);
});

it('names the file field of the federation import', function (): void {
    $unnamed = json_decode((string) visit(route('admin.users.import'))->resize(375, 812)->script(UNNAMED_CONTROLS), true);

    expect($unnamed)->toBe([]);
});
