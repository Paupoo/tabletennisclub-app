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

it('never scrolls the page sideways on a phone', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.delegations'))->resize(375, 812);

    // The table is allowed to scroll inside its own box; the document is not.
    $overflows = $page->script('document.documentElement.scrollWidth > document.documentElement.clientWidth + 1');

    expect($overflows)->toBeFalse();
});

it('keeps the member view readable on a phone', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.users.delegations') . '?view=members')->resize(375, 812);

    $overflows = $page->script('document.documentElement.scrollWidth > document.documentElement.clientWidth + 1');

    expect($overflows)->toBeFalse();
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

    $overflows = $page->script('document.documentElement.scrollWidth > document.documentElement.clientWidth + 1');

    expect($overflows)->toBeFalse();
});
