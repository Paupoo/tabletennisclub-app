<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * The member's own menu, grouped by what they come for: who they are and when
 * they play, their life at the club in the order it happens (affiliate, sign
 * up, join a team, play), then the money, then the settings. The groups are
 * split by separators, never by headings.
 */
it('orders the member space menu in four groups', function (): void {
    $member = User::factory()->create();
    $this->actingAs($member);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);

    // Up to the separator that closes the member space, before the logout.
    $menu = substr($html, 0, (int) strpos($html, 'actions.logout') ?: strlen($html));

    $positions = collect([
        __('My profile'),
        __('My Calendar'),
        'separator-club',
        __('My season'),
        __('My registrations'),
        __('My team(s)'),
        'separator-money',
        __('My payments'),
        __('My expense reports'),
        __('Mutual attestation'),
        'separator-settings',
        __('Settings'),
    ])->map(fn (string $needle): int|false => str_starts_with($needle, 'separator-')
        ? strpos($menu, "data-menu-group=\"{$needle}\"")
        : strpos($menu, e($needle)));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->all())->toBe($positions->sort()->values()->all());
});
