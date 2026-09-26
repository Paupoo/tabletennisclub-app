<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
|--------------------------------------------------------------------------
| Bar — l'écran des ventes
|--------------------------------------------------------------------------
|
| Le comité lit tout (décision du 2026-09-24), mais n'a pas `bar.access` : lui
| donner ouvrirait le comptoir. L'écran des ventes vit donc sous `/bar` sans en
| prendre le verrou, et n'exige que `bar.stats.view`.
|
*/

it('opens to the committee, which has no access to the counter', function (): void {
    $committee = User::factory()->isCommitteeMember()->create();

    $this->actingAs($committee)->get(route('bar.stats.index'))->assertOk();
    $this->actingAs($committee)->get(route('bar.index'))->assertForbidden();
});

it('stays closed to a barman', function (): void {
    $barman = User::factory()->withRole(Role::BARMAN)->create();

    $this->actingAs($barman)->get(route('bar.stats.index'))->assertForbidden();
});
