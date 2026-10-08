<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;

/*
 * A counter in the menu says one thing: something is waiting for you. One
 * colour for all of them, the expense reports' yellow, and the same figures as
 * the dashboard's pills — both read PendingTasks.
 */

function menuCountersMenuOf(User $user): string
{
    test()->actingAs($user);

    return (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $user]);
}

/**
 * The menu entry leading to this URL, from its link to its end.
 */
function menuCountersEntry(string $menu, string $url): string
{
    $start = strpos($menu, 'href="' . $url . '"');
    expect($start)->not->toBeFalse();

    return substr($menu, (int) $start, (int) strpos($menu, '</a>', (int) $start) - (int) $start);
}

it('draws every counter of the menu in the same yellow', function (): void {
    $source = (string) file_get_contents(resource_path('views/components/admin/navigation.blade.php'));

    preg_match_all('/badge-classes="([^"]*)"/', $source, $classes);

    expect($classes[1])->not->toBeEmpty()
        ->each->toBe('badge-warning');
});

it('counts the unread notifications in yellow', function (): void {
    $member = User::factory()->create();
    $member->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'test',
        'data' => [],
    ]);

    expect(menuCountersEntry(menuCountersMenuOf($member), route('notifications.index')))
        ->toContain('badge-warning">1<')
        ->not->toContain('badge-error');
});

it('counts the open tabs on « To cash in », and no longer the cart', function (): void {
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);

    $menu = menuCountersMenuOf(User::factory()->withRole(Role::BARMAN)->create());

    expect(menuCountersEntry($menu, route('bar.orders.index')))->toContain('badge-warning">2<')
        ->and(menuCountersEntry($menu, route('bar.index')))->not->toContain('badge');
});

it('sums the counters of a folded sub-menu on its title, until it unfolds', function (): void {
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);

    $menu = menuCountersMenuOf(User::factory()->withRole(Role::BARMAN)->create());

    expect($menu)->toMatch('/' . preg_quote(e(__('Bar')), '/') . '\s*<span data-submenu-badge x-show="!show" x-cloak class="badge badge-sm badge-warning">1<\/span>/');
});

it('shows no counter to whoever has nothing to do', function (): void {
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);

    expect(menuCountersMenuOf(User::factory()->create()))
        ->not->toContain('data-submenu-badge')
        ->not->toContain('badge badge-sm badge-warning');
});

it('gives the menu and the dashboard the same figure', function (): void {
    $season = Season::factory()->create(['is_active' => true]);
    foreach ([1, 2] as $ignored) {
        Subscription::factory()->create(['user_id' => User::factory()->create()->id, 'season_id' => $season->id, 'status' => 'pending']);
    }
    $secretary = User::factory()->withRole(Role::MEMBERS)->create();
    Subscription::factory()->create(['user_id' => $secretary->id, 'season_id' => $season->id, 'status' => 'paid']);

    $page = (string) $this->actingAs($secretary)->get(route('dashboard'))->assertOk()->getContent();

    expect($page)->toContain('2 affiliations en attente')
        ->and(menuCountersEntry($page, e(route('admin.users.registrations'))))->toContain('badge-warning">2<');
});

it('hands the treasurer the bank lines to reconcile rather than the open payments', function (): void {
    Payment::factory()->create([
        'status' => 'pending',
        'payable_type' => Subscription::class,
        'payable_id' => Subscription::factory()->create(['user_id' => User::factory()->create()->id])->id,
    ]);

    $alerts = collect($this->actingAs(User::factory()->withRole(Role::TREASURY)->create())
        ->get(route('dashboard'))->viewData('alerts'));

    expect($alerts->pluck('label')->implode(' '))->not->toContain('paiement en attente');
});
