<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\KeyRing;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
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

    it('is true once the member holds a key ring', function (): void {
        $user = User::factory()->create();
        KeyRing::factory()->heldBy($user)->create();

        expect($user->fresh()->has_key)->toBeTrue();
    });

    it('ignores a key ring that has been retired', function (): void {
        $user = User::factory()->create();
        KeyRing::factory()->heldBy($user)->create()->delete();

        expect($user->fresh()->has_key)->toBeFalse();
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

// ── User form: entrusted equipment is read-only ───────────────────────────────

describe('User form entrusted equipment', function (): void {
    it('lists the key rings the member holds', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $target = User::factory()->isNotCompetitor()->create();
        KeyRing::factory()->heldBy($target)->create(['number' => 7]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.users.form', ['user' => $target])
            ->assertSee(__('Held key rings'))
            ->assertSee(__('Key ring #:number', ['number' => 7]));
    });

    it('says so when no key ring is entrusted', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $target = User::factory()->isNotCompetitor()->create();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.users.form', ['user' => $target])
            ->assertSee(__('No key ring entrusted.'));
    });

    it('offers no way to hand a key ring over from here', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $target = User::factory()->isNotCompetitor()->create();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.users.form', ['user' => $target])
            ->assertDontSee(__('Has a key'));
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
        $keyHolder = User::factory()->create();
        KeyRing::factory()->heldBy($keyHolder)->create();
        User::factory()->create();

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

// ── Club information overview ────────────────────────────────────────────────

describe('Club information equipment overview', function (): void {
    it('lists every key ring, including the ones nobody holds', function (): void {
        $holder = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Dupont']);
        KeyRing::factory()->heldBy($holder)->create(['number' => 1]);
        KeyRing::factory()->create(['number' => 2]);

        $component = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-admin.club-info');

        expect($component->viewData('keyRings')->pluck('number')->all())->toBe([1, 2]);

        $component->assertSee(__('Key ring #:number', ['number' => 2]))
            ->assertSee(__('In the drawer'));
    });

    it('leaves retired key rings out of the overview', function (): void {
        KeyRing::factory()->retired()->create(['number' => 9]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-admin.club-info')
            ->assertDontSee(__('Key ring #:number', ['number' => 9]));
    });
});

// ── Retiring a cash register ─────────────────────────────────────────────────

describe('Retiring a cash register', function (): void {
    it('hides the register without touching its ledger', function (): void {
        $register = makeRegisterWithHolder();
        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount' => 12_50,
            'reason' => 'manual',
            'recorded_by_id' => $register->held_by_user_id,
        ]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->call('retireRegister')
            ->assertHasNoErrors();

        expect(CashRegister::find($register->id))->toBeNull();
        expect(CashRegisterEntry::where('cash_register_id', $register->id)->count())->toBe(1);
    });

    it('puts a retired register back in service', function (): void {
        $register = makeRegisterWithHolder();
        $register->delete();

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-admin.treasury.cash-register')
            ->set('showRetired', true)
            ->call('restoreRegister', $register->id)
            ->assertHasNoErrors();

        expect(CashRegister::find($register->id))->not->toBeNull();
    });

    it('is closed to a committee member without the cash register delegation', function (): void {
        $register = makeRegisterWithHolder();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]))
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->call('retireRegister')
            ->assertForbidden();

        expect(CashRegister::find($register->id))->not->toBeNull();
    });
});
