<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Member administration answered to committee membership, and the registrations
| screen — approving affiliations, cancelling them with a refund, opening the
| season — had no internal check at all. Each ability is now named.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->delegate = User::factory()->withRole(Role::MEMBERS)->create();
    $this->committeeOnly = User::factory()->isCommitteeMember()->create();
    $this->member = User::factory()->create();
});

describe('reaching the screens', function (): void {
    it('opens member administration to its delegate', function (string $routeName): void {
        $this->actingAs($this->delegate)->get(route($routeName))->assertOk();
    })->with([
        'admin.users.index',
        'admin.users.create',
        'admin.users.registrations',
        'admin.subscriptions.roster',
        'admin.users.delegations',
    ]);

    it('keeps a plain member out', function (string $routeName): void {
        $this->actingAs($this->member)->get(route($routeName))->assertForbidden();
    })->with([
        'admin.users.index',
        'admin.users.create',
        'admin.users.registrations',
        'admin.subscriptions.roster',
        'admin.users.delegations',
    ]);

    it('lets the committee read without granting it the duty', function (): void {
        // The baseline is read-only: lists yes, the create form no.
        $this->actingAs($this->committeeOnly)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->committeeOnly)->get(route('admin.subscriptions.roster'))->assertOk();
        $this->actingAs($this->committeeOnly)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($this->committeeOnly)->get(route('admin.users.delegations'))->assertForbidden();
    });
});

describe('the registrations screen, which had no guard at all', function (): void {
    it('refuses every mutation to a committee member without the duty', function (string $method): void {
        Livewire::actingAs($this->committeeOnly)
            ->test('pages::club-admin.users.registrations')
            ->call($method)
            ->assertForbidden();
    })->with([
        'approve',
        'reject',
        'confirmCancelSubscription',
        'toggleRegistrations',
        'sendPaymentEmail',
    ]);
});

describe('destructive abilities stay with administrators', function (): void {
    it('withholds archiving and anonymising from the members delegate', function (): void {
        $target = User::factory()->create();

        expect($this->delegate)
            ->can('delete', $target)->toBeFalse()
            ->can('anonymize', $target)->toBeFalse()
            ->and(User::factory()->isAdmin()->create()->can('anonymize', $target))->toBeTrue();
    });

    it('never lets anyone hard-delete a member', function (): void {
        $target = User::factory()->create();

        expect(User::factory()->isAdmin()->create()->can('forceDelete', $target))->toBeFalse();
    });

    it('reserves promoting an administrator to administrators', function (): void {
        $target = User::factory()->create();

        expect($this->delegate->can('promoteAdmin', $target))->toBeFalse()
            ->and(User::factory()->isAdmin()->create()->can('promoteAdmin', $target))->toBeTrue();
    });
});

describe('members keep control of their own account', function (): void {
    it('lets a member manage their own affiliation and password', function (): void {
        expect($this->member)
            ->can('manageSubscription', $this->member)->toBeTrue()
            ->can('updatePassword', $this->member)->toBeTrue()
            ->can(Permission::SubscriptionsManage->value)->toBeFalse();
    });

    it('does not let them manage somebody else', function (): void {
        expect($this->member->can('manageSubscription', User::factory()->create()))->toBeFalse();
    });
});
