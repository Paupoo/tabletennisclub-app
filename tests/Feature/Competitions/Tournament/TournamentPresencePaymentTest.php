<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentDebtReminderNotification;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Jobs\SendDebtReminderNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

// ── Helpers ───────────────────────────────────────────────────────────────────

function paidTournament(): Tournament
{
    return Tournament::factory()->create([
        'price' => 10,
        'status' => 'published',
    ]);
}

function registeredUserWithPayment(Tournament $tournament): array
{
    $user = User::factory()->create();
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);
    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->first();

    $payment = $registration->payment()->create([
        'reference' => '001/2026/' . rand(10000, 99999),
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    DB::table('tournament_user')
        ->where('id', $registration->id)
        ->update(['payment_id' => $payment->id]);

    return [$user, $payment];
}

// ── recordCashPayment ─────────────────────────────────────────────────────────

describe('recordCashPayment', function (): void {
    it('marks payment as paid with cash method', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $this->actingAs($admin);
        $service = new TournamentService;
        $register = CashRegister::create(['name' => 'Test']);
        $tournament = paidTournament();
        [$user, $payment] = registeredUserWithPayment($tournament);

        $service->recordCashPayment($tournament, $user, $register);

        $payment->refresh();
        expect($payment->status)->toBe('paid');
        expect($payment->payment_method)->toBe('cash');
    });

    it('sets has_paid to true on the pivot', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $this->actingAs($admin);
        $service = new TournamentService;
        $register = CashRegister::create(['name' => 'Test']);
        $tournament = paidTournament();
        [$user] = registeredUserWithPayment($tournament);

        $service->recordCashPayment($tournament, $user, $register);

        $pivot = DB::table('tournament_user')
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        expect((bool) $pivot->has_paid)->toBeTrue();
    });

    it('creates a cash register entry', function (): void {
        $service = new TournamentService;
        $register = CashRegister::create(['name' => 'Test']);
        $tournament = paidTournament();
        [$user] = registeredUserWithPayment($tournament);
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin);
        $service->recordCashPayment($tournament, $user, $register);

        expect(CashRegisterEntry::where('cash_register_id', $register->id)->exists())->toBeTrue();
        expect(CashRegisterEntry::where('cash_register_id', $register->id)->sum('amount'))
            ->toBe((int) ($tournament->price * 100));
    });
});

// ── recordDebt ────────────────────────────────────────────────────────────────

describe('recordDebt', function (): void {
    it('dispatches a delayed reminder job', function (): void {
        Queue::fake();

        $service = new TournamentService;
        $tournament = paidTournament();
        [$user] = registeredUserWithPayment($tournament);

        $service->recordDebt($tournament, $user);

        Queue::assertPushed(SendDebtReminderNotification::class);
    });

    it('does not mark the payment as paid', function (): void {
        Queue::fake();

        $service = new TournamentService;
        $tournament = paidTournament();
        [$user, $payment] = registeredUserWithPayment($tournament);

        $service->recordDebt($tournament, $user);

        $payment->refresh();
        expect($payment->status)->toBe('pending');
        expect((bool) DB::table('tournament_user')
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->value('has_paid'))->toBeFalse();
    });
});

// ── SendDebtReminderNotification job ─────────────────────────────────────────

describe('SendDebtReminderNotification job', function (): void {
    it('sends notification when payment is still pending', function (): void {
        Notification::fake();

        $tournament = paidTournament();
        [$user, $payment] = registeredUserWithPayment($tournament);

        (new SendDebtReminderNotification($payment->id, $user->id, $tournament->id))->handle();

        Notification::assertSentTo(
            $user,
            TournamentDebtReminderNotification::class
        );
    });

    it('skips notification when payment is already paid', function (): void {
        Notification::fake();

        $tournament = paidTournament();
        [$user, $payment] = registeredUserWithPayment($tournament);
        $payment->update(['status' => 'paid']);

        (new SendDebtReminderNotification($payment->id, $user->id, $tournament->id))->handle();

        Notification::assertNothingSent();
    });
});
