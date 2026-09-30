<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Every Mary <x-textarea> grows with its content (`field-sizing: content` in
 * app.css), from a floor set by its `rows` to a 60vh ceiling. The rule is
 * global, so this measures it on a textarea dropped into an ordinary page:
 * what counts is the height the browser gives the box, not a class name.
 */
it('grows a textarea with its content, between its rows and 60vh', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit(route('admin.user.settings', $user))->resize(1280, 900);

    $heights = $page->script(<<<'JS'
        (() => {
          const field = document.createElement('textarea');
          field.className = 'textarea w-full';
          field.rows = 4;
          (document.querySelector('main') || document.body).prepend(field);

          const measure = () => Math.round(field.getBoundingClientRect().height);
          const lineHeight = parseFloat(getComputedStyle(field).lineHeight);

          const empty = measure();
          field.value = 'line\n'.repeat(12);
          const twelveLines = measure();
          field.value = 'line\n'.repeat(400);
          const huge = measure();

          return { empty, twelveLines, huge, lineHeight, ceiling: Math.round(window.innerHeight * 0.6) };
        })()
    JS);

    $h = $heights[0] ?? $heights;

    expect($h['empty'])->toBeGreaterThanOrEqual((int) floor(4 * $h['lineHeight']), 'rows="4" is the floor of an empty field');
    expect($h['twelveLines'])->toBeGreaterThan($h['empty'] + 4 * $h['lineHeight'], 'the field grows with its lines');
    expect($h['huge'])->toBeLessThanOrEqual($h['ceiling'] + 1, 'the field stops growing at 60vh and scrolls');
});
