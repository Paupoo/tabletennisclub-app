<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * The bar menu, grouped by what it is used for: the counter in the order of
 * an evening (order, cash in, look back, close the till), then the stock in
 * the order of its life (the catalogue, what comes in, what is counted, what
 * went out), then the menu shown to the room.
 */
it('orders the bar menu in two groups before the menu section', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $this->actingAs($admin);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $admin]);
    $menu = substr($html, (int) strpos($html, route('bar.index')));

    $positions = collect([
        __('New order'),
        __('To cash in'),
        __('History'),
        __('Cash sheet'),
        'separator-bar-stock',
        __('Products'),
        __('Categories'),
        __('Shopping'),
        __('Inventories'),
        __('Stock outflows'),
        __('Cast the menu'),
    ])->map(fn (string $needle): int|false => str_starts_with($needle, 'separator-')
        ? strpos($menu, "data-menu-group=\"{$needle}\"")
        : strpos($menu, e($needle)));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->all())->toBe($positions->sort()->values()->all());
});

it('draws no stock separator for a committee reader who never stands at the counter', function (): void {
    $seat = User::factory()->isCommitteeMember()->create();
    $this->actingAs($seat);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $seat]);

    expect($html)->toContain(route('bar.stats.index'))
        ->not->toContain('separator-bar-stock');
});

it('names the screen after everything that left the stock', function (): void {
    $this->actingAs(User::factory()->isCommitteeMember()->create())
        ->get(route('bar.stats.index'))
        ->assertOk()
        ->assertSee('Sorties de stock');
});
