<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Users\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->manager = User::factory()->isAdmin()->create();
    $this->actingAs($this->manager);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('keeps an overnight service in one history day', function (): void {
    Carbon::setTestNow('2026-09-20 02:00:00');

    $overnight = BarOrder::create([
        'created_by' => $this->manager->id,
        'name' => 'Overnight',
        'total_price' => 100,
        'is_paid' => false,
        'created_at' => '2026-09-19 21:00:00',
    ]);

    $outsideBusinessDay = BarOrder::create([
        'created_by' => $this->manager->id,
        'name' => 'Tomorrow',
        'total_price' => 100,
        'is_paid' => false,
        'created_at' => '2026-09-20 07:00:00',
    ]);

    $response = $this->get(route('bar.orders.history'));

    $response->assertSuccessful()
        ->assertSee($overnight->name)
        ->assertDontSee($outsideBusinessDay->name);
});

it('counts revenue by payment time rather than order creation time', function (): void {
    Carbon::setTestNow('2026-09-20 02:00:00');

    BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 200,
        'is_paid' => true,
        'paid_at' => '2026-09-20 01:00:00',
        'payment_method' => 'cash',
        'created_at' => '2026-09-19 23:00:00',
    ]);

    BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 300,
        'is_paid' => true,
        'paid_at' => '2026-09-19 05:00:00',
        'payment_method' => 'cash',
        'created_at' => '2026-09-19 04:00:00',
    ]);

    $response = $this->get(route('bar.orders.history'));

    expect($response->viewData('totalRevenue'))->toBe(200)
        ->and($response->viewData('totalRevenueOffered'))->toBe(0);
});
