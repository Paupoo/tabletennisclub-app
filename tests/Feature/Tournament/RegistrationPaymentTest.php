<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendDebtReminderNotification;
use App\Models\ClubAdmin\Payment\CashRegister;
use App\Models\ClubAdmin\Payment\CashRegisterEntry;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function paidTournamentNoPayment(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'price' => 1000,
        'start_date' => now()->addDays(7)->toDateString(),
        'duration_minutes' => 180,
        'nb_pools' => 2,
        'pool_size' => 4,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ], $overrides));
}

function registrationWithoutPaymentRecord(Tournament $tournament, User $user): TournamentRegistration
{
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    return TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
}

// ── ensurePaymentExists ───────────────────────────────────────────────────────

describe('ensurePaymentExists', function () {
    it('creates a Payment when payment_id is null', function () {
        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        $registration = registrationWithoutPaymentRecord($tournament, $user);

        expect($registration->payment_id)->toBeNull();

        $payment = app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        expect($payment)->toBeInstanceOf(Payment::class)
            ->and($payment->amount_due)->toBe($tournament->price)
            ->and($payment->status)->toBe('pending');

        expect(
            TournamentRegistration::find($registration->id)->payment_id
        )->toBe($payment->id);
    });

    it('returns the existing Payment when payment_id is already set', function () {
        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        $registration = registrationWithoutPaymentRecord($tournament, $user);

        $first = app(TournamentService::class)->ensurePaymentExists($registration, $tournament);
        $registration->refresh();

        $second = app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        expect($second->id)->toBe($first->id)
            ->and(Payment::count())->toBe(1);
    });

    it('does not create a Payment for free tournaments via direct call', function () {
        $tournament = paidTournamentNoPayment(['price' => 0]);
        $user = User::factory()->create();
        $registration = registrationWithoutPaymentRecord($tournament, $user);

        $payment = app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        expect($payment->amount_due)->toEqual(0);
    });
})->group('Tournament', 'Payment');

// ── recordCashPayment ─────────────────────────────────────────────────────────

describe('recordCashPayment', function () {
    it('creates a Payment when payment_id is null, marks it paid and records cash entry', function () {
        $admin = User::factory()->isAdmin()->create();
        $this->actingAs($admin);

        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        registrationWithoutPaymentRecord($tournament, $user);

        $register = CashRegister::create(['name' => 'Main register', 'balance' => 0]);

        app(TournamentService::class)->recordCashPayment($tournament, $user, $register);

        $payment = Payment::first();
        expect($payment->status)->toBe('paid')
            ->and($payment->payment_method)->toBe('cash')
            ->and($payment->amount_paid)->toBe($tournament->price);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('has_paid')
        )->toBeTrue();

        expect(CashRegisterEntry::where('cash_register_id', $register->id)->exists())->toBeTrue();
    });

    it('updates an existing Payment when payment_id is already set', function () {
        $admin = User::factory()->isAdmin()->create();
        $this->actingAs($admin);

        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        $registration = registrationWithoutPaymentRecord($tournament, $user);

        $register = CashRegister::create(['name' => 'Main register', 'balance' => 0]);

        app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        app(TournamentService::class)->recordCashPayment($tournament, $user, $register);

        expect(Payment::count())->toBe(1)
            ->and(Payment::first()->status)->toBe('paid');
    });
})->group('Tournament', 'Payment');

// ── recordDebt ────────────────────────────────────────────────────────────────

describe('recordDebt', function () {
    it('creates a Payment when payment_id is null and dispatches the reminder job', function () {
        Bus::fake();

        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        registrationWithoutPaymentRecord($tournament, $user);

        app(TournamentService::class)->recordDebt($tournament, $user);

        expect(Payment::count())->toBe(1)
            ->and(Payment::first()->status)->toBe('pending');

        Bus::assertDispatched(SendDebtReminderNotification::class);
    });

    it('dispatches the reminder job when payment_id is already set', function () {
        Bus::fake();

        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        $registration = registrationWithoutPaymentRecord($tournament, $user);

        app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        app(TournamentService::class)->recordDebt($tournament, $user);

        expect(Payment::count())->toBe(1);
        Bus::assertDispatched(SendDebtReminderNotification::class);
    });
})->group('Tournament', 'Payment');

// ── openQrModal (Livewire) ────────────────────────────────────────────────────

describe('openQrModal', function () {
    it('creates a Payment on-the-fly and opens the QR modal for a registration without payment_id', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = paidTournamentNoPayment();
        $user = User::factory()->create();
        registrationWithoutPaymentRecord($tournament, $user);

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('openQrModal', $user->id)
            ->assertSet('showQrModal', true)
            ->assertSet('paymentActionUserId', $user->id);

        expect(Payment::count())->toBe(1);
    });
})->group('Tournament', 'Payment', 'Livewire');
