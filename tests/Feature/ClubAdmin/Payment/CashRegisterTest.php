<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createCashRegister(string $name = 'Test Register'): CashRegister
{
    return CashRegister::create(['name' => $name]);
}

function createEntry(CashRegister $register, int $amount, User $user, string $reason = 'manual'): CashRegisterEntry
{
    return CashRegisterEntry::create([
        'cash_register_id' => $register->id,
        'amount' => $amount,
        'reason' => $reason,
        'recorded_by_id' => $user->id,
    ]);
}

// ── CashRegister model ────────────────────────────────────────────────────────

describe('CashRegister model', function (): void {
    it('starts with a zero balance', function (): void {
        $register = createCashRegister();

        expect($register->currentBalance())->toBe(0);
    });

    it('calculates balance from entries', function (): void {
        $user = User::factory()->create();
        $register = createCashRegister();

        createEntry($register, 1000, $user);
        createEntry($register, 500, $user);
        createEntry($register, -200, $user);

        expect($register->currentBalance())->toBe(1300);
    });

    it('has a hasMany relationship with entries', function (): void {
        $user = User::factory()->create();
        $register = createCashRegister();
        createEntry($register, 500, $user);

        expect($register->entries()->count())->toBe(1);
    });
});

// ── CashRegisterEntry model ───────────────────────────────────────────────────

describe('CashRegisterEntry model', function (): void {
    it('belongs to a cash register', function (): void {
        $user = User::factory()->create();
        $register = createCashRegister();
        $entry = createEntry($register, 1000, $user);

        expect($entry->cashRegister->id)->toBe($register->id);
    });

    it('belongs to the recorder', function (): void {
        $user = User::factory()->create();
        $register = createCashRegister();
        $entry = createEntry($register, 1000, $user);

        expect($entry->recordedBy->id)->toBe($user->id);
    });
});

// ── Payment model ─────────────────────────────────────────────────────────────

describe('Payment payment_method field', function (): void {
    it('has a payment_method column with electronic default', function (): void {
        DB::table('payments')->insert([
            'reference' => 'TEST/001',
            'amount_due' => 1000,
            'amount_paid' => 0,
            'status' => 'pending',
            'payable_type' => 'App\Models\Test',
            'payable_id' => 1,
        ]);

        $row = DB::table('payments')->where('reference', 'TEST/001')->first();

        expect($row->payment_method)->toBe('electronic');
    });
});

// ── Treasury routes ────────────────────────────────────────────────────────────

describe('Treasury routes', function (): void {
    it('admin can access treasury payments view', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.treasury.payments'))
            ->assertOk();
    });

    it('admin can access cash register view', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.treasury.cash'))
            ->assertOk();
    });

    it('admin can access transactions view', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.treasury.transactions'))
            ->assertOk();
    });

    it('legacy payments route redirects to treasury', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.payments'))
            ->assertRedirect(route('admin.treasury.payments'));
    });
});

// ── Cash register Livewire component ─────────────────────────────────────────

describe('Cash register view', function (): void {
    it('creates a new cash register', function (): void {
        $admin = User::factory()->isAdmin()->create();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('newRegisterName', 'Caisse tournoi')
            ->call('createRegister')
            ->assertHasNoErrors();

        expect(CashRegister::where('name', 'Caisse tournoi')->exists())->toBeTrue();
    });

    // Regression: the is_active column was dropped — holder list must use active members.
    it('lists active members as holders and excludes users without an active subscription', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();

        activeMember($season, ['first_name' => 'Olga', 'last_name' => 'Activsky']);
        User::factory()->create(['first_name' => 'Igor', 'last_name' => 'Inactif']);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.cash-register')
            ->assertSee('Olga Activsky')
            ->assertDontSee('Igor Inactif');
    });

    it('records a manual entry', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $register = createCashRegister();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.cash-register')
            ->set('selectedRegisterId', $register->id)
            ->set('entryAmount', 50)
            ->set('entryReason', 'manual')
            ->call('saveManualEntry')
            ->assertHasNoErrors();

        expect(CashRegisterEntry::where('cash_register_id', $register->id)->exists())->toBeTrue();
        expect(CashRegisterEntry::where('cash_register_id', $register->id)->sum('amount'))->toBe(50 * 100);
    });
});
