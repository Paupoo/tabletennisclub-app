<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeHolder(): User
{
    return User::factory()->create();
}

function makeRegisterWithHolder(?User $holder = null): CashRegister
{
    $holder ??= makeHolder();

    return CashRegister::create(['name' => 'Test Register', 'held_by_user_id' => $holder->id]);
}

// ── has_key on User ───────────────────────────────────────────────────────────

describe('User has_key', function (): void {
    it('defaults to false', function (): void {
        $user = User::factory()->create();

        expect($user->has_key)->toBeFalse();
    });

    it('can be set to true', function (): void {
        $user = User::factory()->create(['has_key' => true]);

        expect($user->has_key)->toBeTrue();
    });
});

// ── CashRegister heldBy relationship ─────────────────────────────────────────

describe('CashRegister heldBy', function (): void {
    it('belongs to a holder', function (): void {
        $holder = makeHolder();
        $register = makeRegisterWithHolder($holder);

        expect($register->heldBy->id)->toBe($holder->id);
    });

    it('user has many held cash registers', function (): void {
        $holder = makeHolder();
        makeRegisterWithHolder($holder);
        makeRegisterWithHolder($holder);

        expect($holder->heldCashRegisters()->count())->toBe(2);
    });
});

// ── User form has_key ─────────────────────────────────────────────────────────

describe('User form has_key toggle', function (): void {
    it('admin can enable has_key for a user', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $target = User::factory()->isNotCompetitor()->create(['has_key' => false]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.users.form', ['user' => $target])
            ->set('has_key', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($target->fresh()->has_key)->toBeTrue();
    });

    it('admin can disable has_key for a user', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $target = User::factory()->isNotCompetitor()->create(['has_key' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.users.form', ['user' => $target])
            ->set('has_key', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($target->fresh()->has_key)->toBeFalse();
    });
});

// ── confirmChangeHolder authorization ────────────────────────────────────────

describe('Cash register confirmChangeHolder', function (): void {
    it('admin can change holder', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $newHolder = makeHolder();
        $register = makeRegisterWithHolder();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->set('newHolderUserId', $newHolder->id)
            ->call('confirmChangeHolder')
            ->assertHasNoErrors();

        expect($register->fresh()->held_by_user_id)->toBe($newHolder->id);
    });

    it('treasurer can change holder', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::CASH_REGISTER)->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);
        $newHolder = makeHolder();
        $register = makeRegisterWithHolder();

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->set('newHolderUserId', $newHolder->id)
            ->call('confirmChangeHolder')
            ->assertHasNoErrors();

        expect($register->fresh()->held_by_user_id)->toBe($newHolder->id);
    });

    it('committee member without the cash register delegation cannot change holder', function (): void {
        $secretary = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]);
        $newHolder = makeHolder();
        $register = makeRegisterWithHolder();

        Livewire::actingAs($secretary)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->set('newHolderUserId', $newHolder->id)
            ->call('confirmChangeHolder')
            ->assertForbidden();

        expect($register->fresh()->held_by_user_id)->not->toBe($newHolder->id);
    });

    it('regular user cannot change holder', function (): void {
        $user = User::factory()->create();
        $newHolder = makeHolder();
        $register = makeRegisterWithHolder();

        Livewire::actingAs($user)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->set('newHolderUserId', $newHolder->id)
            ->call('confirmChangeHolder')
            ->assertForbidden();

        expect($register->fresh()->held_by_user_id)->not->toBe($newHolder->id);
    });
});

// ── createRegister with holder ────────────────────────────────────────────────

describe('Cash register creation with holder', function (): void {
    it('admin can create register with holder', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $holder = makeHolder();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('newRegisterName', 'New Register')
            ->set('newRegisterHolderUserId', $holder->id)
            ->call('createRegister')
            ->assertHasNoErrors();

        $register = CashRegister::where('name', 'New Register')->first();
        expect($register)->not->toBeNull();
        expect($register->held_by_user_id)->toBe($holder->id);
    });

    it('regular user cannot create register', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('newRegisterName', 'New Register')
            ->call('createRegister')
            ->assertForbidden();
    });
});

// ── User list filters ─────────────────────────────────────────────────────────

describe('User list filters', function (): void {
    it('hasKey filter returns only key holders', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $keyHolder = User::factory()->create(['has_key' => true]);
        User::factory()->create(['has_key' => false]);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-admin.users.index')
            ->set('hasKey', true);

        $ids = $component->viewData('users')->pluck('id')->toArray();
        expect($ids)->toContain($keyHolder->id);
        expect($ids)->not->toContain($admin->id);
    });

    it('hasCashRegister filter returns only users holding a register', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $holder = User::factory()->create();
        $other = User::factory()->create();
        CashRegister::create(['name' => 'Test', 'held_by_user_id' => $holder->id]);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-admin.users.index')
            ->set('hasCashRegister', true);

        $ids = $component->viewData('users')->pluck('id')->toArray();
        expect($ids)->toContain($holder->id);
        expect($ids)->not->toContain($other->id);
    });
});
