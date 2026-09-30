<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
 * The treasury menu follows the money, from the whole to the detail: the
 * report first, then the money that moves (the bank, the till) and what
 * justifies it, then what is owed (by the members, to the members, the fines
 * the club only follows). Groups are split by separators, as in the member
 * space.
 */
function treasuryMenuOf(User $user): string
{
    test()->actingAs($user);

    $html = (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $user]);
    $start = (int) strpos($html, e(__('Treasury')));

    return substr($html, $start);
}

it('orders the treasury menu in three groups', function (): void {
    $menu = treasuryMenuOf(User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->withRole(Role::CASH_REGISTER)->withRole(Role::FINES)->create());

    $positions = collect([
        __('Financial report'),
        'separator-treasury-accounts',
        __('Bank Transactions'),
        __('Cash Register'),
        __('Supporting documents'),
        'separator-treasury-dues',
        __('Payments'),
        __('Expense reports'),
        __('Fines'),
    ])->map(fn (string $needle): int|false => str_starts_with($needle, 'separator-')
        ? strpos($menu, "data-menu-group=\"{$needle}\"")
        : strpos($menu, e($needle)));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->all())->toBe($positions->sort()->values()->all());
});

it('draws no separator in front of an empty group', function (): void {
    // The cash register délégation alone reaches the till, and nothing else.
    $menu = treasuryMenuOf(User::factory()->withRole(Role::CASH_REGISTER)->create());

    expect($menu)->toContain(e(__('Cash Register')))
        ->not->toContain('separator-treasury-accounts')
        ->not->toContain('separator-treasury-dues');
});

/*
 * Every account is a member's, and the member space is the menu people most
 * often failed to find: it opens unfolded, one click saved.
 */
it('shows the member space unfolded', function (): void {
    $member = User::factory()->create();
    test()->actingAs($member);

    $html = (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);
    $memberSpace = substr($html, 0, (int) strpos($html, e(__('My profile'))));

    expect($memberSpace)->toMatch('/show:\s+true/');
});
