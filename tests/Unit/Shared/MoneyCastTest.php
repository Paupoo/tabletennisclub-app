<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Casts\MoneyCast;

it('converts integer cents to float euros when getting', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->get($model, 'amount_due', 1050, []))->toBe(10.5);
});

it('converts zero cents to zero euros', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->get($model, 'amount_due', 0, []))->toBe(0.0);
});

it('rounds to 2 decimal places when getting', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->get($model, 'amount_due', 6667, []))->toBe(66.67);
});

it('converts float euros to integer cents when setting', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->set($model, 'amount_due', 10.5, []))->toBe(1050.0);
});

it('converts zero euros to zero cents when setting', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->set($model, 'amount_due', 0.0, []))->toBe(0.0);
});

it('converts null to 0.0 when getting (floatval behavior)', function (): void {
    $cast = new MoneyCast;
    $model = Subscription::factory()->make();
    expect($cast->get($model, 'amount_due', null, []))->toBe(0.0);
});
