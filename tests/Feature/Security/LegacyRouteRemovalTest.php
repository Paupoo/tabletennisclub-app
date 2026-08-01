<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy "obsolete" route block
|--------------------------------------------------------------------------
|
| routes/web.php carried a block of resource routes behind `auth` + `verified`
| only. Three of the four controllers were empty stubs whose Blade views had
| been deleted, but `SeasonController::store()` really did create a season —
| the model that drives affiliations, subscriptions and payments — for any
| verified member. The whole block is gone; only the subscribe endpoint stayed,
| and it now authorizes the caller against the member being subscribed.
|
*/

describe('removed legacy routes', function (): void {

    it('no longer registers the obsolete resource routes', function (string $routeName): void {
        expect(Route::has($routeName))->toBeFalse();
    })->with([
        'clubEvents.interclubs.seasons.index',
        'clubEvents.interclubs.seasons.create',
        'clubEvents.interclubs.seasons.store',
        'clubEvents.interclubs.seasons.show',
        'clubEvents.interclubs.seasons.edit',
        'clubEvents.interclubs.seasons.update',
        'clubEvents.interclubs.seasons.destroy',
        'clubAdmin.registrations.index',
        'clubAdmin.registrations.store',
        'admin.payments.index',
        'admin.payments.store',
    ]);

    it('rejects a verified member trying to create a season over HTTP', function (): void {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post('/seasons', ['name' => '2099-2100'])
            ->assertNotFound();

        $this->assertDatabaseMissing('seasons', ['name' => '2099-2100']);
    });

    it('still serves the Livewire season index to an admin', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();

        $this->actingAs($admin)->get(route('admin.seasons.index'))->assertOk();
    });

})->group('security');

describe('season subscription authorization', function (): void {

    beforeEach(function (): void {
        $this->season = makeActiveSeason();
    });

    it('forbids a member from subscribing somebody else', function (): void {
        $member = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($member)
            ->post(route('clubEvents.interclubs.seasons.subscribe', $this->season), [
                'user_id' => (string) $target->id,
                'type' => 'casual',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $target->id,
            'season_id' => $this->season->id,
        ]);
    });

    it('lets a member subscribe themselves', function (): void {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('clubEvents.interclubs.seasons.subscribe', $this->season), [
                'user_id' => (string) $member->id,
                'type' => 'casual',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $member->id,
            'season_id' => $this->season->id,
            'is_competitive' => false,
        ]);
    });

    it('lets a subscriptions manager subscribe somebody else', function (): void {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::SubscriptionsManage->value);
        $target = User::factory()->create();

        $this->actingAs($manager)
            ->post(route('clubEvents.interclubs.seasons.subscribe', $this->season), [
                'user_id' => (string) $target->id,
                'type' => 'competitive',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $target->id,
            'season_id' => $this->season->id,
            'is_competitive' => true,
        ]);
    });

})->group('security', 'subscriptions');
