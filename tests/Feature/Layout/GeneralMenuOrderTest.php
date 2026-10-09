<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * The block under the member space, grouped by intent: what concerns the
 * member today, then the people of the club and how to reach them, then the
 * texts one reads now and then. The groups are split by separators.
 */
it('orders the general menu in three groups', function (): void {
    $member = activeMember(makeActiveSeason());
    $this->actingAs($member);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);

    // From the logout that closes the member space onwards.
    $menu = substr($html, (int) strpos($html, 'actions.logout'));

    $positions = collect([
        __('Dashboard'),
        __('Notifications'),
        'separator-people',
        __('Member directory'),
        __('Who does what'),
        __('Your feedback'),
        'separator-reference',
        __('Rules & regulations'),
        __('Club charter'),
        __('Assembly minutes'),
    ])->map(fn (string $needle): int|false => str_starts_with($needle, 'separator-')
        ? strpos($menu, "data-menu-group=\"{$needle}\"")
        : strpos($menu, e($needle)));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->all())->toBe($positions->sort()->values()->all());
});

it('keeps « Who does what » from a member who is not affiliated', function (): void {
    $newcomer = User::factory()->create();
    $this->actingAs($newcomer);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $newcomer]);

    expect($html)->not->toContain(e(__('Who does what')));
});

it('offers « Your feedback » to every signed-in member, affiliated or not', function (): void {
    $newcomer = User::factory()->create();
    $this->actingAs($newcomer);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $newcomer]);

    expect($html)->toContain(route('admin.user.feedback', $newcomer));
});

it('leads the committee to the feedback, and keeps a member out', function (): void {
    $seat = User::factory()->isCommitteeMember()->create();
    $this->actingAs($seat);
    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $seat]);
    expect($html)->toContain(route('admin.feedback.index'));

    $member = User::factory()->create();
    $this->actingAs($member);
    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);
    expect($html)->not->toContain(route('admin.feedback.index'));
});
