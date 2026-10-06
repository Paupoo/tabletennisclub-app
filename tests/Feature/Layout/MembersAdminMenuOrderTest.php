<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
 * The members administration, grouped by what it is about: the people (who
 * they are, which duty each holds), the season in the order it unfolds
 * (affiliate, gather the roster, attest once paid), then the exchange with the
 * members (what the club writes to them, what they write back). Groups are
 * split by separators, never by headings.
 */

/**
 * The rendered members administration submenu, alone.
 */
function membersAdminMenuFor(User $user): string
{
    test()->actingAs($user);
    $html = (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $user]);
    $start = (int) strpos($html, e(__('Members Admin')));

    return substr($html, $start, (int) strpos($html, '</ul>', strpos($html, e(__('Feedback and suggestions')), $start) ?: $start) - $start);
}

it('orders the members administration in three groups', function (): void {
    $menu = membersAdminMenuFor(User::factory()->isAdmin()->create());

    $positions = collect([
        __('Users'),
        __('Delegations'),
        'separator-members-season',
        __('Affiliations'),
        __('Season roster'),
        __('Mutual attestations'),
        'separator-members-exchange',
        __('Communications'),
        __('Feedback and suggestions'),
    ])->map(fn (string $needle): int|false => str_starts_with($needle, 'separator-')
        ? strpos($menu, "data-menu-group=\"{$needle}\"")
        : strpos($menu, e($needle)));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->all())->toBe($positions->sort()->values()->all());
});

it('moves the planning board to the trainings menu', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $this->actingAs($admin);
    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $admin]);

    $planning = strpos($html, e(__('Planning board')));

    expect($planning)->toBeGreaterThan(strpos($html, e(__('Training Packs'))))
        ->and(substr_count($html, route('admin.planning.board')))->toBe(1);
});

it('draws no separator next to an empty group', function (): void {
    $writer = User::factory()->create();
    $writer->givePermissionTo('communications.send');
    $this->actingAs($writer);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $writer]);

    expect($html)->toContain(e(__('Communications')))
        ->not->toContain('separator-members-season')
        ->not->toContain('separator-members-exchange');
});

it('keeps the members menu from someone who only plans the trainings', function (): void {
    $planner = User::factory()->withRole(Role::TRAININGS)->create();
    $this->actingAs($planner);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $planner]);

    expect($html)->not->toContain(e(__('Members Admin')))
        ->toContain(route('admin.planning.board'));
});
