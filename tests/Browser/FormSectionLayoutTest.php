<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * form-section renders a section title beside its controls. It used to emit bare
 * col-span-* children and borrow a grid from its parent: the settings page was
 * once wrapped in <x-form>, whose plain `grid` created the implicit tracks those
 * spans needed. That wrapper went away in 173af8e5 and every section has stacked
 * since — for 192 commits, with nothing to notice.
 *
 * The component now owns its grid. This measures the rendered result, because a
 * class name in the markup proves nothing about where the browser puts the box.
 */
it('puts the section title beside its controls on a desktop screen', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit(route('admin.user.settings', $user))->resize(1280, 900);

    $geometry = $page->script(<<<'JS'
        (() => {
          const main = document.querySelector('main') || document.body;
          const leaves = [...main.querySelectorAll('*')].filter((e) => e.children.length === 0);
          const title = leaves.find((e) => (e.textContent || '').trim() === 'Apparence');
          const control = leaves.find((e) => (e.textContent || '').trim() === 'Theme');
          if (!title || !control) return { found: false };
          const t = title.getBoundingClientRect();
          const c = control.getBoundingClientRect();
          return {
            found: true,
            titleRight: Math.round(t.right),
            controlLeft: Math.round(c.left),
            verticalOverlap: Math.round(Math.min(t.bottom, c.bottom) - Math.max(t.top, c.top)),
          };
        })()
    JS);

    $g = $geometry[0] ?? $geometry;

    expect($g['found'])->toBeTrue('the appearance section should render');
    expect($g['controlLeft'])->toBeGreaterThan($g['titleRight'], 'the control belongs to the right of the title');
    expect($g['verticalOverlap'])->toBeGreaterThan(0, 'title and control share a row rather than stacking');
});
