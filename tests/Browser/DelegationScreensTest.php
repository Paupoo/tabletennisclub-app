<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;

/*
| The delegations overview is a members × 16 duties matrix — the shape most likely
| to overflow a phone. These guard the two rules the design asks for: the page
| itself never scrolls sideways, and nothing is styled in a way that only holds up
| in one theme.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::PRESIDENT,
    ]);

    User::factory()->withRole(Role::CASH_REGISTER)->create(['last_name' => 'Dubois']);
    User::factory()
        ->withRole(Role::WEBSITE, Role::MEETINGS, Role::TOURNAMENTS)
        ->create(['last_name' => 'Lambert', 'committee_role' => CommitteeRolesEnum::SECRETARY]);
});

it('renders the delegations overview without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.delegations'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Délégations')
        ->assertSee('Dubois');
});

/*
 * These two used to assert `document.scrollWidth > clientWidth`, which on this
 * application can never be true: layouts/app.blade.php puts overflow-x-hidden on
 * <body>, so anything too wide is *clipped* rather than made scrollable and the
 * document never grows. The check passed on every screen, including the ones
 * losing content off the right edge.
 *
 * The probe below asks the question the comment always meant: is any element
 * pushed past the viewport without a scrollable ancestor to reach it through?
 * A table scrolling inside its own box stays legal; content clipped away does not.
 */
$clippedContent = <<<'JS'
(() => {
  const vw = document.documentElement.clientWidth;
  const lost = [];
  for (const el of document.querySelectorAll('body *')) {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;
    if (r.right <= vw + 1) continue;

    let reachable = false;
    for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
      const ox = getComputedStyle(n).overflowX;
      if (ox === 'auto' || ox === 'scroll') {
        reachable = n.scrollWidth > n.clientWidth + 1;
        break;
      }
    }
    if (reachable) continue;
    if (lost.some((o) => o.el.contains(el))) continue;
    lost.push({ el, out: Math.round(r.right - vw) });
    if (lost.length >= 5) break;
  }
  return lost.map((o) => o.out + 'px past the right edge: <' + o.el.tagName.toLowerCase() + ' class="' + (o.el.className || '').toString().slice(0, 60) + '">');
})()
JS;

it('never clips content off the right edge on a phone', function () use ($clippedContent): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.delegations'))->resize(375, 812);

    $result = $page->script($clippedContent);
    $lost = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($lost)->toBe([], implode("\n", $lost));
});

it('keeps the member view readable on a phone', function () use ($clippedContent): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.delegations') . '?view=members')->resize(375, 812);

    $result = $page->script($clippedContent);
    $lost = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($lost)->toBe([], implode("\n", $lost));
});

it('renders in dark mode without losing the delegation badges', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.delegations'))
        ->script("document.documentElement.setAttribute('data-theme', 'dark')");

    visit(route('admin.users.delegations'))
        ->assertNoJavaScriptErrors()
        ->assertSee(Role::CASH_REGISTER->label());
});

it('renders the member form delegations section without JS errors', function (): void {
    $this->actingAs($this->admin);
    $member = User::factory()->withRole(Role::BAR)->create();

    visit(route('admin.users.edit', $member))
        ->assertNoJavaScriptErrors()
        ->assertSee('Délégations')
        ->assertSee(Role::BAR->label());
});

it('keeps the member form usable on a phone', function (): void {
    $this->actingAs($this->admin);
    $member = User::factory()->withRole(Role::BAR)->create();

    $page = visit(route('admin.users.edit', $member))->resize(375, 812);

    // « Failed asserting that true is false » n'apprend rien et oblige à rejouer
    // la page à la main. Le coupable dépend du rendu — un runner CI n'a pas les
    // polices d'un poste de dev et mesure le texte plus large — donc l'échec doit
    // nommer les éléments qui dépassent, là où il se produit.
    $report = $page->script(<<<'JS'
        (() => {
            const limit = document.documentElement.clientWidth;
            const scroll = document.documentElement.scrollWidth;

            if (scroll <= limit + 1) {
                return '';
            }

            const guilty = [];
            document.querySelectorAll('*').forEach((el) => {
                const box = el.getBoundingClientRect();
                if (box.right > limit + 1) {
                    guilty.push(
                        el.tagName.toLowerCase()
                        + (el.id ? '#' + el.id : '')
                        + ' [' + (el.className || '').toString().slice(0, 80) + ']'
                        + ' width=' + Math.round(box.width)
                        + ' right=' + Math.round(box.right)
                    );
                }
            });

            return 'scrollWidth=' + scroll + ' clientWidth=' + limit + "\n"
                + guilty.slice(0, 15).join("\n");
        })()
    JS);

    $this->assertSame('', $report, "Le formulaire membre déborde à 375 px :\n" . $report);
});
