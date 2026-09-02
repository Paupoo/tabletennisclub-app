<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/*
 * form-section owns its own grid since 5db5a83c. Pages written for the old
 * contract still carry col-span-* children, and a single col-span-6 left behind
 * creates six implicit tracks in the parent grid — so each section, no longer
 * spanning anything, takes one of them and the sections line up side by side.
 *
 * Sections stack; only their own title and fields sit side by side.
 */
beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->admin = User::factory()->withRole(Role::SUPERVISION)->create();
});

it('stacks the sections of the club sheet instead of lining them up', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.club-info'))->resize(1440, 1000);

    $geometry = $page->script(<<<'JS'
        (() => {
          const main = document.querySelector('main') || document.body;
          const leaves = [...main.querySelectorAll('*')].filter((e) => e.children.length === 0);
          const first = leaves.find((e) => (e.textContent || '').trim().startsWith('Identité'));
          const second = leaves.find((e) => (e.textContent || '').trim().startsWith('Détails'));
          if (!first || !second) return { found: false };
          const a = first.getBoundingClientRect();
          const b = second.getBoundingClientRect();
          return { found: true, firstTop: Math.round(a.top), secondTop: Math.round(b.top), secondLeft: Math.round(b.left), firstLeft: Math.round(a.left) };
        })()
    JS);

    $g = $geometry[0] ?? $geometry;

    expect($g['found'])->toBeTrue('both section titles should render');
    expect($g['secondTop'])->toBeGreaterThan($g['firstTop'], 'the second section belongs below the first, not beside it');
    expect($g['secondLeft'])->toBe($g['firstLeft'], 'both section titles share the same left edge');
});

it('stacks the sections of the member settings too', function (): void {
    $member = User::factory()->create();
    $this->actingAs($member);

    $page = visit(route('admin.user.settings', $member))->resize(1440, 1000);

    $geometry = $page->script(<<<'JS'
        (() => {
          const main = document.querySelector('main') || document.body;
          const leaves = [...main.querySelectorAll('*')].filter((e) => e.children.length === 0);
          const first = leaves.find((e) => (e.textContent || '').trim() === 'Apparence');
          const second = leaves.find((e) => (e.textContent || '').trim() === 'Notifications');
          if (!first || !second) return { found: false };
          const a = first.getBoundingClientRect();
          const b = second.getBoundingClientRect();
          return { found: true, firstTop: Math.round(a.top), secondTop: Math.round(b.top), firstLeft: Math.round(a.left), secondLeft: Math.round(b.left) };
        })()
    JS);

    $g = $geometry[0] ?? $geometry;

    expect($g['found'])->toBeTrue('both section titles should render');
    expect($g['secondTop'])->toBeGreaterThan($g['firstTop'], 'the second section belongs below the first');
    expect($g['secondLeft'])->toBe($g['firstLeft'], 'both section titles share the same left edge');
});

/*
 * The tournament wizard was written against the old contract and kept its
 * `grid grid-cols-6` wrapper. Each self-contained section then takes one of the
 * six tracks, so Details, Capacity and Rules line up in three ~60px columns and
 * every label overlaps its own field.
 *
 * Measured on the section roots rather than the title text: the wizard's titles
 * are not leaf nodes, and the roots are what has to stack.
 */
it('stacks the sections of the tournament wizard too', function (): void {
    // The wizard is gated on the committee, not on the supervision role used above.
    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());

    $page = visit(route('admin.tournaments.wizard'))->resize(1440, 1000);

    $geometry = $page->script(<<<'JS'
        (() => {
          const sections = [...document.querySelectorAll('div.grid.grid-cols-1.gap-x-8')];
          if (sections.length < 2) return { found: false, count: sections.length };
          const rects = sections.slice(0, 3).map((e) => e.getBoundingClientRect());
          return {
            found: true,
            count: sections.length,
            tops: rects.map((r) => Math.round(r.top)),
            lefts: rects.map((r) => Math.round(r.left)),
            widths: rects.map((r) => Math.round(r.width)),
          };
        })()
    JS);

    $g = $geometry[0] ?? $geometry;

    expect($g['found'])->toBeTrue('the wizard should render at least two form sections');

    // Side by side, each section would be a sixth of the ~1100px content area.
    expect($g['widths'][0])->toBeGreaterThan(600, 'a section spans the content width; a narrow one means the parent grid is slicing it');
    expect($g['lefts'][1])->toBe($g['lefts'][0], 'every section shares the same left edge');
    expect($g['tops'][1])->toBeGreaterThan($g['tops'][0], 'the second section belongs below the first, not beside it');
});
